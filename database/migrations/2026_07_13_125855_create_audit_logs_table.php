<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitacora general del sistema: accesos, intentos fallidos, bloqueos,
     * cambios de contrasena y movimientos sobre usuarios, roles y permisos.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_email')->nullable();

            $table->string('evento', 60);
            $table->string('descripcion')->nullable();

            $table->string('modelo')->nullable();
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->json('datos')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['evento', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
