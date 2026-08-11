<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Constancia de cada carga de archivo: quien la hizo, con que perfil, y
     * cuantas filas entraron o se rechazaron. Permite rastrear el origen de
     * cualquier bien y revertir una carga completa si salio mal.
     */
    public function up(): void
    {
        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();

            $table->string('archivo');
            $table->string('hoja')->nullable();

            // tarjeta | listado
            $table->string('tipo', 20);

            $table->foreignId('unidad_servicio_id')->nullable()
                ->constrained('unidades_servicio')->nullOnDelete();

            $table->foreignId('perfil_id')->nullable()
                ->constrained('perfiles_importacion')->nullOnDelete();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->unsignedInteger('filas_leidas')->default(0);
            $table->unsignedInteger('filas_importadas')->default(0);
            $table->unsignedInteger('filas_rechazadas')->default(0);

            // previsualizada | confirmada | revertida
            $table->string('estado', 20)->default('previsualizada');

            $table->json('resumen')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones');
    }
};
