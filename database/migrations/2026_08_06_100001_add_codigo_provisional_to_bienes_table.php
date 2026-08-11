<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Muchos bienes de los archivos tienen descripcion y monto pero nadie les
     * anoto el codigo de inventario. Antes se rechazaban y se perdian, aunque de
     * ellos si se sabe lo mas importante: a que unidad pertenecen y quien
     * responde por ellos.
     *
     * Ahora entran con un codigo provisional que el sistema genera, marcado para
     * poder listarlos y corregirlos cuando Inventarios asigne el definitivo.
     */
    public function up(): void
    {
        Schema::table('bienes', function (Blueprint $table) {
            $table->boolean('codigo_provisional')->default(false)->after('codigo');
        });

        // Los pendientes de corregir se consultan seguido: conviene el indice.
        Schema::table('bienes', function (Blueprint $table) {
            $table->index(['codigo_provisional', 'unidad_servicio_id'], 'bienes_provisional_unidad_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bienes', function (Blueprint $table) {
            $table->dropIndex('bienes_provisional_unidad_idx');
            $table->dropColumn('codigo_provisional');
        });
    }
};
