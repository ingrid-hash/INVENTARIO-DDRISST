<?php

namespace App\Services\Importacion;

use App\Models\AuditLog;
use App\Models\Bien;
use App\Models\Empleado;
use App\Models\Importacion;
use App\Models\ImportacionError;
use App\Models\Renglon;
use App\Models\Tarjeta;
use App\Models\TarjetaRenglon;
use App\Models\UnidadServicio;
use App\Services\TarjetaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class ImportadorExcel
{
    public function __construct(
        private readonly AnalizadorHoja $analizador,
        private readonly TarjetaService $tarjetas,
    ) {}

    /**
     * Corre el analisis sin escribir nada, para que quien importa vea lo que va
     * a pasar antes de confirmarlo.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<string, int>  $mapeo
     * @return array<string, mixed>
     */
    public function previsualizar(array $filas, array $mapeo, ?int $filaEncabezado, string $tipo): array
    {
        $analisis = $this->analizador->analizar($filas, $mapeo, $filaEncabezado);

        $codigos = array_values(array_filter(array_column($analisis['bienes'], 'codigo')));

        // Los que llegan sin codigo entran igual, con uno que genera el sistema.
        $sinCodigo = count(array_filter($analisis['bienes'], fn (array $b) => $b['sin_codigo']));

        // Choques contra lo que ya esta cargado en el sistema.
        $yaExisten = $codigos === [] ? [] : Bien::withTrashed()
            ->whereIn('codigo', $codigos)
            ->pluck('codigo')
            ->all();

        $cuentasConocidas = Renglon::pluck('codigo')->all();

        $cuentasFaltantes = array_values(array_unique(array_filter(
            array_column($analisis['bienes'], 'cuenta'),
            fn (?string $c) => $c !== null && ! in_array($c, $cuentasConocidas, true),
        )));

        $empleadoParecido = null;

        if ($tipo === 'tarjeta' && ! empty($analisis['encabezado']['nombre'])) {
            $empleadoParecido = $this->buscarEmpleadoParecido($analisis['encabezado']['nombre']);
        }

        return [
            'encabezado' => $analisis['encabezado'],
            'total_detectados' => count($analisis['bienes']),
            'nuevos' => count($analisis['bienes']) - count($yaExisten),
            'sin_codigo' => $sinCodigo,
            'ya_existen' => $yaExisten,
            'filas_descartadas' => $analisis['descartadas'],
            'errores' => $analisis['errores'],
            'renglones_detectados' => $analisis['renglones_detectados'],
            'cuentas_faltantes' => $cuentasFaltantes,
            'empleado_parecido' => $empleadoParecido,
            // Una muestra basta para que se vea si el mapeo quedo bien.
            'muestra' => array_slice($analisis['bienes'], 0, 20),
            'suma_total' => round(array_sum(array_column($analisis['bienes'], 'total')), 2),
        ];
    }

    /**
     * Escribe el resultado en la base de datos.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<string, int>  $mapeo
     */
    public function ejecutar(
        array $filas,
        array $mapeo,
        ?int $filaEncabezado,
        string $tipo,
        UnidadServicio $unidad,
        string $archivo,
        string $hoja,
        ?int $perfilId = null,
    ): Importacion {
        $analisis = $this->analizador->analizar($filas, $mapeo, $filaEncabezado);

        $importacion = Importacion::create([
            'archivo' => $archivo,
            'hoja' => $hoja,
            'tipo' => $tipo,
            'unidad_servicio_id' => $unidad->id,
            'perfil_id' => $perfilId,
            'user_id' => Auth::id(),
            'filas_leidas' => count($filas),
            'estado' => 'pendiente',
        ]);

        // Los errores que ya venian del analisis se registran tal cual.
        foreach ($analisis['errores'] as $error) {
            $this->registrarError($importacion, $error);
        }

        $cuentas = Renglon::pluck('id', 'codigo')->all();

        $empleado = null;
        $tarjeta = null;

        if ($tipo === 'tarjeta') {
            $empleado = $this->resolverEmpleado($analisis['encabezado'], $unidad);
            $tarjeta = $empleado->tarjetaVigente ?? $this->tarjetas->abrir($empleado);
        }

        $importados = 0;
        $provisionales = 0;

        
        $posiciones = [];

        // Un bien que el archivo no numera solo se puede reconocer por su
        // descripcion. Se arma una bolsa de candidatos y cada bien sin codigo va
        // tomando uno: asi dos bienes identicos —en la tarjeta de Chinimabe hay
        // dos bancas iguales— se emparejan con los dos que ya estan cargados en
        // lugar de crear copias.
        //
        // De la bolsa se excluyen los codigos que el archivo si trae, porque
        // esos se reconocen por codigo. Lo que queda son los que llegaron sin
        // codigo, incluidos aquellos cuyo provisional ya se corrigio a mano.
        $codigosDelArchivo = array_values(array_filter(array_column($analisis['bienes'], 'codigo')));

        $sinCodigoYaCargados = Bien::query()
            ->where('unidad_servicio_id', $unidad->id)
            ->when($codigosDelArchivo !== [], fn ($q) => $q->whereNotIn('codigo', $codigosDelArchivo))
            ->get(['id', 'descripcion'])
            ->groupBy(fn (Bien $bien) => $this->clavePorDescripcion($bien->descripcion))
            ->map(fn ($grupo) => $grupo->pluck('id')->all())
            ->all();

        foreach ($analisis['bienes'] as $datos) {
            // Si queda un candidato con la misma descripcion, este bien ya entro
            // en una pasada anterior y no hay que volver a crearlo.
            if ($datos['sin_codigo']) {
                $clave = $this->clavePorDescripcion($datos['descripcion']);

                if (! empty($sinCodigoYaCargados[$clave])) {
                    $existente = array_shift($sinCodigoYaCargados[$clave]);

                    if ($tarjeta !== null) {
                        $posiciones[$existente] = $datos['posicion'];
                    }

                    $this->registrarError($importacion, [
                        'fila' => $datos['fila'],
                        'codigo' => null,
                        'motivo_clave' => 'sin_codigo_ya_cargado',
                        'motivo' => 'Este bien no trae código en el archivo y ya estaba cargado; no se duplicó.',
                        'datos' => ['descripcion' => mb_substr($datos['descripcion'], 0, 120)],
                    ]);

                    continue;
                }
            }

           
            try {
                DB::transaction(function () use (
                    $datos, $unidad, $cuentas, $importacion, $tarjeta,
                    &$importados, &$provisionales, &$posiciones
                ) {
                    // Sin codigo en el archivo, el sistema pone uno propio para
                    // que el bien no se pierda.
                    $codigo = $datos['sin_codigo']
                        ? Bien::generarCodigoProvisional($unidad)
                        : $datos['codigo'];

                    if ($datos['sin_codigo']) {
                        $provisionales++;
                    }

                    $bien = Bien::create([
                        'codigo' => $codigo,
                        'codigo_provisional' => $datos['sin_codigo'],
                        'descripcion' => $datos['descripcion'],
                        'cantidad' => $datos['cantidad'],
                        'precio_unitario' => $datos['precio_unitario'],
                        'total' => $datos['total'],
                        'unidad_servicio_id' => $unidad->id,
                        'renglon_id' => $cuentas[$datos['cuenta']] ?? null,
                        'cuenta_texto_original' => $datos['cuenta_texto_original'],
                        'tipo_movimiento' => $datos['tipo_movimiento'],
                        'forma_adquisicion' => $datos['forma_adquisicion'],
                        'programa' => $datos['programa'],
                        'documento_respaldo' => $datos['documento_respaldo'],
                        'fecha_texto_original' => $datos['fecha_texto_original'],
                        'fecha_ingreso' => $datos['fecha_ingreso'],
                        'anio_ingreso' => $datos['anio_ingreso'],
                        'observaciones' => $datos['observaciones'],
                        'importacion_id' => $importacion->id,
                    ]);

                    if ($tarjeta !== null) {
                        // El orden definitivo se fija al final, cuando ya se sabe
                        // que bienes entraron: aqui basta con una posicion libre.
                        TarjetaRenglon::create([
                            'tarjeta_id' => $tarjeta->id,
                            'bien_id' => $bien->id,
                            'orden' => $tarjeta->siguienteOrden(),

                            // Un renglon es cargo o descargo, nunca los dos: en
                            // el papel el monto esta en DEBE o en HABER.
                            'debe' => $datos['haber'] > 0 ? 0 : $bien->total,
                            'haber' => $datos['haber'],

                            // Si en el papel venia un TOTAL despues de este
                            // bien, se conserva tal cual: es el que se firmo.
                            'total_corte_original' => $datos['total_corte_original'],
                        ]);

                        \App\Models\Asignacion::create([
                            'bien_id' => $bien->id,
                            'empleado_id' => $tarjeta->empleado_id,
                            'tarjeta_id' => $tarjeta->id,
                            'fecha_asignacion' => $bien->fecha_ingreso ?? now()->toDateString(),
                            'registrado_por' => Auth::id(),
                        ]);

                        $posiciones[$bien->id] = $datos['posicion'];
                    }

                    $importados++;
                });
            } catch (QueryException $e) {
                // Un codigo repetido es lo esperable al reimportar una hoja: el
                // bien ya esta y se anota su posicion para el reordenamiento.
                if ($tarjeta !== null && $this->clasificar($e) === 'codigo_duplicado') {
                    $existente = Bien::where('codigo', $datos['codigo'])->value('id');

                    if ($existente !== null) {
                        $posiciones[$existente] = $datos['posicion'];
                    }
                }

                $this->registrarError($importacion, [
                    'fila' => $datos['fila'],
                    'codigo' => $datos['codigo'],
                    'motivo_clave' => $this->clasificar($e),
                    'motivo' => $this->explicar($e, $datos['codigo'] ?? '(sin código)'),
                    'datos' => ['descripcion' => mb_substr($datos['descripcion'], 0, 120)],
                ]);
            }
        }

        if ($tarjeta !== null) {
            $this->ordenarSegunArchivo($tarjeta->fresh(), $posiciones);
            $this->tarjetas->recalcularSaldos($tarjeta->fresh());
        }

        $rechazadas = $importacion->errores()->count();

        $importacion->update([
            'filas_importadas' => $importados,
            'filas_rechazadas' => $rechazadas,
            'estado' => 'confirmada',
            'resumen' => [
                'encabezado' => $analisis['encabezado'],
                'renglones_detectados' => $analisis['renglones_detectados'],
                'filas_descartadas' => $analisis['descartadas'],
                'empleado_id' => $empleado?->id,
                'tarjeta_id' => $tarjeta?->id,
                'codigos_provisionales' => $provisionales,
            ],
        ]);

        AuditLog::registrar(
            evento: 'importacion.ejecutada',
            descripcion: sprintf(
                'Se importaron %d bien(es) de %s (hoja %s) a %s; %d con código provisional, %d fila(s) rechazada(s)',
                $importados,
                $archivo,
                $hoja,
                $unidad->nombre,
                $provisionales,
                $rechazadas,
            ),
            modelo: $importacion,
        );

        return $importacion->fresh(['errores']);
    }

    /**
     * Clave con la que se reconoce un bien que no trae codigo.
     *
     * Se normaliza la descripcion —mayusculas, espacios y puntuacion— porque es
     * lo unico que identifica a esos bienes, y en los archivos la misma
     * descripcion aparece escrita con pequenas diferencias de tipeo.
     */
    private function clavePorDescripcion(string $descripcion): string
    {
        $clave = mb_strtolower(trim($descripcion));
        $clave = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $clave) ?? $clave;

        return trim((string) preg_replace('/\s+/', ' ', $clave));
    }

    
    private function ordenarSegunArchivo(Tarjeta $tarjeta, array $posiciones): void
    {
        if ($posiciones === []) {
            return;
        }

        $renglones = $tarjeta->renglones()->get();

        // Un renglon impreso conserva su sitio; el resto se ordena por la
        // posicion del archivo, y lo que no aparece en el va al final.
        $ordenados = $renglones
            ->sortBy(fn (TarjetaRenglon $r) => [
                $r->yaSeImprimio() ? 0 : 1,
                $r->yaSeImprimio() ? $r->orden : ($posiciones[$r->bien_id] ?? PHP_INT_MAX),
                $r->orden,
            ])
            ->values();

        DB::transaction(function () use ($ordenados) {
            // Se liberan primero todas las posiciones: la tabla tiene un indice
            // unico sobre (tarjeta_id, orden) y reasignar en el sitio chocaria.
            foreach ($ordenados as $renglon) {
                $renglon->updateQuietly(['orden' => -$renglon->id]);
            }

            foreach ($ordenados as $indice => $renglon) {
                $renglon->updateQuietly(['orden' => $indice + 1]);
            }
        });
    }

    /**
     * Deshace una carga completa. Solo se puede si ningun bien importado quedo
     * bajo la custodia de alguien fuera de esa misma importacion.
     */
    public function revertir(Importacion $importacion): int
    {
        return DB::transaction(function () use ($importacion) {
            $bienes = Bien::where('importacion_id', $importacion->id)->pluck('id');

            \App\Models\Asignacion::whereIn('bien_id', $bienes)->delete();
            TarjetaRenglon::whereIn('bien_id', $bienes)->delete();

            $borrados = Bien::whereIn('id', $bienes)->forceDelete();

            // Una tarjeta creada por la importacion y que quedo vacia se retira.
            $tarjetaId = $importacion->resumen['tarjeta_id'] ?? null;

            if ($tarjetaId !== null) {
                $tarjeta = Tarjeta::find($tarjetaId);

                if ($tarjeta && $tarjeta->renglones()->count() === 0) {
                    $tarjeta->delete();
                }
            }

            $importacion->update(['estado' => 'revertida']);

            AuditLog::registrar(
                evento: 'importacion.revertida',
                descripcion: sprintf(
                    'Se revirtió la importación de %s: se eliminaron %d bien(es)',
                    $importacion->archivo,
                    $borrados,
                ),
                modelo: $importacion,
            );

            return $borrados;
        });
    }

    /**
     * Encuentra al empleado del encabezado o lo crea.
     *
     * Antes de crear busca uno parecido: en los archivos la misma persona
     * aparece con y sin el titulo delante, y crear las dos partiria su tarjeta.
     *
     * @param  array<string, string|null>  $encabezado
     */
    private function resolverEmpleado(array $encabezado, UnidadServicio $unidad): Empleado
    {
        $nombre = $encabezado['nombre'] ?? null;

        if ($nombre === null || $nombre === '') {
            throw new \RuntimeException(
                'La hoja no trae el nombre del empleado en el encabezado, así que no se puede importar como tarjeta.'
            );
        }

        $parecido = $this->buscarEmpleadoParecido($nombre, $unidad->id);

        if ($parecido !== null) {
            return Empleado::find($parecido['id']);
        }

        return Empleado::create([
            'unidad_servicio_id' => $unidad->id,
            'nombre_completo' => $nombre,
            'cargo' => $encabezado['cargo'] ?? null,
            'area_trabajo' => $encabezado['area_trabajo'] ?? null,
        ]);
    }

    /**
     * @return array{id: int, nombre: string, similitud: int}|null
     */
    private function buscarEmpleadoParecido(string $nombre, ?int $unidadId = null): ?array
    {
        $normalizar = function (string $texto): string {
            $texto = mb_strtoupper(trim($texto));
            $texto = preg_replace('/^(DOCTOR|DOCTORA|DR|DRA|LIC|LICDA|ING|MSC|EP|SR|SRA)\.?\s+/u', '', $texto) ?? $texto;

            return preg_replace('/\s+/', ' ', $texto) ?? $texto;
        };

        $candidato = $normalizar($nombre);
        $mejor = null;

        $empleados = Empleado::query()
            ->when($unidadId !== null, fn ($q) => $q->where('unidad_servicio_id', $unidadId))
            ->get(['id', 'nombre_completo']);

        foreach ($empleados as $empleado) {
            similar_text($candidato, $normalizar($empleado->nombre_completo), $porcentaje);

            if ($porcentaje >= 82 && ($mejor === null || $porcentaje > $mejor['similitud'])) {
                $mejor = [
                    'id' => $empleado->id,
                    'nombre' => $empleado->nombre_completo,
                    'similitud' => (int) round($porcentaje),
                ];
            }
        }

        return $mejor;
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function registrarError(Importacion $importacion, array $error): void
    {
        ImportacionError::create([
            'importacion_id' => $importacion->id,
            'fila' => $error['fila'],
            'codigo' => $error['codigo'],
            'motivo_clave' => $error['motivo_clave'],
            'motivo' => $error['motivo'],
            'datos' => $error['datos'] ?? null,
        ]);
    }

    private function clasificar(QueryException $e): string
    {
        $mensaje = $e->getMessage();

        if (str_contains($mensaje, 'bienes_codigo_unique')) {
            return 'codigo_duplicado';
        }

        if (str_contains($mensaje, 'asignaciones_bien_activa_unica')) {
            return 'bien_ya_asignado';
        }

        return 'error_base_datos';
    }

    private function explicar(QueryException $e, string $codigo): string
    {
        return match ($this->clasificar($e)) {
            'codigo_duplicado' => sprintf('El código %s ya existe en el sistema.', $codigo),
            'bien_ya_asignado' => sprintf('El bien %s ya está asignado a otro empleado.', $codigo),
            default => 'No se pudo guardar la fila: '.mb_substr(explode("\n", $e->getMessage())[0], 0, 160),
        };
    }
}
