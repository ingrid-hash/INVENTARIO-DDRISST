<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Certificacion emitida. Guarda el texto ya armado para poder reimprimirla
 * identica mas adelante.
 */
class Certificacion extends Model
{
    protected $table = 'certificaciones';

    protected $fillable = [
        'numero',
        'certificacion_formato_id',
        'unidad_servicio_id',
        'libro_auxiliar',
        'libro_registro',
        'libro_folio',
        'apertura',
        'parrafo_libro',
        'cierre',
        'firmante_nombre',
        'firmante_cargo',
        'vobo_nombre',
        'vobo_cargo',
        'institucion',
        'emitida_por',
    ];

    protected function casts(): array
    {
        return ['libro_auxiliar' => 'boolean'];
    }

    /**
     * @return HasMany<CertificacionBien, $this>
     */
    public function bienes(): HasMany
    {
        return $this->hasMany(CertificacionBien::class)->orderBy('orden');
    }

    /**
     * @return BelongsTo<UnidadServicio, $this>
     */
    public function unidadServicio(): BelongsTo
    {
        return $this->belongsTo(UnidadServicio::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function emitidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitida_por');
    }

    /**
     * Correlativo del ano en curso: 0001-2026.
     */
    public static function siguienteNumero(): string
    {
        $anio = now()->year;

        $emitidas = static::query()
            ->where('numero', 'like', '%-'.$anio)
            ->count();

        return sprintf('%04d-%d', $emitidas + 1, $anio);
    }
}
