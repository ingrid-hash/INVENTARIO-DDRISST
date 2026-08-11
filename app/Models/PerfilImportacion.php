<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mapeo de columnas guardado para un formato de archivo.
 *
 * Los Excel de las unidades no comparten estructura, asi que el mapeo se define
 * una vez en pantalla y se reutiliza en las cargas siguientes de esa unidad.
 */
class PerfilImportacion extends Model
{
    protected $table = 'perfiles_importacion';

    public const TIPO_TARJETA = 'tarjeta';
    public const TIPO_LISTADO = 'listado';

    /** Campos que el mapeo puede apuntar a una columna del Excel. */
    public const CAMPOS = [
        'codigo' => 'Código de inventario',
        'descripcion' => 'Descripción',
        'cantidad' => 'Cantidad',
        'precio_unitario' => 'Precio unitario',
        'total' => 'Total o monto',
        'cuenta' => 'Cuenta (renglón)',
        'fecha' => 'Fecha',
        'observaciones' => 'Observaciones',
    ];

    protected $fillable = [
        'nombre',
        'unidad_servicio_id',
        'tipo',
        'mapeo',
        'fila_encabezado',
        'fila_inicio_datos',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'mapeo' => 'array',
            'fila_encabezado' => 'integer',
            'fila_inicio_datos' => 'integer',
            'activo' => 'boolean',
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
     * @return HasMany<Importacion, $this>
     */
    public function importaciones(): HasMany
    {
        return $this->hasMany(Importacion::class, 'perfil_id');
    }

    /** La letra de columna asignada a un campo, por ejemplo 'H' para codigo. */
    public function columnaDe(string $campo): ?string
    {
        return $this->mapeo[$campo] ?? null;
    }
}
