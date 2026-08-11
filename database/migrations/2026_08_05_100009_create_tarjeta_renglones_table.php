<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada linea impresa de la tarjeta.
     *
     * Existe aparte de "asignaciones" porque responden preguntas distintas:
     * asignaciones dice quien responde hoy por el bien; esta tabla dice como
     * se ve el documento en papel, con su orden y su hoja fisica.
     */
    public function up(): void
    {
        Schema::create('tarjeta_renglones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tarjeta_id')
                ->constrained('tarjetas')->cascadeOnDelete();

            $table->foreignId('bien_id')
                ->constrained('bienes')->cascadeOnDelete();

            // El orden del documento, tal como viene del Excel. Las adiciones
            // entran al final: orden = ultimo + 1. Nunca se reordena.
            $table->unsignedInteger('orden');

            $table->decimal('debe', 14, 2)->default(0);
            $table->decimal('haber', 14, 2)->default(0);

            // Saldo corrido que se imprime en la columna SALDO y que alimenta
            // los cortes VAN / VIENEN al cambiar de hoja.
            $table->decimal('saldo', 14, 2)->nullable();

            // En que hoja de papel quedo impreso este renglon. Se registra al
            // imprimir en vez de calcularse, porque la capacidad real depende
            // del largo de la descripcion. Impar = frente, par = reverso.
            $table->unsignedSmallInteger('hoja_fisica')->nullable();
            $table->timestamp('impreso_at')->nullable();

            $table->string('observaciones')->nullable();
            $table->timestamps();

            // El mismo bien no puede figurar dos veces en la misma tarjeta.
            $table->unique(['tarjeta_id', 'bien_id']);

            // Y no puede haber dos renglones en la misma posicion.
            $table->unique(['tarjeta_id', 'orden']);

            $table->index(['tarjeta_id', 'hoja_fisica']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarjeta_renglones');
    }
};
