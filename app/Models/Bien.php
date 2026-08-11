<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bien de inventario. El codigo es la llave de todo el sistema.
 */
class Bien extends Model
{
    use SoftDeletes;

    protected $table = 'bienes';

    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_BAJA_SOLICITADA = 'baja_solicitada';
    public const ESTADO_BAJA = 'baja';

    public const ESTADOS = [
        self::ESTADO_ACTIVO => 'Activo',
        self::ESTADO_BAJA_SOLICITADA => 'Baja en trámite',
        self::ESTADO_BAJA => 'Dado de baja',
    ];

    public const MOVIMIENTOS = [
        'apertura' => 'Inventario inicial',
        'adicion' => 'Adición',
    ];

    public const FORMAS_ADQUISICION = [
        'compra' => 'Compra',
        'donacion' => 'Donación',
    ];

    /**
     * Prefijo de los codigos que genera el sistema cuando el archivo no trae
     * uno. Se distingue a simple vista de los dos formatos oficiales.
     */
    public const PREFIJO_PROVISIONAL = 'SC';

    protected $fillable = [
        'codigo',
        'codigo_provisional',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'total',
        'unidad_servicio_id',
        'renglon_id',
        'cuenta_texto_original',
        'tipo_movimiento',
        'forma_adquisicion',
        'programa',
        'documento_respaldo',
        'fecha_texto_original',
        'fecha_ingreso',
        'anio_ingreso',
        'estado',
        'observaciones',
        'importacion_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha_ingreso' => 'date',
            'anio_ingreso' => 'integer',
            'codigo_provisional' => 'boolean',
        ];
    }

    /**
     * Genera un codigo para un bien que llego sin el suyo, con el formato
     * SC-211-CHI-0001. Lleva el codigo de la unidad para que se sepa de donde
     * salio y un correlativo propio de esa unidad.
     */
    public static function generarCodigoProvisional(UnidadServicio $unidad): string
    {
        $prefijo = sprintf('%s-%s-', self::PREFIJO_PROVISIONAL, $unidad->codigo);

        $ultimo = self::withTrashed()
            ->where('codigo', 'like', $prefijo.'%')
            ->orderByDesc('codigo')
            ->value('codigo');

        $correlativo = $ultimo !== null
            ? ((int) substr($ultimo, strlen($prefijo))) + 1
            : 1;

        return $prefijo.str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<UnidadServicio, $this>
     */
    public function unidadServicio(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class);
    }

    /**
     * @return BelongsTo<Renglon, $this>
     */
    public function renglon(): BelongsTo
    {
        return $this->belongsTo(Renglon::class, 'renglon_id');
    }

    /**
     * @return BelongsTo<Importacion, $this>
     */
    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class);
    }

    /**
     * @return HasMany<Asignacion, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class)->latest('fecha_asignacion');
    }

    /**
     * Quien responde hoy por el bien. Solo puede haber una, garantizado por
     * indice unico parcial en la base de datos.
     *
     * @return HasOne<Asignacion, $this>
     */
    public function asignacionVigente(): HasOne
    {
        return $this->hasOne(Asignacion::class)->where('activa', true);
    }

    /**
     * @return HasMany<Baja, $this>
     */
    public function bajas(): HasMany
    {
        return $this->hasMany(Baja::class);
    }

    /**
     * @return HasOne<Baja, $this>
     */
    public function bajaEnTramite(): HasOne
    {
        return $this->hasOne(Baja::class)->where('estado', 'solicitada');
    }

    /**
     * @return HasMany<TarjetaRenglon, $this>
     */
    public function renglonesTarjeta(): HasMany
    {
        return $this->hasMany(TarjetaRenglon::class, 'bien_id');
    }

    public function estaAsignado(): bool
    {
        return $this->asignacionVigente()->exists();
    }

    public function esAdicion(): bool
    {
        return $this->tipo_movimiento === 'adicion';
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Lo que se imprime en la columna CUENTA de la tarjeta.
     *
     * Los bienes historicos conservan la celda literal del Excel para que la
     * reimpresion salga identica al papel firmado. Las adiciones capturadas en
     * el sistema se arman con los campos estructurados.
     *
     * @return array<int, string>  cada elemento es una linea de la celda
     */
    public function lineasColumnaCuenta(): array
    {
        if ($this->tipo_movimiento !== 'adicion') {
            $original = trim((string) $this->cuenta_texto_original);

            if ($original === '') {
                return [];
            }

            $lineas = preg_split('/\r\n|\r|\n/', $original);

            return array_values(array_filter(
                array_map('trim', $lineas ?: []),
                fn (string $linea) => $linea !== '',
            ));
        }

        $lineas = [];

        if ($this->renglon) {
            $lineas[] = $this->renglon->codigo;
        }

        $lineas[] = 'ADICION';

        if ($this->documento_respaldo) {
            $lineas[] = $this->documento_respaldo;
        }

        return $lineas;
    }

    /**
     * @param  Builder<Bien>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('estado', self::ESTADO_ACTIVO);
    }

    /**
     * @param  Builder<Bien>  $query
     */
    public function scopeSinAsignar(Builder $query): void
    {
        $query->whereDoesntHave('asignacionVigente');
    }

    /**
     * @param  Builder<Bien>  $query
     */
    public function scopeSinCuenta(Builder $query): void
    {
        $query->whereNull('renglon_id');
    }

    /**
     * Bienes cuyo codigo lo puso el sistema y sigue pendiente de que Inventarios
     * les asigne el oficial.
     *
     * @param  Builder<Bien>  $query
     */
    public function scopeConCodigoProvisional(Builder $query): void
    {
        $query->where('codigo_provisional', true);
    }

    /**
     * Busqueda por codigo exacto o por palabras de la descripcion, que son las
     * dos formas en que se busca un bien al armar una tarjeta.
     *
     * @param  Builder<Bien>  $query
     */
    public function scopeBuscar(Builder $query, string $termino): void
    {
        $termino = trim($termino);

        if ($termino === '') {
            return;
        }

        $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo', 'ilike', $termino.'%')
                ->orWhere('descripcion', 'ilike', '%'.$termino.'%');
        });
    }

    /**
     * Bienes de una unidad y de todas las que dependen de ella.
     *
     * @param  Builder<Bien>  $query
     */
    public function scopeDeUnidadConDescendientes(Builder $query, UnidadServicio $unidad): void
    {
        $query->whereIn('unidad_servicio_id', $unidad->idsConDescendientes());
    }
}
