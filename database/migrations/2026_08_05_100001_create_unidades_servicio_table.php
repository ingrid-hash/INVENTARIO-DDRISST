<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidades de servicio del MSPAS: direccion de area, distritos, centros y
     * puestos de salud. Se relacionan entre si con padre_id para poder pedir
     * reportes de un puesto, de un distrito o del area completa.
     */
    public function up(): void
    {
        Schema::create('unidades_servicio', function (Blueprint $table) {
            $table->id();

            $table->foreignId('padre_id')->nullable()
                ->constrained('unidades_servicio')->nullOnDelete();

            // Codigo institucional corto, el mismo que se usa en los codigos
            // de bien del formato nuevo: 2026-211-CHI-0013 -> 211-CHI.
            $table->string('codigo', 30)->unique();
            $table->string('nombre');

            // area | distrito | centro_salud | puesto_salud
            $table->string('tipo', 20);

            $table->string('municipio')->nullable();
            $table->string('departamento')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_servicio');
    }
};
