<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $permisos = Permission::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permiso) => [
                'id' => $permiso->id,
                'name' => $permiso->name,
                'modulo' => str_contains($permiso->name, '.') ? explode('.', $permiso->name)[0] : 'general',
                'roles' => $permiso->roles->pluck('name')->all(),
            ])
            ->groupBy('modulo');

        return Inertia::render('admin/permisos/index', [
            'permisosPorModulo' => $permisos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $permiso = Permission::create(['name' => $datos['name'], 'guard_name' => 'web']);

        AuditLog::registrar(
            evento: 'permiso.creado',
            descripcion: sprintf('Se creó el permiso %s', $permiso->name),
            modelo: $permiso,
        );

        return back()->with('status', sprintf('Permiso %s creado.', $permiso->name));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $datos = $this->validar($request, $permission);

        $anterior = $permission->name;
        $permission->update(['name' => $datos['name']]);

        AuditLog::registrar(
            evento: 'permiso.actualizado',
            descripcion: sprintf('El permiso %s pasó a llamarse %s', $anterior, $permission->name),
            modelo: $permission,
        );

        return back()->with('status', 'Permiso actualizado.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->roles()->exists()) {
            throw ValidationException::withMessages([
                'permiso' => 'No se puede eliminar un permiso que todavía está asignado a algún rol.',
            ]);
        }

        AuditLog::registrar(
            evento: 'permiso.eliminado',
            descripcion: sprintf('Se eliminó el permiso %s', $permission->name),
            modelo: $permission,
        );

        $permission->delete();

        return back()->with('status', 'Permiso eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            // Se exige el formato "modulo.accion" para que la pantalla de
            // asignacion pueda seguir agrupando los permisos por modulo.
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z_]+\.[a-z_]+$/',
                Rule::unique('permissions', 'name')->ignore($permission?->id),
            ],
        ], [
            'name.regex' => 'El permiso debe escribirse como modulo.accion, por ejemplo: bienes.crear',
        ], [
            'name' => 'nombre del permiso',
        ]);
    }
}
