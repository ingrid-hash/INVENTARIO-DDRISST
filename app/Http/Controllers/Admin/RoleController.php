<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Roles que el sistema necesita para funcionar y que por lo tanto no se
     * pueden renombrar ni eliminar.
     */
    private const ROLES_PROTEGIDOS = ['Superadmin', 'Admin', 'Usuario'];

    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $rol) => [
                'id' => $rol->id,
                'name' => $rol->name,
                'permisos' => $rol->permissions_count,
                'usuarios' => $rol->users_count,
                'protegido' => in_array($rol->name, self::ROLES_PROTEGIDOS, true),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/formulario', [
            'rol' => null,
            'permisos' => $this->permisosAgrupados(),
            'asignados' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $rol = Role::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $rol->syncPermissions($datos['permisos'] ?? []);

        AuditLog::registrar(
            evento: 'rol.creado',
            descripcion: sprintf('Se creó el rol %s con %d permiso(s)', $rol->name, count($datos['permisos'] ?? [])),
            modelo: $rol,
            datos: ['permisos' => $datos['permisos'] ?? []],
        );

        return to_route('admin.roles.index')->with('status', sprintf('Rol %s creado.', $rol->name));
    }

    public function edit(Role $role): Response
    {
        $this->autorizarSuperadmin($role);

        return Inertia::render('admin/roles/formulario', [
            'rol' => [
                'id' => $role->id,
                'name' => $role->name,
                'protegido' => in_array($role->name, self::ROLES_PROTEGIDOS, true),
            ],
            'permisos' => $this->permisosAgrupados(),
            'asignados' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->autorizarSuperadmin($role);

        $datos = $this->validar($request, $role);

        // Los roles base conservan su nombre: hay codigo y permisos que
        // dependen de el. Solo se les puede cambiar la lista de permisos.
        if (! in_array($role->name, self::ROLES_PROTEGIDOS, true)) {
            $role->update(['name' => $datos['name']]);
        }

        $role->syncPermissions($datos['permisos'] ?? []);

        AuditLog::registrar(
            evento: 'rol.permisos_actualizados',
            descripcion: sprintf('Se actualizaron los permisos del rol %s', $role->name),
            modelo: $role,
            datos: ['permisos' => $datos['permisos'] ?? []],
        );

        return to_route('admin.roles.index')->with('status', sprintf('Permisos del rol %s actualizados.', $role->name));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::ROLES_PROTEGIDOS, true)) {
            throw ValidationException::withMessages([
                'rol' => sprintf('El rol %s es parte del sistema y no puede eliminarse.', $role->name),
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'rol' => 'No se puede eliminar un rol que todavía tiene usuarios asignados.',
            ]);
        }

        AuditLog::registrar(
            evento: 'rol.eliminado',
            descripcion: sprintf('Se eliminó el rol %s', $role->name),
            modelo: $role,
        );

        $role->delete();

        return to_route('admin.roles.index')->with('status', 'Rol eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'permisos' => ['array'],
            'permisos.*' => ['string', Rule::exists('permissions', 'name')],
        ], attributes: [
            'name' => 'nombre del rol',
            'permisos' => 'permisos',
        ]);
    }

    private function autorizarSuperadmin(Role $role): void
    {
        if ($role->name === 'Superadmin' && ! Auth::user()->esSuperadmin()) {
            abort(403, 'Solo un Superadmin puede modificar el rol Superadmin.');
        }
    }

    /**
     * Los permisos se nombran "modulo.accion", asi que se agrupan por modulo
     * para poder mostrarlos ordenados en la pantalla de asignacion.
     *
     * @return array<string, array<int, string>>
     */
    private function permisosAgrupados(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $permiso) => str_contains($permiso, '.') ? explode('.', $permiso)[0] : 'general')
            ->map(fn ($grupo) => $grupo->values()->all())
            ->all();
    }
}
