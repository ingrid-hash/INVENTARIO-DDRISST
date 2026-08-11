<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->unique()->after('id');
            $table->string('dpi', 13)->nullable()->after('name');
            $table->string('puesto')->nullable()->after('dpi');
            $table->string('unidad')->nullable()->after('puesto');
            $table->string('telefono', 20)->nullable()->after('unidad');

            $table->boolean('activo')->default(true);
            $table->boolean('debe_cambiar_password')->default(false);
            $table->timestamp('password_cambiado_en')->nullable();

            $table->unsignedSmallInteger('intentos_fallidos')->default(0);
            $table->timestamp('bloqueado_hasta')->nullable();

            $table->timestamp('ultimo_acceso_en')->nullable();
            $table->string('ultimo_acceso_ip', 45)->nullable();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'dpi',
                'puesto',
                'unidad',
                'telefono',
                'activo',
                'debe_cambiar_password',
                'password_cambiado_en',
                'intentos_fallidos',
                'bloqueado_hasta',
                'ultimo_acceso_en',
                'ultimo_acceso_ip',
                'deleted_at',
            ]);
        });
    }
};
