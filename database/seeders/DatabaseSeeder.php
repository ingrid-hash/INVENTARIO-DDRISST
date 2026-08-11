<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UsuariosSeeder::class,
            RenglonesSeeder::class,
            UnidadesServicioSeeder::class,
        ]);
    }
}
