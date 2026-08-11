<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Se cambia el identificador de sesion tras autenticar para cerrar
        // la puerta a un ataque de fijacion de sesion.
        $request->session()->regenerate();

        $usuario = Auth::user();
        $usuario->registrarAccesoExitoso($request->ip());

        AuditLog::registrar(
            evento: 'acceso.exitoso',
            descripcion: sprintf('%s inició sesión', $usuario->username),
        );

        if ($usuario->debe_cambiar_password) {
            return redirect()->route('password.forzado');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $usuario = Auth::user();

        if ($usuario) {
            AuditLog::registrar(
                evento: 'sesion.cierre',
                descripcion: sprintf('%s cerró sesión', $usuario->username),
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
