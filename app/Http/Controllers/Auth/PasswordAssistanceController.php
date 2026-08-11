<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El sistema no envia correos: el restablecimiento lo realiza siempre un
 * administrador desde el modulo de usuarios. Esta pantalla solo le indica al
 * usuario a quien debe acudir.
 */
class PasswordAssistanceController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/olvide-password');
    }
}
