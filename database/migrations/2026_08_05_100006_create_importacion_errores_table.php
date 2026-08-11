<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filas que el importador no pudo cargar, con el numero de fila del Excel
     * para que quien captura pueda ir a corregirlas al archivo original.
     */
    public function up(): void
    {
        Schema::create('importacion_errores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('importacion_id')
                ->constrained('importaciones')->cascadeOnDelete();

            $table->unsignedInteger('fila');
            $table->string('codigo', 40)->nullable();

            // codigo_duplicado | sin_codigo | renglon_desconocido | ...
            $table->string('motivo_clave', 40);
            $table->string('motivo');

            $table->json('datos')->nullable();
            $table->timestamps();

            $table->index(['importacion_id', 'motivo_clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_errores');
    }
};
