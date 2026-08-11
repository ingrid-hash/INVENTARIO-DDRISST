<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('usuarios.crear');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'alpha_dash', 'min:4', 'max:60', Rule::unique(User::class, 'username')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'dpi' => ['nullable', 'string', 'digits:13'],
            'puesto' => ['nullable', 'string', 'max:255'],
            'unidad' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rol' => ['required', 'string', Rule::exists('roles', 'name')],

            // La contrasena inicial la define el administrador y el usuario
            // queda obligado a cambiarla en su primer ingreso.
            'password' => PasswordPolicy::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => 'nombre de usuario',
            'name' => 'nombre completo',
            'email' => 'correo electrónico',
            'telefono' => 'teléfono',
            'rol' => 'rol',
            'password' => 'contraseña inicial',
        ];
    }
}
