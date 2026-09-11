<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El formato nuevo de 2026 rotula las columnas en la fila 10 y deja las
     * filas 11 a 37 para los bienes: 27 renglones por hoja, no 25.
     *
     * Solo cambia el valor por omision. Las tarjetas que ya existen conservan su
     * capacidad a proposito: si a una tarjeta con hojas impresas se le cambia el
     * tope, los renglones que todavia no salieron se recorren de hoja y dejarian
     * de calzar con el papel que la persona ya firmo.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE tarjetas ALTER COLUMN renglones_por_hoja SET DEFAULT 27');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tarjetas ALTER COLUMN renglones_por_hoja SET DEFAULT 25');
    }
};
