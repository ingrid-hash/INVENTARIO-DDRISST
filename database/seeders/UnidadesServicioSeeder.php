<?php

namespace Database\Seeders;

use App\Models\UnidadServicio;
use Illuminate\Database\Seeder;

class UnidadesServicioSeeder extends Seeder
{
    /**
     * Unidades que aparecen en los archivos entregados, con su jerarquia.
     *
     * El Puesto de Salud de Chinimabe pertenece al municipio de Momostenango,
     * pero lleva su inventario aparte del centro de salud: sus bienes no se
     * repiten con los de aquel. La jerarquia permite pedir un reporte del
     * distrito y recibir tambien lo de sus puestos.
     */
    public function run(): void
    {
        $area = UnidadServicio::updateOrCreate(
            ['codigo' => '211'],
            [
                'padre_id' => null,
                'nombre' => 'Dirección de Área de Salud de Totonicapán',
                'tipo' => 'area',
                'municipio' => 'Totonicapán',
                'departamento' => 'Totonicapán',
                'activo' => true,
            ],
        );

        $momostenango = UnidadServicio::updateOrCreate(
            ['codigo' => '211-MOM'],
            [
                'padre_id' => $area->id,
                'nombre' => 'Centro de Salud de Momostenango',
                'tipo' => 'centro_salud',
                'municipio' => 'Momostenango',
                'departamento' => 'Totonicapán',
                'activo' => true,
            ],
        );

        UnidadServicio::updateOrCreate(
            ['codigo' => '211-CHI'],
            [
                'padre_id' => $momostenango->id,
                'nombre' => 'Puesto de Salud de Chinimabe',
                'tipo' => 'puesto_salud',
                'municipio' => 'Momostenango',
                'departamento' => 'Totonicapán',
                'activo' => true,
            ],
        );

        $this->command->info('Unidades de servicio sembradas: 3 (área, centro, puesto)');
    }
}
