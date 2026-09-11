<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\AuditLog;
use App\Models\Bien;
use App\Models\Empleado;
use App\Models\Tarjeta;
use App\Models\TarjetaHoja;
use App\Models\TarjetaRenglon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class TarjetaService
{
    
    public function abrir(Empleado $empleado, ?string $numero = null, ?string $fechaApertura = null): Tarjeta
    {
        if ($empleado->tarjetaVigente()->exists()) {
            throw ValidationException::withMessages([
                'empleado_id' => sprintf('%s ya tiene una tarjeta vigente.', $empleado->nombre_completo),
            ]);
        }

        $tarjeta = Tarjeta::create([
            'empleado_id' => $empleado->id,
            'unidad_servicio_id' => $empleado->unidad_servicio_id,
            'numero' => $numero,
            'fecha_apertura' => $fechaApertura ?? now()->toDateString(),
        ]);

        AuditLog::registrar(
            evento: 'tarjeta.abierta',
            descripcion: sprintf('Se abrió la tarjeta de %s', $empleado->nombre_completo),
            modelo: $tarjeta,
        );

        return $tarjeta;
    }

   
    public function agregarBien(Tarjeta $tarjeta, Bien $bien): TarjetaRenglon
    {
        $this->exigirVigente($tarjeta);

        if ($bien->estado === Bien::ESTADO_BAJA) {
            throw ValidationException::withMessages([
                'bien' => sprintf('El bien %s está dado de baja y no puede asignarse.', $bien->codigo),
            ]);
        }

        if ($tarjeta->renglones()->where('bien_id', $bien->id)->exists()) {
            throw ValidationException::withMessages([
                'bien' => sprintf('El bien %s ya figura en esta tarjeta.', $bien->codigo),
            ]);
        }

        
        $vigente = $bien->asignacionVigente()->with('empleado')->first();

        if ($vigente) {
            throw ValidationException::withMessages([
                'bien' => sprintf(
                    'El bien %s está asignado a %s. Retírelo de esa tarjeta antes de agregarlo aquí.',
                    $bien->codigo,
                    $vigente->empleado?->nombre_completo ?? 'otro empleado',
                ),
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $bien) {
            $renglon = TarjetaRenglon::create([
                'tarjeta_id' => $tarjeta->id,
                'bien_id' => $bien->id,
                'orden' => $tarjeta->siguienteOrden(),
                'debe' => $bien->total,
            ]);

            Asignacion::create([
                'bien_id' => $bien->id,
                'empleado_id' => $tarjeta->empleado_id,
                'tarjeta_id' => $tarjeta->id,
                'fecha_asignacion' => now()->toDateString(),
                'registrado_por' => Auth::id(),
            ]);

            $this->recalcularSaldos($tarjeta);

            AuditLog::registrar(
                evento: 'tarjeta.bien_agregado',
                descripcion: sprintf(
                    'Se agregó el bien %s a la tarjeta de %s',
                    $bien->codigo,
                    $tarjeta->empleado->nombre_completo,
                ),
                modelo: $tarjeta,
                datos: ['bien' => $bien->codigo, 'orden' => $renglon->orden],
            );

            return $renglon;
        });
    }

    
    public function quitarBien(Tarjeta $tarjeta, Bien $bien, string $motivo = 'cambio_responsable'): Tarjeta
    {
        $this->exigirVigente($tarjeta);

        $renglon = $tarjeta->renglones()->where('bien_id', $bien->id)->first();

        if (! $renglon) {
            throw ValidationException::withMessages([
                'bien' => sprintf('El bien %s no figura en esta tarjeta.', $bien->codigo),
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $bien, $renglon, $motivo) {
            Asignacion::where('bien_id', $bien->id)
                ->where('activa', true)
                ->update([
                    'activa' => false,
                    'fecha_devolucion' => now()->toDateString(),
                    'motivo_cierre' => $motivo,
                ]);

            AuditLog::registrar(
                evento: 'tarjeta.bien_retirado',
                descripcion: sprintf(
                    'Se retiró el bien %s de la tarjeta de %s',
                    $bien->codigo,
                    $tarjeta->empleado->nombre_completo,
                ),
                modelo: $tarjeta,
                datos: ['bien' => $bien->codigo, 'motivo' => $motivo],
            );

            
            if (! $renglon->yaSeImprimio()) {
                $renglon->delete();
                $this->compactarOrden($tarjeta);
                $this->recalcularSaldos($tarjeta);

                return $tarjeta->fresh();
            }

            
            return $this->regenerar($tarjeta, excluyendo: [$bien->id]);
        });
    }

    /**
     * Crea la version siguiente de la tarjeta con los mismos bienes menos los
     * excluidos. La version anterior queda como el documento que se firmo.
     *
     * @param  array<int, int>  $excluyendo  identificadores de bienes a dejar fuera
     */
    public function regenerar(Tarjeta $tarjeta, array $excluyendo = []): Tarjeta
    {
        $this->exigirVigente($tarjeta);

        return DB::transaction(function () use ($tarjeta, $excluyendo) {
            $renglones = $tarjeta->renglones()
                ->whereNotIn('bien_id', $excluyendo)
                ->orderBy('orden')
                ->get();

            $tarjeta->update(['estado' => Tarjeta::ESTADO_REEMPLAZADA]);

            $nueva = Tarjeta::create([
                'empleado_id' => $tarjeta->empleado_id,
                'unidad_servicio_id' => $tarjeta->unidad_servicio_id,
                'numero' => $tarjeta->numero,
                'version' => $tarjeta->version + 1,
                'reemplaza_a' => $tarjeta->id,
                'estado' => Tarjeta::ESTADO_VIGENTE,
                'fecha_apertura' => $tarjeta->fecha_apertura,
                'renglones_por_hoja' => $tarjeta->renglones_por_hoja,
            ]);

            
            $orden = 1;

            foreach ($renglones as $renglon) {
                TarjetaRenglon::create([
                    'tarjeta_id' => $nueva->id,
                    'bien_id' => $renglon->bien_id,
                    'orden' => $orden++,
                    'debe' => $renglon->debe,
                    'haber' => $renglon->haber,

                   
                    'total_corte_original' => $renglon->total_corte_original,

                    'observaciones' => $renglon->observaciones,
                ]);
            }

            // Las custodias vigentes pasan a colgar de la tarjeta nueva.
            Asignacion::where('tarjeta_id', $tarjeta->id)
                ->where('activa', true)
                ->update(['tarjeta_id' => $nueva->id]);

            $this->recalcularSaldos($nueva);

            AuditLog::registrar(
                evento: 'tarjeta.regenerada',
                descripcion: sprintf(
                    'Se generó la versión %d de la tarjeta de %s con %d bien(es)',
                    $nueva->version,
                    $tarjeta->empleado->nombre_completo,
                    $renglones->count(),
                ),
                modelo: $nueva,
                datos: ['reemplaza_a' => $tarjeta->id, 'excluidos' => $excluyendo],
            );

            return $nueva;
        });
    }

    
    public function recalcularSaldos(Tarjeta $tarjeta): void
    {
        $acumulado = 0.0;

        foreach ($tarjeta->renglones()->orderBy('orden')->get() as $renglon) {
            $acumulado += (float) $renglon->debe - (float) $renglon->haber;

            $renglon->updateQuietly(['saldo' => $acumulado]);
        }

        $tarjeta->updateQuietly(['saldo_total' => $acumulado]);
    }


    public function marcarImpreso(Tarjeta $tarjeta, array $renglonIds, int $hoja): void
    {
        $tarjeta->renglones()
            ->whereIn('id', $renglonIds)
            ->update(['hoja_fisica' => $hoja, 'impreso_at' => now()]);

        // La hoja de papel queda marcada la primera vez que sale de la
        // impresora. Es lo que despues permite saber que sobre ese papel ya no
        // se puede escribir a ciegas.
        $papel = $tarjeta->hoja($hoja);

        if (! $papel->seImprimio()) {
            $papel->update(['impresa_at' => now()]);
        }

        AuditLog::registrar(
            evento: 'tarjeta.impresa',
            descripcion: sprintf(
                'Se imprimieron %d renglón(es) de la tarjeta de %s en la hoja %d (%s)',
                count($renglonIds),
                $tarjeta->empleado->nombre_completo,
                $hoja,
                Tarjeta::caraDeHoja($hoja),
            ),
            modelo: $tarjeta,
        );
    }

    /**
     * Retracta la marca de impresion de un solo renglon.
     *
     * Es una correccion de control interno, para cuando se marco por error algo
     * que no salio en papel. Va de a un bien a proposito: retractar una hoja
     * entera dejaria sin sentido el corte de VAN que ya quedo impreso en ella.
     *
     * No borra tinta. Si el papel si salio, hay que descartarlo o reimprimir la
     * hoja; por eso se exige una justificacion que queda en la bitacora.
     */
    public function desmarcarImpresion(Tarjeta $tarjeta, TarjetaRenglon $renglon, string $motivo): void
    {
        if ($renglon->tarjeta_id !== $tarjeta->id) {
            throw ValidationException::withMessages([
                'renglon' => 'Ese renglón no pertenece a esta tarjeta.',
            ]);
        }

        if (! $renglon->yaSeImprimio()) {
            throw ValidationException::withMessages([
                'renglon' => sprintf(
                    'El bien %s no está marcado como impreso, no hay nada que retractar.',
                    $renglon->bien->codigo,
                ),
            ]);
        }

        DB::transaction(function () use ($tarjeta, $renglon, $motivo) {
            $hoja = $renglon->hoja_fisica;

            $renglon->update(['impreso_at' => null]);

            // Si en esa hoja ya no queda nada impreso, para el sistema el papel
            // vuelve a estar limpio: se retira la marca y se reabre, porque un
            // papel sin renglones impresos no puede estar cerrado.
            $quedanImpresos = $hoja !== null && $tarjeta->renglones()
                ->where('hoja_fisica', $hoja)
                ->whereNotNull('impreso_at')
                ->exists();

            if ($hoja !== null && ! $quedanImpresos) {
                $tarjeta->hoja($hoja)->update(['impresa_at' => null, 'cerrada_at' => null]);
            }

            AuditLog::registrar(
                evento: 'tarjeta.impresion_retractada',
                descripcion: sprintf(
                    'Se retractó la impresión del bien %s en la tarjeta de %s',
                    $renglon->bien->codigo,
                    $tarjeta->empleado->nombre_completo,
                ),
                modelo: $tarjeta,
                datos: [
                    'bien' => $renglon->bien->codigo,
                    'descripcion' => mb_substr($renglon->bien->descripcion, 0, 160),
                    'hoja' => $hoja,
                    'motivo' => $motivo,
                ],
            );
        });
    }

    /**
     * Mueve un renglon a otra hoja de papel.
     *
     * Solo se puede mover lo que todavia no salio en tinta: un renglon impreso
     * ocupa un lugar fisico en un papel que la persona ya firmo, y cambiarlo de
     * hoja en el sistema no lo mueve en el papel.
     */
    public function moverRenglonAHoja(Tarjeta $tarjeta, TarjetaRenglon $renglon, int $hoja): void
    {
        $this->exigirVigente($tarjeta);

        if ($renglon->tarjeta_id !== $tarjeta->id) {
            throw ValidationException::withMessages([
                'renglon' => 'El renglón no pertenece a esta tarjeta.',
            ]);
        }

        if ($renglon->yaSeImprimio()) {
            throw ValidationException::withMessages([
                'renglon' => sprintf(
                    'El bien %s ya salió impreso en la hoja %d. Su posición en el papel es definitiva.',
                    $renglon->bien->codigo,
                    $renglon->hoja_fisica,
                ),
            ]);
        }

        $papel = $tarjeta->hoja($hoja);

        if ($papel->estaCerrada()) {
            throw ValidationException::withMessages([
                'hoja' => sprintf('La hoja %d está cerrada y ya no admite bienes.', $hoja),
            ]);
        }

        $ocupados = $tarjeta->renglones()
            ->where('hoja_fisica', $hoja)
            ->where('id', '!=', $renglon->id)
            ->count();

        if ($ocupados >= $tarjeta->renglones_por_hoja) {
            throw ValidationException::withMessages([
                'hoja' => sprintf(
                    'La hoja %d ya tiene sus %d renglones. No cabe uno más.',
                    $hoja,
                    $tarjeta->renglones_por_hoja,
                ),
            ]);
        }

        $anterior = $renglon->hoja_fisica;
        $renglon->update(['hoja_fisica' => $hoja]);

        AuditLog::registrar(
            evento: 'tarjeta.renglon_movido',
            descripcion: sprintf(
                'El bien %s pasó de la hoja %s a la hoja %d en la tarjeta de %s',
                $renglon->bien->codigo,
                $anterior ?? 'sin asignar',
                $hoja,
                $tarjeta->empleado->nombre_completo,
            ),
            modelo: $tarjeta,
            datos: ['renglon_id' => $renglon->id, 'hoja_anterior' => $anterior, 'hoja_nueva' => $hoja],
        );
    }

    /**
     * Guarda el calce de una hoja: los milimetros que hay que correr la
     * impresion para que la tinta caiga en los espacios libres del papel.
     */
    public function guardarCalce(Tarjeta $tarjeta, int $hoja, float $x, float $y): TarjetaHoja
    {
        $tope = TarjetaHoja::DESFASE_MAXIMO_MM;

        $papel = $tarjeta->hoja($hoja);

        $papel->update([
            'desfase_x_mm' => round(max(-$tope, min($tope, $x)), 1),
            'desfase_y_mm' => round(max(-$tope, min($tope, $y)), 1),
        ]);

        AuditLog::registrar(
            evento: 'tarjeta.calce_guardado',
            descripcion: sprintf(
                'Se calibró la hoja %d de la tarjeta de %s en %s / %s mm',
                $hoja,
                $tarjeta->empleado->nombre_completo,
                $papel->desfase_x_mm,
                $papel->desfase_y_mm,
            ),
            modelo: $tarjeta,
        );

        return $papel;
    }

    /**
     * Cierra una hoja: deja de admitir bienes y pasa a llevar su linea de VAN.
     *
     * Mientras la hoja sigue abierta el corte no se imprime, porque el saldo del
     * papel cambiaria en cuanto entre el proximo bien.
     */
    public function cerrarHoja(Tarjeta $tarjeta, int $hoja): TarjetaHoja
    {
        $this->exigirVigente($tarjeta);

        $papel = $tarjeta->hoja($hoja);

        if ($papel->estaCerrada()) {
            return $papel;
        }

        $papel->update(['cerrada_at' => now()]);

        AuditLog::registrar(
            evento: 'tarjeta.hoja_cerrada',
            descripcion: sprintf(
                'Se cerró la hoja %d (%s) de la tarjeta de %s',
                $hoja,
                Tarjeta::caraDeHoja($hoja),
                $tarjeta->empleado->nombre_completo,
            ),
            modelo: $tarjeta,
        );

        return $papel;
    }

    /**
     * Reabre una hoja cerrada por error. No borra tinta: solo permite volver a
     * usar el espacio libre que le haya quedado.
     */
    public function reabrirHoja(Tarjeta $tarjeta, int $hoja): TarjetaHoja
    {
        $this->exigirVigente($tarjeta);

        $papel = $tarjeta->hoja($hoja);
        $papel->update(['cerrada_at' => null]);

        AuditLog::registrar(
            evento: 'tarjeta.hoja_reabierta',
            descripcion: sprintf(
                'Se reabrió la hoja %d de la tarjeta de %s',
                $hoja,
                $tarjeta->empleado->nombre_completo,
            ),
            modelo: $tarjeta,
        );

        return $papel;
    }

    
    private function compactarOrden(Tarjeta $tarjeta): void
    {
        $orden = 1;

        foreach ($tarjeta->renglones()->orderBy('orden')->get() as $renglon) {
            if ($renglon->yaSeImprimio()) {
                $orden = $renglon->orden + 1;

                continue;
            }

            if ($renglon->orden !== $orden) {
                // Se libera la posicion antes de ocuparla: la tarjeta tiene un
                // indice unico sobre (tarjeta_id, orden).
                $renglon->updateQuietly(['orden' => -$renglon->id]);
                $renglon->updateQuietly(['orden' => $orden]);
            }

            $orden++;
        }
    }

    private function exigirVigente(Tarjeta $tarjeta): void
    {
        if (! $tarjeta->estaVigente()) {
            throw ValidationException::withMessages([
                'tarjeta' => 'Esta tarjeta fue reemplazada por una versión más nueva y ya no se puede modificar.',
            ]);
        }
    }
}
