<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Persona que responde por bienes del Estado.
 *
 * No es un usuario del sistema: la mayoria del personal nunca inicia sesion,
 * solo firma su tarjeta de responsabilidad.
 */
class Empleado extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'unidad_servicio_id',
        'nombre_completo',
        'dpi',
        'cargo',
        'area_trabajo',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return BelongsTo<UnidadServicio, $this>
     */
    public function unidadServicio(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class);
    }

    /**
     * @return HasMany<Tarjeta, $this>
     */
    public function tarjetas(): HasMany
    {
        return $this->hasMany(Tarjeta::class);
    }

    /**
     * La tarjeta que esta en uso. Solo puede haber una, garantizado por indice
     * unico parcial en la base de datos.
     *
     * @return HasOne<Tarjeta, $this>
     */
    public function tarjetaVigente(): HasOne
    {
        return $this->hasOne(Tarjeta::class)->where('estado', 'vigente');
    }

    /**
     * @return HasMany<Asignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    /**
     * @return HasMany<Asignacion, $this>
     */
    public function asignacionesVigentes(): HasMany
    {
        return $this->hasMany(Asignacion::class)->where('activa', true);
    }

    /**
     * @param  Builder<Empleado>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
