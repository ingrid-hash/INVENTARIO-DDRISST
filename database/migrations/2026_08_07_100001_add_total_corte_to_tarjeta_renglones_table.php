<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El TOTAL que cierra cada adicion en la tarjeta.
     *
     * El sistema sabe calcularlo (es el saldo corrido al cerrar el grupo), pero
     * en las tarjetas que ya estan impresas y firmadas manda el papel: si el
     * archivo trae un TOTAL escrito se guarda tal cual y es ese el que se
     * reimprime, aunque no cuadre con la suma. De las adiciones nuevas en
     * adelante la columna queda nula y el total lo calcula el sistema.
     */
    public function up(): void
    {
        Schema::table('tarjeta_renglones', function (Blueprint $table) {
            $table->decimal('total_corte_original', 14, 2)->nullable()->after('saldo');
        });
    }

    public function down(): void
    {
        Schema::table('tarjeta_renglones', function (Blueprint $table) {
            $table->dropColumn('total_corte_original');
        });
    }
};
