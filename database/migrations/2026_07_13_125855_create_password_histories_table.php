<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda los hashes anteriores de cada usuario. Cumple dos funciones:
     * impedir que se reutilice una contrasena reciente y dejar constancia
     * de quien realizo cada cambio.
     */
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cambiado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->string('password_hash');

            // inicial        -> alta del usuario
            // auto_cambio    -> el propio usuario la cambio
            // reset_admin    -> un administrador la restablecio
            // cambio_forzado -> cambio obligatorio tras un reset
            $table->string('motivo', 20);

            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
