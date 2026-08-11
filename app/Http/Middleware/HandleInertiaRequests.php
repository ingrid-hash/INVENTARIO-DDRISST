<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Datos disponibles en todas las pantallas de React.
     *
     * Se envia solo lo indispensable del usuario: nunca el hash de la
     * contrasena ni sus campos de control interno.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $usuario ? [
                    'id' => $usuario->id,
                    'username' => $usuario->username,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'puesto' => $usuario->puesto,
                    'unidad' => $usuario->unidad,
                    'telefono' => $usuario->telefono,
                    'rol' => $usuario->roles->first()?->name,
                    'permisos' => $usuario->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                // Se muestra una unica vez al administrador que restablecio
                // la contrasena de un usuario.
                'passwordTemporal' => fn () => $request->session()->get('passwordTemporal'),
            ],
        ];
    }
}
