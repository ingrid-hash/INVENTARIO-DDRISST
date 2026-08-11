<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quien responde por cada bien, con historial completo.
     *
     * Una fila por periodo de custodia: si un bien pasa de una persona a otra,
     * la primera fila se cierra y nace otra. Asi se puede contestar "quien lo
     * tenia en 2024", que es lo que pide una auditoria.
     */
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bien_id')
                ->constrained('bienes')->cascadeOnDelete();

            $table->foreignId('empleado_id')
                ->constrained('empleados')->cascadeOnDelete();

            $table->foreignId('tarjeta_id')->nullable()
                ->constrained('tarjetas')->nullOnDelete();

            $table->date('fecha_asignacion');
            $table->date('fecha_devolucion')->nullable();

            $table->boolean('activa')->default(true);

            // baja | traslado | cambio_responsable | correccion
            $table->string('motivo_cierre', 30)->nullable();

            $table->text('observaciones')->nullable();

            $table->foreignId('registrado_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['empleado_id', 'activa']);
            $table->index(['bien_id', 'fecha_asignacion']);
        });

        // LA REGLA CENTRAL DEL SISTEMA: un bien solo puede tener una asignacion
        // activa. El historial se conserva porque las filas cerradas tienen
        // activa = false y el indice parcial las ignora.
        //
        // Se declara en la base de datos y no en la aplicacion para que sea
        // imposible saltarsela, incluso desde una importacion masiva o dos
        // usuarios trabajando al mismo tiempo.
        DB::statement(
            'CREATE UNIQUE INDEX asignaciones_bien_activa_unica
                ON asignaciones (bien_id)
             WHERE activa'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
