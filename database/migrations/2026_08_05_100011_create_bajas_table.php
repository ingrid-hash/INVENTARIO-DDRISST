<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expediente de baja de un bien.
     *
     * La baja es un tramite, no un borrado. Mientras esta solicitada el bien
     * sigue en la tarjeta del empleado y sigue sumando al saldo; solo al
     * autorizarse se cierra la asignacion y se regenera la tarjeta sin el bien.
     */
    public function up(): void
    {
        Schema::create('bajas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bien_id')
                ->constrained('bienes')->cascadeOnDelete();

            $table->string('numero_acta', 60)->nullable();
            $table->string('motivo');

            $table->date('fecha_solicitud');
            $table->date('fecha_resolucion')->nullable();

            // solicitada | autorizada | rechazada
            $table->string('estado', 20)->default('solicitada');

            $table->foreignId('solicitada_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->foreignId('resuelta_por')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_solicitud']);
        });

        // Un bien no puede tener dos bajas en tramite a la vez. Los expedientes
        // ya resueltos no estorban, y un bien rechazado puede volver a
        // solicitarse mas adelante.
        DB::statement(
            "CREATE UNIQUE INDEX bajas_bien_en_tramite_unica
                ON bajas (bien_id)
             WHERE estado = 'solicitada'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bajas');
    }
};
