<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si a un usuario se le desactiva la cuenta mientras tiene la sesion abierta,
 * la sesion se cierra en su siguiente peticion.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario && ! $usuario->activo) {
            AuditLog::registrar(
                evento: 'sesion.revocada',
                descripcion: sprintf('Sesión cerrada: la cuenta %s fue desactivada', $usuario->username),
            );

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Su cuenta fue desactivada. Comuníquese con el administrador del sistema.',
            ]);
        }

        return $next($request);
    }
}
