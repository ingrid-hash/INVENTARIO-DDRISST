<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada punto numerado de una certificacion, con el texto tal como se imprimio.
 */
class CertificacionBien extends Model
{
    protected $table = 'certificacion_bienes';

    protected $fillable = ['certificacion_id', 'bien_id', 'orden', 'texto'];

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    /**
     * @return BelongsTo<Certificacion, $this>
     */
    public function certificacion(): BelongsTo
    {
        return $this->belongsTo(Certificacion::class);
    }

    /**
     * @return BelongsTo<Bien, $this>
     */
    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }
}
