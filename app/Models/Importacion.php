<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Constancia de una carga de archivo, con su conteo de filas y su resultado.
 */
class Importacion extends Model
{
    protected $table = 'importaciones';

    public const ESTADO_PREVISUALIZADA = 'previsualizada';
    public const ESTADO_CONFIRMADA = 'confirmada';
    public const ESTADO_REVERTIDA = 'revertida';

    protected $fillable = [
        'archivo',
        'hoja',
        'tipo',
        'unidad_servicio_id',
        'perfil_id',
        'user_id',
        'filas_leidas',
        'filas_importadas',
        'filas_rechazadas',
        'estado',
        'resumen',
    ];

    protected function casts(): array
    {
        return [
            'filas_leidas' => 'integer',
            'filas_importadas' => 'integer',
            'filas_rechazadas' => 'integer',
            'resumen' => 'array',
        ];
    }

    /**
     * @return BelongsTo<UnidadServicio, $this>
     */
    public function unidadServicio(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class);
    }

    /**
     * @return BelongsTo<PerfilImportacion, $this>
     */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilImportacion::class, 'perfil_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ImportacionError, $this>
     */
    public function errores(): HasMany
    {
        return $this->hasMany(ImportacionError::class, 'importacion_id');
    }

    /**
     * Bienes que entraron con esta carga. Permite revertirla completa si algo
     * salio mal.
     *
     * @return HasMany<Bien, $this>
     */
    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'importacion_id');
    }

    /**
     * @param  Builder<Importacion>  $query
     */
    public function scopeConfirmadas(Builder $query): void
    {
        $query->where('estado', self::ESTADO_CONFIRMADA);
    }
}
