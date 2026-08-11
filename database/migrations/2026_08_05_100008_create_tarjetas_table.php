<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarjeta de responsabilidad, en el formato de la Contraloria General de
     * Cuentas. Una por empleado.
     *
     * Se versiona: cuando se autoriza una baja o se quita un bien, la tarjeta
     * vigente pasa a "reemplazada" y nace una version nueva sin ese bien. La
     * anterior se conserva porque es el documento que la persona firmo.
     */
    public function up(): void
    {
        Schema::create('tarjetas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empleado_id')
                ->constrained('empleados')->cascadeOnDelete();

            $table->foreignId('unidad_servicio_id')
                ->constrained('unidades_servicio')->cascadeOnDelete();

            // Numero oficial de la tarjeta: "No. 69"
            $table->string('numero', 30)->nullable();

            $table->unsignedSmallInteger('version')->default(1);

            $table->foreignId('reemplaza_a')->nullable()
                ->constrained('tarjetas')->nullOnDelete();

            // vigente | reemplazada
            $table->string('estado', 20)->default('vigente');

            $table->date('fecha_apertura')->nullable();
            $table->decimal('saldo_total', 14, 2)->default(0);

            // Cuantos renglones caben por hoja segun el formato impreso. Es
            // configurable porque el tope real depende del largo de las
            // descripciones: 25 es el maximo observado en los archivos.
            $table->unsignedSmallInteger('renglones_por_hoja')->default(25);

            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'estado']);
            $table->index(['unidad_servicio_id', 'estado']);
        });

        // Un empleado no puede tener dos tarjetas vigentes al mismo tiempo.
        // Las versiones anteriores quedan como "reemplazada" y no estorban.
        DB::statement(
            "CREATE UNIQUE INDEX tarjetas_empleado_vigente_unica
                ON tarjetas (empleado_id)
             WHERE estado = 'vigente'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tarjetas');
    }
};
