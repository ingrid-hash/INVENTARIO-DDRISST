<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * El usuario solo puede corregir sus datos de contacto. El correo, el
     * usuario y el rol los administra el area de informatica.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return to_route('profile.edit')->with('status', 'Sus datos fueron actualizados.');
    }
}
