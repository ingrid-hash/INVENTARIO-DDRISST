<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periodo de custodia de un bien por parte de un empleado.
 *
 * La base de datos garantiza que un bien tenga una sola asignacion activa, con
 * un indice unico parcial. El historial se conserva en las filas cerradas.
 */
class Asignacion extends Model
{
    protected $table = 'asignaciones';

    public const MOTIVOS_CIERRE = [
        'baja' => 'Baja autorizada del bien',
        'traslado' => 'Traslado a otra unidad',
        'cambio_responsable' => 'Cambio de responsable',
        'correccion' => 'Corrección de captura',
    ];

    protected $fillable = [
        'bien_id',
        'empleado_id',
        'tarjeta_id',
        'fecha_asignacion',
        'fecha_devolucion',
        'activa',
        'motivo_cierre',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'date',
            'fecha_devolucion' => 'date',
            'activa' => 'boolean',
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
     * @return BelongsTo<Empleado, $this>
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * @return BelongsTo<Tarjeta, $this>
     */
    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function motivoCierreLegible(): ?string
    {
        return $this->motivo_cierre
            ? (self::MOTIVOS_CIERRE[$this->motivo_cierre] ?? $this->motivo_cierre)
            : null;
    }

    /**
     * @param  Builder<Asignacion>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->where('activa', true);
    }
}
