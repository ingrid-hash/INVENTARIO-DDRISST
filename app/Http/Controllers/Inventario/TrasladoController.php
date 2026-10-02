<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\Bien;
use App\Models\Empleado;
use App\Services\TarjetaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Traslado de un bien de una persona a otra.
 *
 * Para quien lo recibe es una adicion mas en su tarjeta. Para quien lo tenia, el
 * bien sigue escrito en su hoja firmada hasta que esa tarjeta se regenere.
 */
class TrasladoController extends Controller
{
    public function __construct(private readonly TarjetaService $tarjetas) {}

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->string('bien'));

        return Inertia::render('inventario/traslados/index', [
            'buscar' => $buscar,
            'candidatos' => $buscar === '' ? [] : $this->candidatos($buscar),
            'empleados' => Empleado::query()
                ->whereHas('tarjetaVigente')
                ->orderBy('nombre_completo')
                ->get(['id', 'nombre_completo', 'cargo'])
                ->all(),
            'recientes' => $this->recientes(),
        ]);
    }

    /**
     * Bienes que se pueden trasladar: los activos, esten o no a cargo de
     * alguien.
     *
     * @return array<int, array<string, mixed>>
     */
    private function candidatos(string $termino): array
    {
        return Bien::query()
            ->with(['unidadServicio:id,nombre', 'asignacionVigente.empleado:id,nombre_completo'])
            ->where('estado', Bien::ESTADO_ACTIVO)
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

    /**
     * Los ultimos traslados: son las custodias que se cerraron por esa razon.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recientes(): array
    {
        return Asignacion::query()
            ->with(['bien:id,codigo,descripcion', 'empleado:id,nombre_completo'])
            ->where('motivo_cierre', 'traslado')
            ->latest('fecha_devolucion')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (Asignacion $a) => [
                'id' => $a->id,
                'codigo' => $a->bien?->codigo,
                'descripcion' => $a->bien?->descripcion,
                'de' => $a->empleado?->nombre_completo,
                'a' => $a->bien?->asignacionVigente?->empleado?->nombre_completo,
                'fecha' => $a->fecha_devolucion?->format('d/m/Y'),
                'observaciones' => $a->observaciones,
            ])
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'bien_id' => ['required', 'integer', Rule::exists('bienes', 'id')],
            'empleado_id' => ['required', 'integer', Rule::exists('empleados', 'id')],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'bien_id' => 'bien',
            'empleado_id' => 'empleado',
        ]);

        $bien = Bien::findOrFail($datos['bien_id']);
        $destino = Empleado::findOrFail($datos['empleado_id']);

        $this->tarjetas->trasladar($bien, $destino, $datos['observaciones'] ?? null);

        return back()->with('status', sprintf(
            'El bien %s pasó a la tarjeta de %s. Imprima lo nuevo en esa tarjeta, y regenere la '
            .'anterior para que el bien salga de la hoja de quien lo tenía.',
            $bien->codigo,
            $destino->nombre_completo,
        ));
    }
}
