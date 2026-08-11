<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\BienRequest;
use App\Models\AuditLog;
use App\Models\Bien;
use App\Models\Renglon;
use App\Models\UnidadServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BienController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);

        $bienes = Bien::query()
            ->with(['renglon:id,codigo,nombre', 'unidadServicio:id,codigo,nombre'])
            ->with(['asignacionVigente.empleado:id,nombre_completo'])
            ->when($filtros['buscar'] !== '', fn ($q) => $q->buscar($filtros['buscar']))
            ->when($filtros['unidad'] !== null, function ($q) use ($filtros) {
                // Incluye las unidades que dependen de la elegida, para poder
                // consultar un distrito completo y no solo un puesto.
                $unidad = UnidadServicio::find($filtros['unidad']);

                if ($unidad) {
                    $q->deUnidadConDescendientes($unidad);
                }
            })
            ->when($filtros['cuenta'] !== null, fn ($q) => $q->where('renglon_id', $filtros['cuenta']))
            ->when($filtros['estado'] !== '', fn ($q) => $q->where('estado', $filtros['estado']))
            ->when($filtros['movimiento'] !== '', fn ($q) => $q->where('tipo_movimiento', $filtros['movimiento']))
            ->when($filtros['sin_cuenta'], fn ($q) => $q->sinCuenta())
            ->when($filtros['sin_asignar'], fn ($q) => $q->sinAsignar())
            ->when($filtros['provisionales'], fn ($q) => $q->conCodigoProvisional())
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Bien $bien) => [
                'id' => $bien->id,
                'codigo' => $bien->codigo,
                'codigo_provisional' => $bien->codigo_provisional,
                'descripcion' => $bien->descripcion,
                'cantidad' => $bien->cantidad,
                'precio_unitario' => (float) $bien->precio_unitario,
                'total' => (float) $bien->total,
                'cuenta' => $bien->renglon?->codigo,
                'unidad' => $bien->unidadServicio?->nombre,
                'estado' => $bien->estado,
                'estado_legible' => $bien->estadoLegible(),
                'es_adicion' => $bien->esAdicion(),
                'responsable' => $bien->asignacionVigente?->empleado?->nombre_completo,
                'anio' => $bien->anio_ingreso,
            ]);

        return Inertia::render('inventario/bienes/index', [
            'bienes' => $bienes,
            'filtros' => $filtros,
            'catalogos' => $this->catalogos(),
            'resumen' => $this->resumen($filtros),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inventario/bienes/formulario', [
            'bien' => null,
            'catalogos' => $this->catalogos(),
            'programasUsados' => $this->programasUsados(),
        ]);
    }

    public function store(BienRequest $request): RedirectResponse
    {
        $bien = Bien::create($this->datosDelFormulario($request));

        AuditLog::registrar(
            evento: 'bien.creado',
            descripcion: sprintf('Se registró el bien %s: %s', $bien->codigo, mb_substr($bien->descripcion, 0, 60)),
            modelo: $bien,
        );

        return to_route('inventario.bienes.index')
            ->with('status', sprintf('Bien %s registrado.', $bien->codigo));
    }

    public function show(Bien $bien): Response
    {
        $bien->load([
            'renglon',
            'unidadServicio',
            'importacion:id,archivo,created_at',
            'asignaciones.empleado:id,nombre_completo,cargo',
            'bajas.solicitadaPor:id,username',
            'bajas.resueltaPor:id,username',
        ]);

        return Inertia::render('inventario/bienes/detalle', [
            'bien' => [
                'id' => $bien->id,
                'codigo' => $bien->codigo,
                'descripcion' => $bien->descripcion,
                'cantidad' => $bien->cantidad,
                'precio_unitario' => (float) $bien->precio_unitario,
                'total' => (float) $bien->total,
                'cuenta' => $bien->renglon ? $bien->renglon->etiquetaCompleta() : null,
                'cuenta_texto_original' => $bien->cuenta_texto_original,
                'lineas_columna_cuenta' => $bien->lineasColumnaCuenta(),
                'unidad' => $bien->unidadServicio?->nombre,
                'tipo_movimiento' => Bien::MOVIMIENTOS[$bien->tipo_movimiento] ?? $bien->tipo_movimiento,
                'forma_adquisicion' => $bien->forma_adquisicion
                    ? (Bien::FORMAS_ADQUISICION[$bien->forma_adquisicion] ?? $bien->forma_adquisicion)
                    : null,
                'programa' => $bien->programa,
                'documento_respaldo' => $bien->documento_respaldo,
                'fecha_texto_original' => $bien->fecha_texto_original,
                'fecha_ingreso' => $bien->fecha_ingreso?->format('d/m/Y'),
                'anio_ingreso' => $bien->anio_ingreso,
                'estado' => $bien->estado,
                'estado_legible' => $bien->estadoLegible(),
                'observaciones' => $bien->observaciones,
                'origen_importacion' => $bien->importacion?->archivo,
            ],
            'asignaciones' => $bien->asignaciones->map(fn ($a) => [
                'id' => $a->id,
                'empleado' => $a->empleado?->nombre_completo,
                'cargo' => $a->empleado?->cargo,
                'desde' => $a->fecha_asignacion?->format('d/m/Y'),
                'hasta' => $a->fecha_devolucion?->format('d/m/Y'),
                'activa' => $a->activa,
                'motivo_cierre' => $a->motivoCierreLegible(),
            ]),
            'bajas' => $bien->bajas->map(fn ($b) => [
                'id' => $b->id,
                'numero_acta' => $b->numero_acta,
                'motivo' => $b->motivo,
                'estado' => $b->estadoLegible(),
                'fecha_solicitud' => $b->fecha_solicitud?->format('d/m/Y'),
                'fecha_resolucion' => $b->fecha_resolucion?->format('d/m/Y'),
                'solicitada_por' => $b->solicitadaPor?->username,
                'resuelta_por' => $b->resueltaPor?->username,
            ]),
        ]);
    }

    public function edit(Bien $bien): Response
    {
        return Inertia::render('inventario/bienes/formulario', [
            'bien' => [
                'id' => $bien->id,
                'codigo' => $bien->codigo,
                'descripcion' => $bien->descripcion,
                'cantidad' => $bien->cantidad,
                'precio_unitario' => (float) $bien->precio_unitario,
                'unidad_servicio_id' => $bien->unidad_servicio_id,
                'renglon_id' => $bien->renglon_id,
                'tipo_movimiento' => $bien->tipo_movimiento,
                'forma_adquisicion' => $bien->forma_adquisicion,
                'programa' => $bien->programa,
                'documento_respaldo' => $bien->documento_respaldo,
                'fecha_ingreso' => $bien->fecha_ingreso?->format('Y-m-d'),
                'anio_ingreso' => $bien->anio_ingreso,
                'observaciones' => $bien->observaciones,
                'cuenta_texto_original' => $bien->cuenta_texto_original,
                'fecha_texto_original' => $bien->fecha_texto_original,
            ],
            'catalogos' => $this->catalogos(),
            'programasUsados' => $this->programasUsados(),
        ]);
    }

    public function update(BienRequest $request, Bien $bien): RedirectResponse
    {
        $anterior = $bien->only(['codigo', 'descripcion', 'precio_unitario', 'renglon_id']);

        $datos = $this->datosDelFormulario($request);

        // Si el bien tenia un codigo puesto por el sistema y ahora se le asigna
        // otro, deja de estar pendiente de correccion.
        if ($bien->codigo_provisional && $datos['codigo'] !== $bien->codigo) {
            $datos['codigo_provisional'] = false;
        }

        $bien->update($datos);

        AuditLog::registrar(
            evento: 'bien.actualizado',
            descripcion: sprintf('Se editó el bien %s', $bien->codigo),
            modelo: $bien,
            datos: ['antes' => $anterior, 'despues' => $bien->only(array_keys($anterior))],
        );

        return to_route('inventario.bienes.index')->with('status', 'Bien actualizado.');
    }

  
    public function asignarCuenta(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'bienes' => ['required', 'array', 'min:1'],
            'bienes.*' => ['integer', Rule::exists('bienes', 'id')],
            'renglon_id' => ['required', 'integer', Rule::exists('renglones', 'id')],
        ], attributes: [
            'bienes' => 'bienes',
            'renglon_id' => 'cuenta',
        ]);

        $renglon = Renglon::findOrFail($datos['renglon_id']);

        $afectados = DB::transaction(
            fn () => Bien::whereIn('id', $datos['bienes'])->update(['renglon_id' => $renglon->id])
        );

        AuditLog::registrar(
            evento: 'bien.cuenta_asignada',
            descripcion: sprintf('Se asignó la cuenta %s a %d bien(es)', $renglon->codigo, $afectados),
            modelo: $renglon,
            datos: ['bienes' => $datos['bienes']],
        );

        return back()->with('status', sprintf(
            'Se asignó la cuenta %s a %d bien(es).',
            $renglon->codigo,
            $afectados,
        ));
    }

    public function destroy(Bien $bien): RedirectResponse
    {
        if ($bien->asignacionVigente()->exists()) {
            throw ValidationException::withMessages([
                'bien' => sprintf(
                    'El bien %s está asignado a %s. Cierre la asignación antes de eliminarlo.',
                    $bien->codigo,
                    $bien->asignacionVigente->empleado?->nombre_completo ?? 'un empleado',
                ),
            ]);
        }

        AuditLog::registrar(
            evento: 'bien.eliminado',
            descripcion: sprintf('Se eliminó el bien %s: %s', $bien->codigo, mb_substr($bien->descripcion, 0, 60)),
            modelo: $bien,
        );

        // Borrado logico: el bien deja de aparecer pero su rastro queda en la
        // bitacora y en el historial de asignaciones.
        $bien->delete();

        return to_route('inventario.bienes.index')->with('status', 'Bien eliminado.');
    }

    // ------------------------------------------------------------------ apoyo

    /**
     * @return array{buscar: string, unidad: int|null, cuenta: int|null, estado: string, movimiento: string, sin_cuenta: bool, sin_asignar: bool}
     */
    private function filtros(Request $request): array
    {
        return [
            'buscar' => trim((string) $request->string('buscar')),
            'unidad' => $request->integer('unidad') ?: null,
            'cuenta' => $request->integer('cuenta') ?: null,
            'estado' => (string) $request->string('estado'),
            'movimiento' => (string) $request->string('movimiento'),
            'sin_cuenta' => $request->boolean('sin_cuenta'),
            'sin_asignar' => $request->boolean('sin_asignar'),
            'provisionales' => $request->boolean('provisionales'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'unidades' => UnidadServicio::query()
                ->activas()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'tipo'])
                ->map(fn (UnidadServicio $u) => [
                    'id' => $u->id,
                    'nombre' => $u->nombre,
                    'codigo' => $u->codigo,
                ]),
            'cuentas' => Renglon::query()
                ->activos()
                ->ordenados()
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Renglon $r) => [
                    'id' => $r->id,
                    'codigo' => $r->codigo,
                    'nombre' => $r->nombre,
                ]),
            'estados' => Bien::ESTADOS,
            'movimientos' => Bien::MOVIMIENTOS,
            'formas' => Bien::FORMAS_ADQUISICION,
        ];
    }

    /**
     * Totales del filtro activo. Son los numeros que pide un reporte: cuantos
     * bienes y por cuanto dinero.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function resumen(array $filtros): array
    {
        $base = Bien::query()
            ->when($filtros['buscar'] !== '', fn ($q) => $q->buscar($filtros['buscar']))
            ->when($filtros['unidad'] !== null, function ($q) use ($filtros) {
                $unidad = UnidadServicio::find($filtros['unidad']);
                if ($unidad) {
                    $q->deUnidadConDescendientes($unidad);
                }
            })
            ->when($filtros['cuenta'] !== null, fn ($q) => $q->where('renglon_id', $filtros['cuenta']))
            ->when($filtros['estado'] !== '', fn ($q) => $q->where('estado', $filtros['estado']))
            ->when($filtros['movimiento'] !== '', fn ($q) => $q->where('tipo_movimiento', $filtros['movimiento']))
            ->when($filtros['sin_cuenta'], fn ($q) => $q->sinCuenta())
            ->when($filtros['sin_asignar'], fn ($q) => $q->sinAsignar());

        return [
            'bienes' => (clone $base)->count(),
            'valor' => (float) (clone $base)->sum('total'),
            'sin_cuenta' => Bien::query()->sinCuenta()->count(),
            'sin_asignar' => Bien::query()->activos()->sinAsignar()->count(),
            'provisionales' => Bien::query()->conCodigoProvisional()->count(),
        ];
    }

    /**
     * Programas ya capturados, para sugerirlos en el formulario y evitar que el
     * mismo programa se escriba de varias formas distintas.
     *
     * @return array<int, string>
     */
    private function programasUsados(): array
    {
        return Bien::query()
            ->whereNotNull('programa')
            ->where('programa', '!=', '')
            ->distinct()
            ->orderBy('programa')
            ->pluck('programa')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDelFormulario(BienRequest $request): array
    {
        $datos = $request->validated();

        // El total siempre se calcula: no se captura a mano para que no pueda
        // quedar descuadrado con la cantidad y el precio.
        $datos['total'] = round($datos['cantidad'] * $datos['precio_unitario'], 2);

        // El programa solo tiene sentido en una donacion.
        if (($datos['forma_adquisicion'] ?? null) !== 'donacion') {
            $datos['programa'] = null;
        }

        // Si se indico una fecha completa, el anio se toma de ahi.
        if (! empty($datos['fecha_ingreso'])) {
            $datos['anio_ingreso'] = (int) date('Y', strtotime($datos['fecha_ingreso']));
        }

        return $datos;
    }
}
