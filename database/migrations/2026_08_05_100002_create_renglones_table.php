<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renglones presupuestarios, las "cuentas" del inventario.
     *
     * En la tarjeta de responsabilidad se imprime solo el codigo (1232.03);
     * en el listado general se imprime el codigo y el nombre. El catalogo se
     * captura a mano y la importacion unicamente busca por codigo.
     */
    public function up(): void
    {
        Schema::create('renglones', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 12)->unique();
            $table->string('nombre');

            // Controla el orden en que salen los grupos del listado general.
            $table->unsignedSmallInteger('orden')->default(0);

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renglones');
    }
};
