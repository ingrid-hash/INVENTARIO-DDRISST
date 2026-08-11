<?php

namespace Database\Seeders;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    /**
     * Cuentas iniciales del sistema.
     *
     * IMPORTANTE: estas contrasenas son solo para la puesta en marcha. La
     * primera tarea en el servidor real es cambiarlas y borrar las cuentas de
     * demostracion (admin.prueba y usuario.prueba).
     */
    public function run(): void
    {
        $superadmin = $this->crear(
            username: 'superadmin',
            name: 'Superadministrador del Sistema',
            email: 'superadmin@mspas.gob.gt',
            password: 'Mspas.2026$Super',
            rol: 'Superadmin',
            puesto: 'Administrador de Tecnología',
            unidad: 'DDRISST',
            debeCambiar: false,
        );

        $this->crear(
            username: 'admin.prueba',
            name: 'Administrador de Inventario',
            email: 'admin@mspas.gob.gt',
            password: 'Mspas.2026$Admin',
            rol: 'Admin',
            puesto: 'Encargado de Inventario',
            unidad: 'DDRISST',
            debeCambiar: true,
        );

        $this->crear(
            username: 'usuario.prueba',
            name: 'Usuario de Consulta',
            email: 'usuario@mspas.gob.gt',
            password: 'Mspas.2026$User',
            rol: 'Usuario',
            puesto: 'Auxiliar',
            unidad: 'Centro de Salud',
            debeCambiar: true,
        );

        $this->command->info("Superadmin creado: {$superadmin->username} / Mspas.2026\$Super");
    }

    private function crear(
        string $username,
        string $name,
        string $email,
        string $password,
        string $rol,
        string $puesto,
        string $unidad,
        bool $debeCambiar,
    ): User {
        $usuario = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'puesto' => $puesto,
                'unidad' => $unidad,
                'activo' => true,
                'debe_cambiar_password' => $debeCambiar,
                'password_cambiado_en' => now(),
            ],
        );

        $usuario->syncRoles([$rol]);

        PasswordHistory::firstOrCreate(
            ['user_id' => $usuario->id, 'motivo' => 'inicial'],
            ['password_hash' => $usuario->password, 'ip' => '127.0.0.1'],
        );

        return $usuario;
    }
}
