<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Baja;
use App\Models\Bien;
use App\Services\BajaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BajaController extends Controller
{
    public function __construct(private readonly BajaService $bajas) {}

    /**
     * Las bajas, con las que estan en tramite arriba: son las que esperan que
     * alguien las resuelva.
     */
    public function index(Request $request): Response
    {
        $estado = $request->string('estado')->toString();
        $buscar = trim((string) $request->string('buscar'));

        $bajas = Baja::query()
            ->with(['bien:id,codigo,descripcion,total,unidad_servicio_id', 'bien.unidadServicio:id,nombre',
                'bien.asignacionVigente.empleado:id,nombre_completo',
                'solicitadaPor:id,name', 'resueltaPor:id,name'])
            ->when($estado !== '' && $estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->when($buscar !== '', fn ($q) => $q->where(function ($sub) use ($buscar) {
                $sub->where('numero_acta', 'ilike', $buscar.'%')
                    ->orWhere('motivo', 'ilike', '%'.$buscar.'%')
                    ->orWhereHas('bien', fn ($b) => $b
                        ->where('codigo', 'ilike', $buscar.'%')
                        ->orWhere('descripcion', 'ilike', '%'.$buscar.'%'));
            }))
            // Primero lo que espera resolucion, y dentro de eso lo mas reciente.
            ->orderByRaw("CASE WHEN estado = 'solicitada' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Baja $baja) => [
                'id' => $baja->id,
                'estado' => $baja->estado,
                'estado_legible' => $baja->estadoLegible(),
                'numero_acta' => $baja->numero_acta,
                'motivo' => $baja->motivo,
                'observaciones' => $baja->observaciones,
                'fecha_solicitud' => $baja->fecha_solicitud?->format('d/m/Y'),
                'fecha_resolucion' => $baja->fecha_resolucion?->format('d/m/Y'),
                'solicitada_por' => $baja->solicitadaPor?->name,
                'resuelta_por' => $baja->resueltaPor?->name,
                'bien' => [
                    'id' => $baja->bien?->id,
                    'codigo' => $baja->bien?->codigo,
                    'descripcion' => $baja->bien?->descripcion,
                    'total' => (float) ($baja->bien?->total ?? 0),
                    'unidad' => $baja->bien?->unidadServicio?->nombre,
                    'responsable' => $baja->bien?->asignacionVigente?->empleado?->nombre_completo,
                ],
            ]);

        return Inertia::render('inventario/bajas/index', [
            'bajas' => $bajas,
            'filtros' => ['estado' => $estado ?: 'todas', 'buscar' => $buscar],
            'estados' => Baja::ESTADOS,
            'resumen' => [
                'en_tramite' => Baja::enTramite()->count(),
                'autorizadas' => Baja::autorizadas()->count(),
                'valor_dado_de_baja' => (float) Bien::where('estado', Bien::ESTADO_BAJA)->sum('total'),
            ],
            'candidatos' => $this->candidatos($request),
            'buscar_bien' => trim((string) $request->string('bien')),
        ]);
    }

    /**
     * Bienes que se pueden dar de baja: los que estan activos y todavia no
     * tienen un expediente abierto.
     *
     * @return array<int, array<string, mixed>>
     */
    private function candidatos(Request $request): array
    {
        $termino = trim((string) $request->string('bien'));

        if ($termino === '') {
            return [];
        }

        return Bien::query()
            ->with(['unidadServicio:id,nombre', 'asignacionVigente.empleado:id,nombre_completo'])
            ->where('estado', Bien::ESTADO_ACTIVO)
            ->whereDoesntHave('bajaEnTramite')
            ->buscar($termino)
            ->orderBy('codigo')
            ->limit(25)
            ->get()
            ->map(fn (Bien $bien) => [
                'id' => $bien->id,
                'codigo' => $bien->codigo,
                'descripcion' => $bien->descripcion,
                'total' => (float) $bien->total,
                'unidad' => $bien->unidadServicio?->nombre,
                'responsable' => $bien->asignacionVigente?->empleado?->nombre_completo,
            ])
            ->all();
    }

    /** Abre el expediente de baja de un bien. */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'bien_id' => ['required', 'integer', Rule::exists('bienes', 'id')],
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
            'numero_acta' => ['nullable', 'string', 'max:60'],
            'fecha_solicitud' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'bien_id' => 'bien',
            'numero_acta' => 'número de acta',
            'fecha_solicitud' => 'fecha de solicitud',
        ]);

        $bien = Bien::findOrFail($datos['bien_id']);
        $baja = $this->bajas->solicitar($bien, $datos);

        return back()->with('status', sprintf(
            'Se solicitó la baja del bien %s. El bien sigue en la tarjeta hasta que se autorice.',
            $baja->bien->codigo,
        ));
    }

    /** Autoriza la baja: el bien sale de la tarjeta. */
    public function autorizar(Request $request, Baja $baja): RedirectResponse
    {
        $datos = $request->validate([
            'numero_acta' => ['nullable', 'string', 'max:60'],
            'fecha_resolucion' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'numero_acta' => 'número de acta',
            'fecha_resolucion' => 'fecha de resolución',
        ]);

        $this->bajas->autorizar($baja, $datos);

        return back()->with('status', sprintf(
            'Baja autorizada. El bien %s salió de la tarjeta y ya no suma al saldo.',
            $baja->bien->codigo,
        ));
    }

    /** Rechaza la baja: el bien vuelve a estar activo. */
    public function rechazar(Request $request, Baja $baja): RedirectResponse
    {
        $datos = $request->validate([
            'observaciones' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: ['observaciones' => 'razón del rechazo']);

        $this->bajas->rechazar($baja, $datos['observaciones']);

        return back()->with('status', sprintf(
            'Baja rechazada. El bien %s sigue activo en su tarjeta.',
            $baja->bien->codigo,
        ));
    }
}
