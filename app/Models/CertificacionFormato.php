<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Combinacion de firmas de una certificacion. Entre un formato y otro solo
 * cambian la linea de apertura y los dos bloques de firma.
 */
class CertificacionFormato extends Model
{
    protected $table = 'certificacion_formatos';

    public const GENEROS = ['f' => 'Mujer', 'm' => 'Hombre'];

    protected $fillable = [
        'nombre',
        'cargo_apertura',
        'genero',
        'firmante_nombre',
        'firmante_cargo',
        'vobo_nombre',
        'vobo_cargo',
        'institucion',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
            'predeterminado' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Certificacion, $this>
     */
    public function certificaciones(): HasMany
    {
        return $this->hasMany(Certificacion::class);
    }
}
