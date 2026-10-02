<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una hoja de papel de la tarjeta de responsabilidad.
 *
 * Guarda lo que el papel tiene y los renglones no saben: cuanto hay que correr
 * la impresion para que calce con lo que ya salio impreso, y si la hoja esta
 * cerrada.
 */
class TarjetaHoja extends Model
{
    protected $table = 'tarjeta_hojas';

    /**
     * Tope del calce. Mas alla de esto ya no es un ajuste, es otro papel.
     *
     * Son 18 mm, poco menos de cuatro renglones: alcanza para corregir una
     * bandeja que alimenta torcido sin permitir que la tinta se vaya tan abajo
     * que ya no tenga nada que ver con el formulario.
     */
    public const DESFASE_MAXIMO_MM = 18.0;

    /**
     * Los valores por omision de PostgreSQL no llegan al objeto recien creado, y
     * el paginador los lee de inmediato: sin esto una hoja nueva saldria sin
     * calce y sin ajuste de texto, que es null y no cero.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'desfase_x_mm' => 0,
        'desfase_y_mm' => 0,
        'escala' => 1,
    ];

    protected $fillable = [
        'tarjeta_id',
        'numero',
        'desfase_x_mm',
        'desfase_y_mm',
        'escala',
        'cerrada_at',
        'impresa_at',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'desfase_x_mm' => 'float',
            'desfase_y_mm' => 'float',
            'escala' => 'float',
            'cerrada_at' => 'datetime',
            'impresa_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tarjeta, $this>
     */
    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class);
    }

    public function estaCerrada(): bool
    {
        return $this->cerrada_at !== null;
    }

    public function seImprimio(): bool
    {
        return $this->impresa_at !== null;
    }

    public function tieneCalce(): bool
    {
        return abs($this->desfase_x_mm) > 0.01 || abs($this->desfase_y_mm) > 0.01;
    }

    public function cara(): string
    {
        return Tarjeta::caraDeHoja($this->numero);
    }

    public function papel(): int
    {
        return Tarjeta::papelDeHoja($this->numero);
    }
}
