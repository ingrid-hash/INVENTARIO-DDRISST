<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Libro y folio donde esta registrado el bien.
     *
     * Son datos del libro autorizado por la Contraloria, no del bien en si,
     * pero se guardan aqui porque el folio es distinto para cada bien. La
     * primera certificacion los deja escritos y las siguientes ya salen con
     * ellos puestos; al cambiar el ano se corrigen y vuelven a quedar.
     */
    public function up(): void
    {
        Schema::table('bienes', function (Blueprint $table) {
            $table->string('libro_registro', 40)->nullable()->after('observaciones');
            $table->string('libro_folio', 20)->nullable()->after('libro_registro');
        });
    }

    public function down(): void
    {
        Schema::table('bienes', function (Blueprint $table) {
            $table->dropColumn(['libro_registro', 'libro_folio']);
        });
    }
};
