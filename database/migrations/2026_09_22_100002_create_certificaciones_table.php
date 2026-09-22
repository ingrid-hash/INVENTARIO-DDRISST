<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificaciones emitidas.
     *
     * Se guarda el texto ya armado y no solo las referencias: la certificacion
     * es un documento firmado y sellado, y tiene que poder reimprimirse igual
     * aunque despues cambie la descripcion del bien, el encargado de turno o el
     * numero de libro del ano siguiente.
     */
    public function up(): void
    {
        Schema::create('certificaciones', function (Blueprint $table) {
            $table->id();

            // Correlativo visible, con el ano: 0001-2026.
            $table->string('numero', 20)->unique();

            $table->foreignId('certificacion_formato_id')->nullable()
                ->constrained('certificacion_formatos')->nullOnDelete();

            $table->foreignId('unidad_servicio_id')->nullable()
                ->constrained('unidades_servicio')->nullOnDelete();

            // Cual de las dos redacciones se uso: el libro auxiliar de una
            // unidad, o el de hojas movibles de la Direccion.
            $table->boolean('libro_auxiliar')->default(true);

            $table->string('libro_registro', 40)->nullable();
            $table->string('libro_folio', 20)->nullable();

            // El documento tal como salio impreso.
            $table->text('apertura');
            $table->text('parrafo_libro');
            $table->text('cierre');

            $table->string('firmante_nombre', 120);
            $table->string('firmante_cargo', 120);
            $table->string('vobo_nombre', 120);
            $table->string('vobo_cargo', 120);
            $table->string('institucion', 160);

            $table->foreignId('emitida_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('certificacion_bienes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('certificacion_id')
                ->constrained('certificaciones')->cascadeOnDelete();

            // Si el bien se elimina, la certificacion conserva su texto: lo que
            // se certifico ya se entrego en papel.
            $table->foreignId('bien_id')->nullable()
                ->constrained('bienes')->nullOnDelete();

            $table->unsignedSmallInteger('orden');
            $table->text('texto');

            $table->timestamps();

            $table->index(['certificacion_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificacion_bienes');
        Schema::dropIfExists('certificaciones');
    }
};
