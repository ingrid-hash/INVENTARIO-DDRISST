<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\PasswordService;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Cambio voluntario del propio usuario. Exige la contrasena actual para
     * que nadie pueda aprovechar una sesion abierta y ajena.
     */
    public function update(Request $request, PasswordService $passwords): RedirectResponse
    {
        $usuario = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => PasswordPolicy::rules($usuario),
        ], attributes: [
            'current_password' => 'contraseña actual',
            'password' => 'nueva contraseña',
        ]);

        $passwords->cambiar($usuario, (string) $request->string('password'), motivo: 'auto_cambio');

        return back()->with('status', 'Su contraseña fue actualizada correctamente.');
    }
}
