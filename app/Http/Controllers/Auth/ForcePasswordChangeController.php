<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordService;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla obligatoria que aparece cuando un administrador restablecio la
 * contrasena del usuario: no puede entrar al sistema hasta definir una nueva.
 */
class ForcePasswordChangeController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (! Auth::user()->debe_cambiar_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/cambio-obligatorio');
    }

    public function store(Request $request, PasswordService $passwords): RedirectResponse
    {
        $usuario = Auth::user();

        if (! $usuario->debe_cambiar_password) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'password' => PasswordPolicy::rules($usuario),
        ], attributes: [
            'password' => 'contraseña',
        ]);

        $passwords->cambiar($usuario, (string) $request->string('password'), motivo: 'cambio_forzado');

        return redirect()->route('dashboard')
            ->with('status', 'Su contraseña fue actualizada. Ya puede usar el sistema con normalidad.');
    }
}
