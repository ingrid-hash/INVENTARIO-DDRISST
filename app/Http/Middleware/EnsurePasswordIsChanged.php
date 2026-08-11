<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras el usuario tenga pendiente el cambio obligatorio de contrasena,
 * el sistema no le permite navegar a ninguna otra pantalla.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        $rutasPermitidas = $request->routeIs('password.forzado', 'password.forzado.store', 'logout');

        if ($usuario && $usuario->debe_cambiar_password && ! $rutasPermitidas) {
            return redirect()->route('password.forzado');
        }

        return $next($request);
    }
}
