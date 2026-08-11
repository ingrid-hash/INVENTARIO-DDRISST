<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapeo de columnas guardado por formato de archivo.
     *
     * Los Excel de las unidades no comparten estructura: el codigo aparece en
     * la columna C, E, F, G o H segun el archivo y la hoja. En lugar de fijar
     * posiciones en el codigo, el mapeo se define una vez en pantalla y se
     * guarda aqui para reutilizarlo en las cargas siguientes.
     */
    public function up(): void
    {
        Schema::create('perfiles_importacion', function (Blueprint $table) {
            $table->id();

            $table->string('nombre');

            $table->foreignId('unidad_servicio_id')->nullable()
                ->constrained('unidades_servicio')->nullOnDelete();

            // tarjeta | listado
            $table->string('tipo', 20);

            // {"codigo":"H","descripcion":"E","cantidad":"D","precio":"F", ...}
            $table->json('mapeo');

            $table->unsignedSmallInteger('fila_encabezado')->nullable();
            $table->unsignedSmallInteger('fila_inicio_datos')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['unidad_servicio_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles_importacion');
    }
};
