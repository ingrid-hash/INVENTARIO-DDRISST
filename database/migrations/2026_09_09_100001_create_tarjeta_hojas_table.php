<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de cada hoja de papel de una tarjeta.
     *
     * Hasta ahora esto se calculaba al vuelo a partir de los renglones, y por
     * eso no habia donde guardar dos cosas que el papel si tiene:
     *
     *  - El calce: cuantos milimetros hay que correr la impresion para que la
     *    tinta caiga en los espacios libres de una hoja que ya salio impresa.
     *    Cada papel se alimenta distinto, asi que el desfase es por hoja.
     *
     *  - Si la hoja esta cerrada. La linea de VAN se escribe cuando la hoja se
     *    cierra, no antes: mientras quede espacio se siguen agregando bienes, y
     *    un VAN impreso de mas dejaria un saldo en el papel que ya no cuadra.
     */
    public function up(): void
    {
        Schema::create('tarjeta_hojas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tarjeta_id')
                ->constrained('tarjetas')->cascadeOnDelete();

            // Numero de hoja dentro de la tarjeta: 1 es el frente del papel 1.
            $table->unsignedSmallInteger('numero');

            // Desfase de impresion en milimetros. Positivo = hacia la derecha y
            // hacia abajo. Se guarda con un decimal porque el ajuste fino que
            // hace falta en la practica es de medio milimetro.
            $table->decimal('desfase_x_mm', 4, 1)->default(0);
            $table->decimal('desfase_y_mm', 4, 1)->default(0);

            // Cerrada: ya no admite bienes nuevos y se le imprime el VAN.
            $table->timestamp('cerrada_at')->nullable();

            // La primera vez que esta hoja salio de la impresora.
            $table->timestamp('impresa_at')->nullable();

            $table->timestamps();

            $table->unique(['tarjeta_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarjeta_hojas');
    }
};
