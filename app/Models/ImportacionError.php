<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila que el importador no pudo cargar. Guarda el numero de fila del Excel
 * para que quien captura pueda ir a corregirla en el archivo original.
 */
class ImportacionError extends Model
{
    protected $table = 'importacion_errores';

    public const MOTIVOS = [
        'sin_codigo' => 'La fila no trae código de inventario',
        'codigo_duplicado' => 'El código ya existe en el sistema',
        'codigo_invalido' => 'El código no tiene un formato reconocido',
        'sin_descripcion' => 'La fila no trae descripción',
        'renglon_desconocido' => 'La cuenta indicada no está en el catálogo',
        'precio_invalido' => 'El monto no es un número válido',
    ];

    protected $fillable = [
        'importacion_id',
        'fila',
        'codigo',
        'motivo_clave',
        'motivo',
        'datos',
    ];

    protected function casts(): array
    {
        return [
            'fila' => 'integer',
            'datos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Importacion, $this>
     */
    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class);
    }
}
