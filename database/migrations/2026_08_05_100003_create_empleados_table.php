<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personal que responde por los bienes.
     *
     * No son usuarios del sistema: la mayoria nunca inicia sesion. Se mantienen
     * aparte de la tabla users a proposito, para no tener que crear cuentas con
     * contrasena a gente que solo firma una tarjeta.
     */
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unidad_servicio_id')
                ->constrained('unidades_servicio')->cascadeOnDelete();

            $table->string('nombre_completo');
            $table->string('dpi', 13)->nullable();
            $table->string('cargo')->nullable();

            // El "DEPARTAMENTO:" que aparece junto al cargo en la tarjeta y que
            // se refiere al area de trabajo (ENFERMERIA, NUTRICION), no al
            // departamento geografico, que vive en la unidad de servicio.
            $table->string('area_trabajo')->nullable();

            $table->boolean('activo')->default(true);

            $table->softDeletes();
            $table->timestamps();

            $table->index('nombre_completo');
            $table->index(['unidad_servicio_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
