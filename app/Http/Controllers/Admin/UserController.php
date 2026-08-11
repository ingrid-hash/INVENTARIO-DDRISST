<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PasswordService;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarios = User::query()
            ->with('roles:id,name')
            ->when($request->string('buscar')->toString(), function ($query, string $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('name', 'ilike', "%{$buscar}%")
                        ->orWhere('username', 'ilike', "%{$buscar}%")
                        ->orWhere('email', 'ilike', "%{$buscar}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $usuario) => [
                'id' => $usuario->id,
                'username' => $usuario->username,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'unidad' => $usuario->unidad,
                'activo' => $usuario->activo,
                'bloqueado' => $usuario->estaBloqueado(),
                'debe_cambiar_password' => $usuario->debe_cambiar_password,
                'ultimo_acceso_en' => $usuario->ultimo_acceso_en?->format('d/m/Y H:i'),
                'rol' => $usuario->roles->first()?->name,
            ]);

        return Inertia::render('admin/usuarios/index', [
            'usuarios' => $usuarios,
            'filtros' => ['buscar' => $request->string('buscar')->toString()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/usuarios/formulario', [
            'usuario' => null,
            'roles' => $this->rolesAsignables(),
            'passwordSugerida' => PasswordPolicy::generarTemporal(),
        ]);
    }

    public function store(StoreUserRequest $request, PasswordService $passwords): RedirectResponse
    {
        $datos = $request->validated();

        $this->autorizarRol($datos['rol']);

        $usuario = User::create([
            'username' => $datos['username'],
            'name' => $datos['name'],
            'email' => $datos['email'],
            'dpi' => $datos['dpi'] ?? null,
            'puesto' => $datos['puesto'] ?? null,
            'unidad' => $datos['unidad'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'password' => $datos['password'],
            'activo' => true,
        ]);

        $usuario->syncRoles([$datos['rol']]);

        // Se registra la contrasena inicial en el historial y se obliga al
        // usuario a cambiarla la primera vez que entre.
        $passwords->cambiar($usuario, $datos['password'], motivo: 'inicial', debeCambiar: true);

        AuditLog::registrar(
            evento: 'usuario.creado',
            descripcion: sprintf('Se creó el usuario %s con el rol %s', $usuario->username, $datos['rol']),
            modelo: $usuario,
        );

        return to_route('admin.usuarios.index')
            ->with('status', sprintf('Usuario %s creado. Debe cambiar su contraseña en el primer ingreso.', $usuario->username));
    }

    public function edit(User $usuario): Response
    {
        $this->autorizarGestionDe($usuario);

        return Inertia::render('admin/usuarios/formulario', [
            'usuario' => [
                'id' => $usuario->id,
                'username' => $usuario->username,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'dpi' => $usuario->dpi,
                'puesto' => $usuario->puesto,
                'unidad' => $usuario->unidad,
                'telefono' => $usuario->telefono,
                'activo' => $usuario->activo,
                'rol' => $usuario->roles->first()?->name,
            ],
            'roles' => $this->rolesAsignables(),
            'passwordSugerida' => null,
        ]);
    }

    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        $this->autorizarGestionDe($usuario);

        $datos = $request->validated();

        $this->autorizarRol($datos['rol']);

        if ($usuario->id === Auth::id() && ! $datos['activo']) {
            throw ValidationException::withMessages([
                'activo' => 'No puede desactivar su propia cuenta.',
            ]);
        }

        $usuario->update([
            'username' => $datos['username'],
            'name' => $datos['name'],
            'email' => $datos['email'],
            'dpi' => $datos['dpi'] ?? null,
            'puesto' => $datos['puesto'] ?? null,
            'unidad' => $datos['unidad'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'activo' => $datos['activo'],
        ]);

        $usuario->syncRoles([$datos['rol']]);

        AuditLog::registrar(
            evento: 'usuario.actualizado',
            descripcion: sprintf('Se actualizó el usuario %s (rol %s)', $usuario->username, $datos['rol']),
            modelo: $usuario,
        );

        return to_route('admin.usuarios.index')->with('status', 'Usuario actualizado correctamente.');
    }

    /**
     * Restablecimiento por administrador: genera una contrasena temporal, la
     * muestra una unica vez y obliga al usuario a cambiarla al ingresar.
     */
    public function resetearPassword(User $usuario, PasswordService $passwords): RedirectResponse
    {
        $this->autorizarGestionDe($usuario);

        $temporal = PasswordPolicy::generarTemporal();

        $passwords->restablecerPorAdministrador($usuario, $temporal);

        return back()->with('passwordTemporal', [
            'usuario' => $usuario->username,
            'password' => $temporal,
        ]);
    }

    public function toggleActivo(User $usuario): RedirectResponse
    {
        $this->autorizarGestionDe($usuario);

        if ($usuario->id === Auth::id()) {
            throw ValidationException::withMessages([
                'activo' => 'No puede desactivar su propia cuenta.',
            ]);
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        AuditLog::registrar(
            evento: $usuario->activo ? 'usuario.activado' : 'usuario.desactivado',
            descripcion: sprintf(
                'La cuenta %s fue %s',
                $usuario->username,
                $usuario->activo ? 'activada' : 'desactivada',
            ),
            modelo: $usuario,
        );

        return back()->with('status', sprintf(
            'La cuenta %s fue %s.',
            $usuario->username,
            $usuario->activo ? 'activada' : 'desactivada',
        ));
    }

    public function desbloquear(User $usuario): RedirectResponse
    {
        $this->autorizarGestionDe($usuario);

        $usuario->forceFill([
            'bloqueado_hasta' => null,
            'intentos_fallidos' => 0,
        ])->save();

        AuditLog::registrar(
            evento: 'usuario.desbloqueado',
            descripcion: sprintf('Se desbloqueó la cuenta %s', $usuario->username),
            modelo: $usuario,
        );

        return back()->with('status', sprintf('La cuenta %s fue desbloqueada.', $usuario->username));
    }

    public function destroy(User $usuario): RedirectResponse
    {
        $this->autorizarGestionDe($usuario);

        if ($usuario->id === Auth::id()) {
            throw ValidationException::withMessages([
                'usuario' => 'No puede eliminar su propia cuenta.',
            ]);
        }

        AuditLog::registrar(
            evento: 'usuario.eliminado',
            descripcion: sprintf('Se eliminó el usuario %s', $usuario->username),
            modelo: $usuario,
        );

        // Borrado logico: el usuario deja de existir para el sistema pero se
        // conserva su rastro en la bitacora y en el historial de contrasenas.
        $usuario->delete();

        return to_route('admin.usuarios.index')->with('status', 'Usuario eliminado.');
    }

    public function historial(User $usuario): Response
    {
        $historial = $usuario->historialPasswords()
            ->with('autor:id,username,name')
            ->paginate(15)
            ->through(fn ($registro) => [
                'id' => $registro->id,
                'motivo' => $registro->motivoLegible(),
                'autor' => $registro->autor?->username ?? 'Sistema',
                'ip' => $registro->ip,
                'fecha' => $registro->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('admin/usuarios/historial', [
            'usuario' => [
                'id' => $usuario->id,
                'username' => $usuario->username,
                'name' => $usuario->name,
            ],
            'historial' => $historial,
        ]);
    }

    /**
     * Un Admin no puede tocar la cuenta de un Superadmin: solo otro
     * Superadmin puede hacerlo.
     */
    private function autorizarGestionDe(User $usuario): void
    {
        if ($usuario->esSuperadmin() && ! Auth::user()->esSuperadmin()) {
            abort(403, 'Solo un Superadmin puede administrar la cuenta de otro Superadmin.');
        }
    }

    /**
     * El rol Superadmin solo lo puede otorgar un Superadmin.
     */
    private function autorizarRol(string $rol): void
    {
        if ($rol === 'Superadmin' && ! Auth::user()->esSuperadmin()) {
            abort(403, 'Solo un Superadmin puede otorgar el rol Superadmin.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function rolesAsignables(): array
    {
        return Role::query()
            ->when(! Auth::user()->esSuperadmin(), fn ($q) => $q->where('name', '!=', 'Superadmin'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
