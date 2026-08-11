<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogo maestro de bienes. El codigo de inventario es la llave del
     * sistema completo y es unico a nivel global.
     *
     * Conviven dos formatos de codigo, y los dos son oficiales:
     *   - el viejo, hexadecimal de 8 caracteres     -> 0033C31E
     *   - el nuevo, con anio y unidad               -> 2026-211-CHI-0013
     */
    public function up(): void
    {
        Schema::create('bienes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 40)->unique();
            $table->text('descripcion');

            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->foreignId('unidad_servicio_id')
                ->constrained('unidades_servicio')->cascadeOnDelete();

            // La cuenta queda opcional a proposito: en los archivos historicos
            // esta anotada de forma muy esporadica y no se completa hacia atras.
            // Para toda adicion nueva el sistema la exige.
            $table->foreignId('renglon_id')->nullable()
                ->constrained('renglones')->nullOnDelete();

            // La celda CUENTA tal como venia del Excel. Permite reimprimir la
            // tarjeta identica al papel ya firmado, aunque el dato este
            // incompleto o mezclado (1232.03 + ADICION, COMPRA, CRECER SANO...).
            $table->text('cuenta_texto_original')->nullable();

            // apertura (venia en el inventario inicial) | adicion (bien nuevo)
            $table->string('tipo_movimiento', 20)->default('apertura');

            // compra | donacion. Una adicion siempre es una de las dos.
            $table->string('forma_adquisicion', 20)->nullable();

            // Programa que financio la donacion: CRECER SANO, VIH, etc.
            // Texto libre, con sugerencias de lo ya capturado.
            $table->string('programa')->nullable();

            // Oficio o documento de respaldo: S/OF.020-2025
            $table->string('documento_respaldo')->nullable();

            // En los archivos la fecha a veces es completa (21/08/2024) y a
            // veces solo el anio (2018). Se conserva el texto original para
            // reimprimir igual, y la fecha interpretada para poder consultar.
            $table->string('fecha_texto_original', 40)->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->unsignedSmallInteger('anio_ingreso')->nullable();

            // activo | baja_solicitada | baja
            // Mientras la baja esta solicitada el bien sigue en la tarjeta del
            // empleado y sigue sumando al saldo.
            $table->string('estado', 20)->default('activo');

            $table->text('observaciones')->nullable();

            $table->foreignId('importacion_id')->nullable()
                ->constrained('importaciones')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['unidad_servicio_id', 'estado']);
            $table->index(['renglon_id', 'estado']);
            $table->index(['tipo_movimiento', 'anio_ingreso']);
        });

        // Indice de texto completo para buscar el bien por su descripcion, que
        // es la consulta mas frecuente del sistema: armar una tarjeta buscando
        // "balanza" o "camilla" en vez de recordar el codigo.
        DB::statement(
            "CREATE INDEX bienes_descripcion_busqueda ON bienes USING gin (to_tsvector('spanish', descripcion))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bienes');
    }
};
