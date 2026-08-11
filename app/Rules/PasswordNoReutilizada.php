<?php

namespace App\Rules;

use App\Models\PasswordHistory;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Impide que un usuario vuelva a usar alguna de sus ultimas contrasenas.
 */
class PasswordNoReutilizada implements ValidationRule
{
    public function __construct(private readonly User $usuario) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if ($this->usuario->password && Hash::check($value, $this->usuario->password)) {
            $fail('La nueva contraseña no puede ser igual a la contraseña actual.');

            return;
        }

        $anteriores = PasswordHistory::query()
            ->where('user_id', $this->usuario->id)
            ->latest()
            ->limit(PasswordHistory::NO_REUTILIZAR_ULTIMAS)
            ->pluck('password_hash');

        foreach ($anteriores as $hash) {
            if (Hash::check($value, $hash)) {
                $fail(sprintf(
                    'La contraseña ya fue utilizada antes. Elija una distinta a sus últimas %d contraseñas.',
                    PasswordHistory::NO_REUTILIZAR_ULTIMAS,
                ));

                return;
            }
        }
    }
}
