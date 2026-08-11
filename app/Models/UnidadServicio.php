<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadServicio extends Model
{
    protected $table = 'unidades_servicio';

    public const TIPOS = [
        'area' => 'Dirección de Área',
        'distrito' => 'Distrito de Salud',
        'centro_salud' => 'Centro de Salud',
        'puesto_salud' => 'Puesto de Salud',
    ];

    protected $fillable = [
        'padre_id',
        'codigo',
        'nombre',
        'tipo',
        'municipio',
        'departamento',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return BelongsTo<UnidadServicio, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    /**
     * @return HasMany<UnidadServicio, $this>
     */
    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    /**
     * @return HasMany<Bien, $this>
     */
    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class);
    }

    /**
     * @return HasMany<Empleado, $this>
     */
    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    /**
     * @return HasMany<Tarjeta, $this>
     */
    public function tarjetas(): HasMany
    {
        return $this->hasMany(Tarjeta::class);
    }

    public function tipoLegible(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /**
     * Todos los identificadores de esta unidad y de las que dependen de ella,
     * a cualquier profundidad. Es lo que permite pedir un reporte de un
     * distrito y recibir tambien lo de sus puestos de salud.
     *
     * @return array<int, int>
     */
    public function idsConDescendientes(): array
    {
        $ids = [$this->id];
        $pendientes = [$this->id];

        while ($pendientes !== []) {
            $hijas = self::query()
                ->whereIn('padre_id', $pendientes)
                ->pluck('id')
                ->all();

            $nuevas = array_diff($hijas, $ids);

            if ($nuevas === []) {
                break;
            }

            $ids = array_merge($ids, $nuevas);
            $pendientes = $nuevas;
        }

        return $ids;
    }

    /**
     * @param  Builder<UnidadServicio>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activo', true);
    }
}
