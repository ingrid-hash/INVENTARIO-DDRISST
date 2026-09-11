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

    /**
     * Renglones que caben en una hoja del formato de 2026: los rotulos van en la
     * fila 10 y los bienes de la 11 a la 37.
     */
    public const RENGLONES_POR_HOJA = 27;

    /**
     * La capacidad se fija aqui y no solo en la base. El valor por omision de
     * PostgreSQL no llega al objeto recien creado, y el paginador lo lee de
     * inmediato: sin esto una tarjeta nueva paginaria de a un renglon por hoja.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'renglones_por_hoja' => self::RENGLONES_POR_HOJA,
    ];

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

    /**
     * Las hojas de papel de la tarjeta, con su calce y su estado.
     *
     * @return HasMany<TarjetaHoja, $this>
     */
    public function hojas(): HasMany
    {
        return $this->hasMany(TarjetaHoja::class)->orderBy('numero');
    }

    /**
     * La hoja indicada, creandola si es la primera vez que se nombra. Una hoja
     * nace abierta y sin calce: solo existe para poder guardarle algo.
     */
    public function hoja(int $numero): TarjetaHoja
    {
        return $this->hojas()->firstOrCreate(['numero' => $numero]);
    }

    /**
     * Estado de las hojas indexado por numero, para no consultar una por una al
     * paginar.
     *
     * @return array<int, TarjetaHoja>
     */
    public function hojasPorNumero(): array
    {
        return $this->hojas->keyBy('numero')->all();
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
