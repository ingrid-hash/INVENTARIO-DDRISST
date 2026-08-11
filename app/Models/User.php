<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Intentos fallidos permitidos antes de bloquear la cuenta.
     */
    public const MAX_INTENTOS_FALLIDOS = 5;

    /**
     * Minutos que permanece bloqueada la cuenta tras agotar los intentos.
     */
    public const MINUTOS_BLOQUEO = 15;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'dpi',
        'puesto',
        'unidad',
        'telefono',
        'email',
        'password',
        'activo',
        'debe_cambiar_password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
            'password_cambiado_en' => 'datetime',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PasswordHistory, $this>
     */
    public function historialPasswords(): HasMany
    {
        return $this->hasMany(PasswordHistory::class)->latest();
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function bitacora(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest();
    }

    public function esSuperadmin(): bool
    {
        return $this->hasRole('Superadmin');
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    public function segundosParaDesbloqueo(): int
    {
        if (! $this->estaBloqueado()) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->bloqueado_hasta, absolute: true);
    }

    /**
     * Suma un intento fallido y bloquea la cuenta al llegar al limite.
     */
    public function registrarIntentoFallido(): void
    {
        $this->increment('intentos_fallidos');

        if ($this->intentos_fallidos >= self::MAX_INTENTOS_FALLIDOS) {
            $this->forceFill([
                'bloqueado_hasta' => now()->addMinutes(self::MINUTOS_BLOQUEO),
                'intentos_fallidos' => 0,
            ])->save();
        }
    }

    /**
     * Limpia el contador de intentos y deja constancia del acceso.
     */
    public function registrarAccesoExitoso(?string $ip): void
    {
        $this->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso_en' => now(),
            'ultimo_acceso_ip' => $ip,
        ])->save();
    }

    public function bloqueadoHastaFormateado(): ?Carbon
    {
        return $this->bloqueado_hasta;
    }
}
