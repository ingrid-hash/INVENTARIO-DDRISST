<?php

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Intentos por combinacion usuario+IP antes de frenar la peticion.
     */
    private const MAX_INTENTOS_POR_IP = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login' => 'usuario',
            'password' => 'contraseña',
        ];
    }

    /**
     * Verifica las credenciales aplicando, en orden: freno por IP,
     * bloqueo de la cuenta, validez de la contrasena y estado de la cuenta.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credencial = (string) $this->string('login');
        $campo = filter_var($credencial, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $usuario = User::where($campo, $credencial)->first();

        $this->ensureCuentaNoBloqueada($usuario);

        $credenciales = [$campo => $credencial, 'password' => (string) $this->string('password')];

        if (! Auth::attempt($credenciales, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $usuario?->registrarIntentoFallido();

            AuditLog::registrar(
                evento: 'acceso.fallido',
                descripcion: sprintf('Intento de acceso fallido con la credencial "%s"', $credencial),
                usuario: $usuario,
            );

            if ($usuario?->fresh()?->estaBloqueado()) {
                AuditLog::registrar(
                    evento: 'acceso.cuenta_bloqueada',
                    descripcion: sprintf(
                        'La cuenta %s quedó bloqueada por %d intentos fallidos',
                        $usuario->username,
                        User::MAX_INTENTOS_FALLIDOS,
                    ),
                    usuario: $usuario,
                );
            }

            throw ValidationException::withMessages([
                'login' => 'Las credenciales ingresadas no son correctas.',
            ]);
        }

        // La contrasena es correcta, pero la cuenta puede estar deshabilitada.
        // Se comprueba despues de validar la clave para no revelar a un
        // desconocido si el usuario existe o no.
        $autenticado = Auth::user();

        if (! $autenticado->activo) {
            Auth::guard('web')->logout();

            AuditLog::registrar(
                evento: 'acceso.cuenta_inactiva',
                descripcion: sprintf('La cuenta %s está desactivada', $autenticado->username),
                usuario: $autenticado,
            );

            throw ValidationException::withMessages([
                'login' => 'Su cuenta se encuentra desactivada. Comuníquese con el administrador del sistema.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureCuentaNoBloqueada(?User $usuario): void
    {
        if ($usuario === null || ! $usuario->estaBloqueado()) {
            return;
        }

        $minutos = max(1, (int) ceil($usuario->segundosParaDesbloqueo() / 60));

        throw ValidationException::withMessages([
            'login' => sprintf(
                'La cuenta está bloqueada por seguridad. Intente de nuevo en %d minuto(s) o solicite ayuda al administrador.',
                $minutos,
            ),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_INTENTOS_POR_IP)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
