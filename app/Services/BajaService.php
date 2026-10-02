<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Baja;
use App\Models\Bien;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El tramite de baja de un bien.
 *
 * La baja no borra nada: es un expediente que pasa por dos manos. Mientras esta
 * solicitada el bien sigue en la tarjeta del empleado y sigue sumando al saldo,
 * porque hasta que alguien la autorice la persona sigue respondiendo por el.
 * Al autorizarse se cierra la asignacion y la tarjeta se regenera sin el bien;
 * la version anterior queda guardada, que es el documento que se firmo.
 */
class BajaService
{
    /**
     * Abre el expediente. El bien queda marcado en tramite pero no se mueve de
     * la tarjeta.
     *
     * @param  array<string, mixed>  $datos
     */
    public function solicitar(Bien $bien, array $datos): Baja
    {
        if ($bien->estado === Bien::ESTADO_BAJA) {
            throw ValidationException::withMessages([
                'bien_id' => sprintf('El bien %s ya está dado de baja.', $bien->codigo),
            ]);
        }

        // La base lo garantiza con un indice unico parcial, pero el aviso en
        // pantalla tiene que decir que pasa y no un error de base de datos.
        if ($bien->bajaEnTramite()->exists()) {
            throw ValidationException::withMessages([
                'bien_id' => sprintf('El bien %s ya tiene una baja en trámite.', $bien->codigo),
            ]);
        }

        // La baja es un tramite sobre un documento firmado. Un bien que todavia
        // no salio en papel no hay que darlo de baja: se quita de la tarjeta y
        // listo, porque nadie respondio nunca por el.
        if (! $bien->renglonesTarjeta()->whereNotNull('impreso_at')->exists()) {
            throw ValidationException::withMessages([
                'bien_id' => sprintf(
                    'El bien %s todavía no está impreso en ninguna tarjeta, así que no hay nada que '
                    .'dar de baja. Quítelo de la tarjeta desde la pantalla de la tarjeta.',
                    $bien->codigo,
                ),
            ]);
        }

        return DB::transaction(function () use ($bien, $datos) {
            $baja = Baja::create([
                'bien_id' => $bien->id,
                'numero_acta' => $datos['numero_acta'] ?? null,
                'motivo' => $datos['motivo'],
                'fecha_solicitud' => $datos['fecha_solicitud'] ?? now()->toDateString(),
                'estado' => Baja::ESTADO_SOLICITADA,
                'solicitada_por' => Auth::id(),
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $bien->update(['estado' => Bien::ESTADO_BAJA_SOLICITADA]);

            AuditLog::registrar(
                evento: 'baja.solicitada',
                descripcion: sprintf(
                    'Se solicitó la baja del bien %s: %s',
                    $bien->codigo,
                    $baja->motivo,
                ),
                modelo: $baja,
                datos: ['bien' => $bien->codigo, 'acta' => $baja->numero_acta],
            );

            return $baja;
        });
    }

    /**
     * Autoriza la baja.
     *
     * No toca la tarjeta a proposito. El bien sigue escrito en la hoja que la
     * persona firmo, asi que sigue apareciendo y sigue sumando al saldo, y la
     * custodia sigue abierta: mientras este en el papel, esa persona responde
     * por el. Sale el dia que se regenere la tarjeta, que es cuando nace la
     * version nueva y se imprime en hoja limpia.
     *
     * @param  array<string, mixed>  $datos
     */
    public function autorizar(Baja $baja, array $datos = []): Baja
    {
        $this->exigirEnTramite($baja);

        return DB::transaction(function () use ($baja, $datos) {
            $bien = $baja->bien;

            $bien->update(['estado' => Bien::ESTADO_BAJA]);

            $baja->update([
                'estado' => Baja::ESTADO_AUTORIZADA,
                'fecha_resolucion' => $datos['fecha_resolucion'] ?? now()->toDateString(),
                'numero_acta' => $datos['numero_acta'] ?? $baja->numero_acta,
                'resuelta_por' => Auth::id(),
                'observaciones' => $datos['observaciones'] ?? $baja->observaciones,
            ]);

            AuditLog::registrar(
                evento: 'baja.autorizada',
                descripcion: sprintf(
                    'Se autorizó la baja del bien %s%s. Sale de la tarjeta al regenerarla.',
                    $bien->codigo,
                    $baja->numero_acta ? ' según acta '.$baja->numero_acta : '',
                ),
                modelo: $baja,
                datos: ['bien' => $bien->codigo],
            );

            return $baja->fresh();
        });
    }

    /**
     * Rechaza la baja. El bien vuelve a estar activo, como si no se hubiera
     * pedido nada, y el expediente queda con la razon del rechazo.
     */
    public function rechazar(Baja $baja, string $observaciones): Baja
    {
        $this->exigirEnTramite($baja);

        return DB::transaction(function () use ($baja, $observaciones) {
            $baja->bien->update(['estado' => Bien::ESTADO_ACTIVO]);

            $baja->update([
                'estado' => Baja::ESTADO_RECHAZADA,
                'fecha_resolucion' => now()->toDateString(),
                'resuelta_por' => Auth::id(),
                'observaciones' => $observaciones,
            ]);

            AuditLog::registrar(
                evento: 'baja.rechazada',
                descripcion: sprintf(
                    'Se rechazó la baja del bien %s: %s',
                    $baja->bien->codigo,
                    $observaciones,
                ),
                modelo: $baja,
            );

            return $baja->fresh();
        });
    }

    private function exigirEnTramite(Baja $baja): void
    {
        if (! $baja->estaEnTramite()) {
            throw ValidationException::withMessages([
                'baja' => sprintf(
                    'Esta baja ya está %s y no se puede volver a resolver.',
                    mb_strtolower($baja->estadoLegible()),
                ),
            ]);
        }
    }
}
