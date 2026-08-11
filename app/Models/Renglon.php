<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Renglon presupuestario, la "cuenta" del inventario.
 *
 * En la tarjeta de responsabilidad se imprime solo el codigo; en el listado
 * general, el codigo con su nombre.
 */
class Renglon extends Model
{
    protected $table = 'renglones';

    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return HasMany<Bien, $this>
     */
    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'renglon_id');
    }

    /** Como aparece en el encabezado de seccion del listado general. */
    public function etiquetaCompleta(): string
    {
        return $this->codigo.' '.$this->nombre;
    }

    /**
     * @param  Builder<Renglon>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * @param  Builder<Renglon>  $query
     */
    public function scopeOrdenados(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('codigo');
    }
}
