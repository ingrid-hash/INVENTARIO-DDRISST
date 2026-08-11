<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordHistory extends Model
{
    /**
     * Cantidad de contrasenas anteriores que no se pueden reutilizar.
     */
    public const NO_REUTILIZAR_ULTIMAS = 5;

    protected $fillable = [
        'user_id',
        'cambiado_por',
        'password_hash',
        'motivo',
        'ip',
        'user_agent',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quien realizo el cambio: el propio usuario o un administrador.
     *
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por');
    }

    public function motivoLegible(): string
    {
        return match ($this->motivo) {
            'inicial' => 'Contraseña inicial',
            'auto_cambio' => 'Cambiada por el propio usuario',
            'reset_admin' => 'Restablecida por un administrador',
            'cambio_forzado' => 'Cambio obligatorio tras restablecimiento',
            default => $this->motivo,
        };
    }
}
