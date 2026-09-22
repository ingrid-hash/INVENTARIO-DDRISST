<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Combinaciones de firma de la certificacion.
     *
     * El documento oficial trae cuatro, y lo unico que cambia entre ellas es la
     * linea de apertura y los dos bloques de firma; el cuerpo es el mismo. Se
     * guardan en tabla y no en el programa porque los encargados cambian: al
     * nombrar a otra persona, o al firmar alguien distinto el visto bueno, hay
     * que poder agregar la combinacion sin tocar el sistema.
     */
    public function up(): void
    {
        Schema::create('certificacion_formatos', function (Blueprint $table) {
            $table->id();

            // Lo que se lee al elegir el formato en pantalla.
            $table->string('nombre', 120);

            // El cargo tal como se escribe en la linea de apertura, en
            // mayusculas: "ENCARGADA DE INVENTARIOS", "ENCARGADO a.i. DE
            // INVENTARIOS", "AUXILIAR DE INVENTARIOS".
            $table->string('cargo_apertura', 120);

            // Define si la apertura dice LA INFRASCRITA o EL INFRASCRITO.
            $table->char('genero', 1)->default('f');

            $table->string('firmante_nombre', 120);
            $table->string('firmante_cargo', 120);

            $table->string('vobo_nombre', 120);
            $table->string('vobo_cargo', 120);

            // Las dos lineas que van debajo de cada nombre.
            $table->string('institucion', 160);

            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            // Las cuatro que vienen del documento oficial. No se borran, para
            // que el formato de la institucion siempre este disponible.
            $table->boolean('predeterminado')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificacion_formatos');
    }
};
