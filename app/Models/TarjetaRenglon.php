<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una linea impresa de la tarjeta de responsabilidad.
 */
class TarjetaRenglon extends Model
{
    protected $table = 'tarjeta_renglones';

    protected $fillable = [
        'tarjeta_id',
        'bien_id',
        'orden',
        'debe',
        'haber',
        'saldo',
        'total_corte_original',
        'hoja_fisica',
        'impreso_at',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'debe' => 'decimal:2',
            'haber' => 'decimal:2',
            'saldo' => 'decimal:2',
            'total_corte_original' => 'decimal:2',
            'hoja_fisica' => 'integer',
            'impreso_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tarjeta, $this>
     */
    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class);
    }

    /**
     * @return BelongsTo<Bien, $this>
     */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function yaSeImprimio(): bool
    {
        return $this->impreso_at !== null;
    }

    public function cara(): ?string
    {
        return $this->hoja_fisica !== null
            ? Tarjeta::caraDeHoja($this->hoja_fisica)
            : null;
    }

    /**
     * @param  Builder<TarjetaRenglon>  $query
     */
    public function scopePendientesDeImprimir(Builder $query): void
    {
        $query->whereNull('impreso_at');
    }
}
