<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarjeta de responsabilidad en el formato de la Contraloria General de
 * Cuentas. Se versiona: al quitar un bien nace una version nueva y la anterior
 * se conserva, porque es el documento que la persona firmo.
 */
class Tarjeta extends Model
{
    public const ESTADO_VIGENTE = 'vigente';
    public const ESTADO_REEMPLAZADA = 'reemplazada';

    protected $fillable = [
        'empleado_id',
        'unidad_servicio_id',
        'numero',
        'version',
        'reemplaza_a',
        'estado',
        'fecha_apertura',
        'saldo_total',
        'renglones_por_hoja',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'fecha_apertura' => 'date',
            'saldo_total' => 'decimal:2',
            'renglones_por_hoja' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Empleado, $this>
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * @return BelongsTo<UnidadServicio, $this>
     */
    public function unidadServicio(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class);
    }

    /**
     * @return BelongsTo<Tarjeta, $this>
     */
    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_a');
    }

    /**
     * Los bienes de la tarjeta, en el orden del documento. Nunca se reordena:
     * las adiciones entran al final.
     *
     * @return HasMany<TarjetaRenglon, $this>
     */
    public function renglones(): HasMany
    {
        return $this->hasMany(TarjetaRenglon::class)->orderBy('orden');
    }

    /**
     * @return HasMany<Asignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }

    public function estaVigente(): bool
    {
        return $this->estado === self::ESTADO_VIGENTE;
    }

    /** La posicion que le toca al proximo bien que se agregue. */
    public function siguienteOrden(): int
    {
        return (int) $this->renglones()->max('orden') + 1;
    }

    /** Numero de la ultima hoja de papel que ya se imprimio. */
    public function ultimaHojaImpresa(): ?int
    {
        $hoja = $this->renglones()->whereNotNull('hoja_fisica')->max('hoja_fisica');

        return $hoja !== null ? (int) $hoja : null;
    }

    /**
     * Cuantos renglones caben todavia en la ultima hoja impresa. Es lo que
     * permite reutilizar el papel que ya salio de la impresora en lugar de
     * gastar una hoja nueva.
     */
    public function espacioEnUltimaHoja(): int
    {
        $hoja = $this->ultimaHojaImpresa();

        if ($hoja === null) {
            return $this->renglones_por_hoja;
        }

        $ocupados = $this->renglones()->where('hoja_fisica', $hoja)->count();

        return max(0, $this->renglones_por_hoja - $ocupados);
    }

    /** Renglones que todavia no se han impreso en ningun papel. */
    public function renglonesPendientesDeImprimir(): int
    {
        return $this->renglones()->whereNull('impreso_at')->count();
    }

    /** Frente o reverso: las hojas impares van al frente del papel. */
    public static function caraDeHoja(int $hoja): string
    {
        return $hoja % 2 === 1 ? 'frente' : 'reverso';
    }

    /** Dos hojas por papel: la 1 y la 2 son el mismo papel. */
    public static function papelDeHoja(int $hoja): int
    {
        return (int) ceil($hoja / 2);
    }

    /**
     * @param  Builder<Tarjeta>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->where('estado', self::ESTADO_VIGENTE);
    }
}
