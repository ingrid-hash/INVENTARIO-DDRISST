<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasar de contar renglones a medir milimetros.
     *
     * La hoja se repartia contando bienes: veintisiete por papel. Pero un bien
     * cuya descripcion ocupa seis lineas gasta seis veces lo que uno de una, asi
     * que el reparto no se correspondia con el papel y la hoja se desbordaba.
     *
     * Se guardan dos cosas nuevas:
     *
     *  - El alto de cada renglon, que se congela cuando la hoja sale impresa.
     *    Si despues alguien corrige una descripcion, las lineas de ese papel no
     *    se mueven y lo que se imprima encima sigue cayendo donde debe.
     *
     *  - La escala de cada hoja: cuanto hay que apretar el texto para que cierre
     *    sin pasarse, como el 75 % que la institucion le pone al archivo de
     *    Excel. Es por hoja porque el papel ya impreso manda sobre lo que sigue.
     */
    public function up(): void
    {
        Schema::table('tarjeta_renglones', function (Blueprint $table) {
            $table->decimal('alto_mm', 6, 3)->nullable()->after('hoja_fisica');
            $table->unsignedSmallInteger('lineas')->nullable()->after('alto_mm');
        });

        Schema::table('tarjeta_hojas', function (Blueprint $table) {
            $table->decimal('escala', 4, 3)->default(1)->after('desfase_y_mm');
        });
    }

    public function down(): void
    {
        Schema::table('tarjeta_renglones', function (Blueprint $table) {
            $table->dropColumn(['alto_mm', 'lineas']);
        });

        Schema::table('tarjeta_hojas', function (Blueprint $table) {
            $table->dropColumn('escala');
        });
    }
};
