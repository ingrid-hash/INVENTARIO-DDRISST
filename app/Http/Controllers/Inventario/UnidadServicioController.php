<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UnidadServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;


class UnidadServicioController extends Controller
{
    public function index(): Response
    {
        $unidades = UnidadServicio::query()
            ->with('padre:id,nombre,codigo')
            ->withCount(['bienes', 'empleados'])
            ->orderBy('codigo')
            ->get()
            ->map(fn (UnidadServicio $unidad) => [
                'id' => $unidad->id,
                'padre_id' => $unidad->padre_id,
                'padre' => $unidad->padre?->nombre,
                'codigo' => $unidad->codigo,
                'nombre' => $unidad->nombre,
                'tipo' => $unidad->tipo,
                'tipo_legible' => $unidad->tipoLegible(),
                'municipio' => $unidad->municipio,
                'departamento' => $unidad->departamento,
                'activo' => $unidad->activo,
                'bienes' => $unidad->bienes_count,
                'empleados' => $unidad->empleados_count,
            ]);

        return Inertia::render('inventario/unidades/index', [
            'unidades' => $unidades,
            'tipos' => UnidadServicio::TIPOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $unidad = UnidadServicio::create($datos);

        AuditLog::registrar(
            evento: 'unidad.creada',
            descripcion: sprintf('Se creó la unidad %s (%s)', $unidad->nombre, $unidad->codigo),
            modelo: $unidad,
        );

        return back()->with('status', sprintf('Unidad %s creada.', $unidad->nombre));
    }

    public function update(Request $request, UnidadServicio $unidad): RedirectResponse
    {
        $datos = $this->validar($request, $unidad);

        // Una unidad no puede depender de si misma ni de una de sus propias
        // descendientes: eso crearia un ciclo en la jerarquia.
        if (! empty($datos['padre_id']) && in_array((int) $datos['padre_id'], $unidad->idsConDescendientes(), true)) {
            throw ValidationException::withMessages([
                'padre_id' => 'Una unidad no puede depender de sí misma ni de una unidad que está por debajo de ella.',
            ]);
        }

        $unidad->update($datos);

        AuditLog::registrar(
            evento: 'unidad.actualizada',
            descripcion: sprintf('Se actualizó la unidad %s (%s)', $unidad->nombre, $unidad->codigo),
            modelo: $unidad,
        );

        return back()->with('status', 'Unidad actualizada.');
    }

    public function destroy(UnidadServicio $unidad): RedirectResponse
    {
        if ($unidad->bienes()->exists() || $unidad->empleados()->exists()) {
            throw ValidationException::withMessages([
                'unidad' => 'No se puede eliminar una unidad que tiene bienes o empleados. Desactívela en lugar de eliminarla.',
            ]);
        }

        if ($unidad->hijas()->exists()) {
            throw ValidationException::withMessages([
                'unidad' => 'No se puede eliminar una unidad que tiene otras unidades por debajo.',
            ]);
        }

        AuditLog::registrar(
            evento: 'unidad.eliminada',
            descripcion: sprintf('Se eliminó la unidad %s (%s)', $unidad->nombre, $unidad->codigo),
            modelo: $unidad,
        );

        $unidad->delete();

        return back()->with('status', 'Unidad eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?UnidadServicio $unidad = null): array
    {
        return $request->validate([
            // Codigo institucional corto: 211, 211-MOM, 211-CHI
            'codigo' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9A-Z\-]+$/',
                Rule::unique('unidades_servicio', 'codigo')->ignore($unidad?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(array_keys(UnidadServicio::TIPOS))],
            'padre_id' => ['nullable', 'integer', Rule::exists('unidades_servicio', 'id')],
            'municipio' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ], [
            'codigo.regex' => 'El código solo admite números, letras mayúsculas y guiones. Por ejemplo: 211-CHI',
        ], [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'tipo' => 'tipo de unidad',
            'padre_id' => 'unidad superior',
        ]);
    }
}
