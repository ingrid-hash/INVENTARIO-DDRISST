<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'usuario_email',
        'evento',
        'descripcion',
        'modelo',
        'modelo_id',
        'datos',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'datos' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra un evento en la bitacora tomando por defecto al usuario
     * autenticado y los datos de la peticion en curso.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrar(
        string $evento,
        ?string $descripcion = null,
        ?User $usuario = null,
        ?Model $modelo = null,
        array $datos = [],
    ): self {
        $usuario ??= Auth::user();

        return self::create([
            'user_id' => $usuario?->id,
            'usuario_email' => $usuario?->email,
            'evento' => $evento,
            'descripcion' => $descripcion,
            'modelo' => $modelo ? class_basename($modelo) : null,
            'modelo_id' => $modelo?->getKey(),
            'datos' => $datos ?: null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
