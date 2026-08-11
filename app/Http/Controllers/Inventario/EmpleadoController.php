<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Empleado;
use App\Models\UnidadServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmpleadoController extends Controller
{
    
    private const UMBRAL_PARECIDO = 82;

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->string('buscar'));
        $unidad = $request->integer('unidad') ?: null;

        $empleados = Empleado::query()
            ->with('unidadServicio:id,codigo,nombre')
            ->withCount(['asignacionesVigentes as bienes_a_cargo'])
            ->with('tarjetaVigente:id,empleado_id,numero,version,saldo_total')
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre_completo', 'ilike', "%{$buscar}%")
                        ->orWhere('cargo', 'ilike', "%{$buscar}%")
                        ->orWhere('dpi', 'ilike', "{$buscar}%");
                });
            })
            ->when($unidad !== null, fn ($q) => $q->where('unidad_servicio_id', $unidad))
            ->orderBy('nombre_completo')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Empleado $empleado) => [
                'id' => $empleado->id,
                'nombre_completo' => $empleado->nombre_completo,
                'dpi' => $empleado->dpi,
                'cargo' => $empleado->cargo,
                'area_trabajo' => $empleado->area_trabajo,
                'unidad' => $empleado->unidadServicio?->nombre,
                'unidad_servicio_id' => $empleado->unidad_servicio_id,
                'activo' => $empleado->activo,
                'bienes_a_cargo' => $empleado->bienes_a_cargo,
                'tarjeta' => $empleado->tarjetaVigente ? [
                    'id' => $empleado->tarjetaVigente->id,
                    'numero' => $empleado->tarjetaVigente->numero,
                    'version' => $empleado->tarjetaVigente->version,
                    'saldo' => (float) $empleado->tarjetaVigente->saldo_total,
                ] : null,
            ]);

        return Inertia::render('inventario/empleados/index', [
            'empleados' => $empleados,
            'filtros' => ['buscar' => $buscar, 'unidad' => $unidad],
            'unidades' => UnidadServicio::activas()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

       
        if (! $request->boolean('confirmar_parecido')) {
            $parecido = $this->buscarParecido($datos['nombre_completo'], (int) $datos['unidad_servicio_id']);

            if ($parecido !== null) {
                throw ValidationException::withMessages([
                    'nombre_completo' => sprintf(
                        'Ya existe "%s" en esta unidad, con un %d%% de parecido. Si es otra persona, marque la casilla para confirmarlo.',
                        $parecido['nombre'],
                        $parecido['similitud'],
                    ),
                ]);
            }
        }

        $empleado = Empleado::create($datos);

        AuditLog::registrar(
            evento: 'empleado.creado',
            descripcion: sprintf('Se registró al empleado %s', $empleado->nombre_completo),
            modelo: $empleado,
        );

        return back()->with('status', sprintf('%s registrado.', $empleado->nombre_completo));
    }

    public function update(Request $request, Empleado $empleado): RedirectResponse
    {
        $datos = $this->validar($request, $empleado);

        $nombreAnterior = $empleado->nombre_completo;
        $empleado->update($datos);

        AuditLog::registrar(
            evento: 'empleado.actualizado',
            descripcion: $nombreAnterior === $empleado->nombre_completo
                ? sprintf('Se actualizó al empleado %s', $empleado->nombre_completo)
                : sprintf('El empleado "%s" pasó a "%s"', $nombreAnterior, $empleado->nombre_completo),
            modelo: $empleado,
        );

        return back()->with('status', 'Empleado actualizado.');
    }

    public function destroy(Empleado $empleado): RedirectResponse
    {
        if ($empleado->asignacionesVigentes()->exists()) {
            throw ValidationException::withMessages([
                'empleado' => sprintf(
                    '%s tiene %d bien(es) a su cargo. Traslade esos bienes antes de eliminar el registro.',
                    $empleado->nombre_completo,
                    $empleado->asignacionesVigentes()->count(),
                ),
            ]);
        }

        AuditLog::registrar(
            evento: 'empleado.eliminado',
            descripcion: sprintf('Se eliminó al empleado %s', $empleado->nombre_completo),
            modelo: $empleado,
        );

        $empleado->delete();

        return back()->with('status', 'Empleado eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Empleado $empleado = null): array
    {
        return $request->validate([
            'unidad_servicio_id' => ['required', 'integer', Rule::exists('unidades_servicio', 'id')],
            'nombre_completo' => ['required', 'string', 'max:255'],
            'dpi' => [
                'nullable',
                'string',
                'digits:13',
                Rule::unique('empleados', 'dpi')->ignore($empleado?->id)->whereNull('deleted_at'),
            ],
            'cargo' => ['nullable', 'string', 'max:255'],
            'area_trabajo' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ], attributes: [
            'unidad_servicio_id' => 'unidad de servicio',
            'nombre_completo' => 'nombre completo',
            'dpi' => 'DPI',
            'area_trabajo' => 'área de trabajo',
        ]);
    }

    /**
     * Busca un empleado de la misma unidad cuyo nombre se parezca lo bastante
     * como para sospechar que es la misma persona.
     *
     * @return array{nombre: string, similitud: int}|null
     */
    private function buscarParecido(string $nombre, int $unidadId): ?array
    {
        $normalizar = function (string $texto): string {
            $texto = mb_strtoupper(trim($texto));

            $texto = preg_replace('/^(DOCTOR|DOCTORA|DR|DRA|LIC|LICDA|ING|MSC|EP)\.?\s+/u', '', $texto) ?? $texto;

            return preg_replace('/\s+/', ' ', $texto) ?? $texto;
        };

        $candidato = $normalizar($nombre);

        $mejor = null;

        foreach (Empleado::where('unidad_servicio_id', $unidadId)->pluck('nombre_completo') as $existente) {
            similar_text($candidato, $normalizar($existente), $porcentaje);

            if ($porcentaje >= self::UMBRAL_PARECIDO && ($mejor === null || $porcentaje > $mejor['similitud'])) {
                $mejor = ['nombre' => $existente, 'similitud' => (int) round($porcentaje)];
            }
        }

        return $mejor;
    }
}
