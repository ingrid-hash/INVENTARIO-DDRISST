<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Importacion;
use App\Models\PerfilImportacion;
use App\Models\UnidadServicio;
use App\Services\Importacion\ImportadorExcel;
use App\Services\Importacion\LectorLibro;
use App\Support\Excel\DetectorFormato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


class ImportacionController extends Controller
{
    /** Carpeta temporal donde vive el archivo mientras se configura la carga. */
    private const CARPETA = 'importaciones';

    public function __construct(private readonly ImportadorExcel $importador) {}

    public function index(): Response
    {
        $importaciones = Importacion::query()
            ->with(['unidadServicio:id,nombre', 'user:id,username'])
            ->withCount('errores')
            ->latest()
            ->paginate(15)
            ->through(fn (Importacion $i) => [
                'id' => $i->id,
                'archivo' => $i->archivo,
                'hoja' => $i->hoja,
                'tipo' => $i->tipo,
                'unidad' => $i->unidadServicio?->nombre,
                'usuario' => $i->user?->username,
                'leidas' => $i->filas_leidas,
                'importadas' => $i->filas_importadas,
                'rechazadas' => $i->errores_count,
                'estado' => $i->estado,
                'fecha' => $i->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('inventario/importacion/index', [
            'importaciones' => $importaciones,
            'unidades' => UnidadServicio::activas()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    /**
     * Recibe el archivo y devuelve las hojas que contiene, con una cuenta de
     * cuantos codigos de inventario trae cada una.
     */
    public function subir(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:20480'],
        ], attributes: ['archivo' => 'archivo']);

        $subido = $request->file('archivo');
        $ruta = $subido->store(self::CARPETA);

        // Se abre una vez para confirmar que el libro se puede leer: mejor
        // fallar aqui que en la pantalla siguiente.
        try {
            (new LectorLibro(Storage::path($ruta)))->hojas();
        } catch (\Throwable $e) {
            Storage::delete($ruta);

            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Verifique que sea un libro de Excel válido. ('
                    .mb_substr($e->getMessage(), 0, 120).')',
            ]);
        }

        return to_route('inventario.importacion.configurar', [
            'ruta' => $ruta,
            'nombre' => $subido->getClientOriginalName(),
        ]);
    }

    /**
     * Elección de hoja y revisión del mapeo propuesto.
     */
    public function configurar(Request $request): Response
    {
        $datos = $request->validate([
            'ruta' => ['required', 'string'],
            'nombre' => ['required', 'string'],
            'hoja' => ['nullable', 'string'],
        ]);

        $rutaCompleta = $this->rutaSegura($datos['ruta']);
        $lector = new LectorLibro($rutaCompleta);

        $hojas = $lector->hojas();

        // Por defecto se propone la hoja legible mas grande: en los archivos del
        // MSPAS las demas suelen ser versiones antiguas, auxiliares o vacias.
        $hoja = $datos['hoja'] ?? collect($hojas)
            ->where('legible', true)
            ->sortByDesc('filas')
            ->first()['nombre'] ?? null;

        $deteccion = null;
        $vista = null;
        $avisoHoja = null;

        if ($hoja !== null) {
            try {
                $filas = $lector->filas($hoja);
                $deteccion = (new DetectorFormato)->detectar($filas);

                $vista = $this->importador->previsualizar(
                    $filas,
                    $deteccion['mapeo'],
                    $deteccion['fila_encabezado'],
                    $deteccion['tipo_sugerido'],
                );
            } catch (\RuntimeException $e) {
                // La hoja no se puede procesar: se explica por que y se deja
                // elegir otra, en lugar de dejar la pantalla en blanco.
                $avisoHoja = $e->getMessage();
            }
        }

        return Inertia::render('inventario/importacion/configurar', [
            'archivo' => ['ruta' => $datos['ruta'], 'nombre' => $datos['nombre']],
            'hojas' => $hojas,
            'hojaElegida' => $hoja,
            'deteccion' => $deteccion ? [
                'fila_encabezado' => $deteccion['fila_encabezado'],
                'tipo_sugerido' => $deteccion['tipo_sugerido'],
                'confianza' => $deteccion['confianza'],
                'mapeo' => $this->mapeoConLetras($deteccion['mapeo']),
                'columnas' => $this->columnasConLetras($deteccion['columnas']),
            ] : null,
            'vista' => $vista,
            'avisoHoja' => $avisoHoja,
            'unidades' => UnidadServicio::activas()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'campos' => $this->camposDisponibles(),
            'perfiles' => PerfilImportacion::with('unidadServicio:id,nombre')
                ->orderBy('nombre')
                ->get()
                ->map(fn (PerfilImportacion $p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'tipo' => $p->tipo,
                    'unidad' => $p->unidadServicio?->nombre,
                    'mapeo' => $this->mapeoConLetras($p->mapeo ?? []),
                    'fila_encabezado' => $p->fila_encabezado,
                ]),
        ]);
    }

    /**
     * Recalcula la previsualizacion con el mapeo que el usuario ajusto a mano.
     */
    public function previsualizar(Request $request): RedirectResponse
    {
        $datos = $this->validarCarga($request, exigirUnidad: false);

        return back()->with('vistaRecalculada', $this->calcularVista($datos));
    }

    public function ejecutar(Request $request): RedirectResponse
    {
        $datos = $this->validarCarga($request, exigirUnidad: true);

        $rutaCompleta = $this->rutaSegura($datos['ruta']);
        $unidad = UnidadServicio::findOrFail($datos['unidad_servicio_id']);
        $mapeo = $this->mapeoConIndices($datos['mapeo']);

        if (! isset($mapeo['codigo']) || ! isset($mapeo['descripcion'])) {
            throw ValidationException::withMessages([
                'mapeo' => 'Hay que indicar al menos qué columna trae el código y cuál la descripción.',
            ]);
        }

        try {
            $filas = (new LectorLibro($rutaCompleta))->filas($datos['hoja']);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['hoja' => $e->getMessage()]);
        }

        $perfil = null;

        if ($request->filled('guardar_perfil')) {
            $perfil = PerfilImportacion::updateOrCreate(
                ['nombre' => $request->string('guardar_perfil')->toString()],
                [
                    'unidad_servicio_id' => $unidad->id,
                    'tipo' => $datos['tipo'],
                    'mapeo' => $mapeo,
                    'fila_encabezado' => $datos['fila_encabezado'],
                ],
            );
        }

        try {
            $importacion = $this->importador->ejecutar(
                filas: $filas,
                mapeo: $mapeo,
                filaEncabezado: $datos['fila_encabezado'],
                tipo: $datos['tipo'],
                unidad: $unidad,
                archivo: $datos['nombre'],
                hoja: $datos['hoja'],
                perfilId: $perfil?->id,
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['tipo' => $e->getMessage()]);
        }

        // El archivo temporal ya cumplió su función.
        Storage::delete($datos['ruta']);

        return to_route('inventario.importacion.show', $importacion)->with('status', sprintf(
            'Se importaron %d bien(es). %s',
            $importacion->filas_importadas,
            $importacion->filas_rechazadas > 0
                ? sprintf('%d fila(s) quedaron sin importar: revise el detalle.', $importacion->filas_rechazadas)
                : 'No hubo filas rechazadas.',
        ));
    }

    public function show(Importacion $importacion): Response
    {
        $importacion->load(['unidadServicio:id,nombre', 'user:id,username']);

        return Inertia::render('inventario/importacion/detalle', [
            'importacion' => [
                'id' => $importacion->id,
                'archivo' => $importacion->archivo,
                'hoja' => $importacion->hoja,
                'tipo' => $importacion->tipo,
                'unidad' => $importacion->unidadServicio?->nombre,
                'usuario' => $importacion->user?->username,
                'leidas' => $importacion->filas_leidas,
                'importadas' => $importacion->filas_importadas,
                'rechazadas' => $importacion->filas_rechazadas,
                'estado' => $importacion->estado,
                'fecha' => $importacion->created_at?->format('d/m/Y H:i'),
                'resumen' => $importacion->resumen,
            ],
            'errores' => $importacion->errores()
                ->orderBy('fila')
                ->paginate(50)
                ->through(fn ($e) => [
                    'fila' => $e->fila,
                    'codigo' => $e->codigo,
                    'motivo_clave' => $e->motivo_clave,
                    'motivo' => $e->motivo,
                    'descripcion' => $e->datos['descripcion'] ?? null,
                ]),
            'resumenErrores' => $importacion->errores()
                ->selectRaw('motivo_clave, COUNT(*) as total')
                ->groupBy('motivo_clave')
                ->pluck('total', 'motivo_clave'),
        ]);
    }

    public function revertir(Importacion $importacion): RedirectResponse
    {
        if ($importacion->estado === 'revertida') {
            throw ValidationException::withMessages([
                'importacion' => 'Esta importación ya fue revertida.',
            ]);
        }

        $borrados = $this->importador->revertir($importacion);

        return back()->with('status', sprintf(
            'Se revirtió la importación: se eliminaron %d bien(es).',
            $borrados,
        ));
    }

    // ------------------------------------------------------------------ apoyo

    /**
     * @return array<string, mixed>
     */
    private function validarCarga(Request $request, bool $exigirUnidad): array
    {
        return $request->validate([
            'ruta' => ['required', 'string'],
            'nombre' => ['required', 'string'],
            'hoja' => ['required', 'string'],
            'tipo' => ['required', Rule::in(['listado', 'tarjeta'])],
            'fila_encabezado' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'mapeo' => ['required', 'array'],
            'mapeo.*' => ['nullable', 'string', 'regex:/^[A-Z]{1,3}$/'],
            'unidad_servicio_id' => [
                $exigirUnidad ? 'required' : 'nullable',
                'integer',
                Rule::exists('unidades_servicio', 'id'),
            ],
            'guardar_perfil' => ['nullable', 'string', 'max:120'],
        ], attributes: [
            'hoja' => 'hoja',
            'tipo' => 'tipo de archivo',
            'unidad_servicio_id' => 'unidad de servicio',
            'fila_encabezado' => 'fila de encabezado',
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function calcularVista(array $datos): array
    {
        $filas = (new LectorLibro($this->rutaSegura($datos['ruta'])))->filas($datos['hoja']);

        return $this->importador->previsualizar(
            $filas,
            $this->mapeoConIndices($datos['mapeo']),
            $datos['fila_encabezado'] ?? null,
            $datos['tipo'],
        );
    }

   
    private function rutaSegura(string $ruta): string
    {
        $normalizada = str_replace('\\', '/', $ruta);

        if (! str_starts_with($normalizada, self::CARPETA.'/')
            || str_contains($normalizada, '..')
            || ! Storage::exists($normalizada)
        ) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo ya no está disponible. Vuelva a subirlo.',
            ]);
        }

        return Storage::path($normalizada);
    }

    /**
     * En pantalla las columnas se manejan por su letra, que es como las ve la
     * persona en Excel; por dentro se usan indices.
     *
     * @param  array<string, int>  $mapeo
     * @return array<string, string>
     */
    private function mapeoConLetras(array $mapeo): array
    {
        return array_map(
            fn (int $indice) => Coordinate::stringFromColumnIndex($indice + 1),
            $mapeo,
        );
    }

    /**
     * @param  array<string, string|null>  $mapeo
     * @return array<string, int>
     */
    private function mapeoConIndices(array $mapeo): array
    {
        $indices = [];

        foreach ($mapeo as $campo => $letra) {
            if ($letra === null || $letra === '') {
                continue;
            }

            $indices[$campo] = Coordinate::columnIndexFromString($letra) - 1;
        }

        return $indices;
    }

    /**
     * @param  array<int, string>  $columnas
     * @return array<int, array{letra: string, ejemplo: string}>
     */
    private function columnasConLetras(array $columnas): array
    {
        $lista = [];

        foreach ($columnas as $indice => $ejemplo) {
            $lista[] = [
                'letra' => Coordinate::stringFromColumnIndex($indice + 1),
                'ejemplo' => $ejemplo,
            ];
        }

        return $lista;
    }

    /**
     * @return array<string, array{etiqueta: string, obligatorio: bool, ayuda: string}>
     */
    private function camposDisponibles(): array
    {
        return [
            'codigo' => ['etiqueta' => 'Código de inventario', 'obligatorio' => true, 'ayuda' => '0033C31E o 2026-211-CHI-0013'],
            'descripcion' => ['etiqueta' => 'Descripción', 'obligatorio' => true, 'ayuda' => 'El nombre del bien'],
            'cantidad' => ['etiqueta' => 'Cantidad', 'obligatorio' => false, 'ayuda' => 'Si no viene, se asume 1'],
            'precio_unitario' => ['etiqueta' => 'Precio unitario', 'obligatorio' => false, 'ayuda' => 'Se deduce del total si falta'],
            'total' => ['etiqueta' => 'Monto o total', 'obligatorio' => false, 'ayuda' => 'En las tarjetas es la columna DEBE'],
            'cuenta' => ['etiqueta' => 'Cuenta', 'obligatorio' => false, 'ayuda' => 'Trae la cuenta, COMPRA, DONACION o ADICION'],
            'fecha' => ['etiqueta' => 'Fecha', 'obligatorio' => false, 'ayuda' => 'Fecha completa o solo el año'],
            'observaciones' => ['etiqueta' => 'Observaciones', 'obligatorio' => false, 'ayuda' => ''],
        ];
    }
}
