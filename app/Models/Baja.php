<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expediente de baja de un bien.
 *
 * Mientras el estado es "solicitada" el bien sigue en la tarjeta del empleado y
 * sigue sumando al saldo. Solo al autorizarse se cierra la asignacion y la
 * tarjeta se regenera sin el bien.
 */
class Baja extends Model
{
    public const ESTADO_SOLICITADA = 'solicitada';
    public const ESTADO_AUTORIZADA = 'autorizada';
    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADOS = [
        self::ESTADO_SOLICITADA => 'En trámite',
        self::ESTADO_AUTORIZADA => 'Autorizada',
        self::ESTADO_RECHAZADA => 'Rechazada',
    ];

    protected $fillable = [
        'bien_id',
        'numero_acta',
        'motivo',
        'fecha_solicitud',
        'fecha_resolucion',
        'estado',
        'solicitada_por',
        'resuelta_por',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'date',
            'fecha_resolucion' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Bien, $this>
     */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    public function estaEnTramite(): bool
    {
        return $this->estado === self::ESTADO_SOLICITADA;
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * @param  Builder<Baja>  $query
     */
    public function scopeEnTramite(Builder $query): void
    {
        $query->where('estado', self::ESTADO_SOLICITADA);
    }

    /**
     * @param  Builder<Baja>  $query
     */
    public function scopeAutorizadas(Builder $query): void
    {
        $query->where('estado', self::ESTADO_AUTORIZADA);
    }
}
