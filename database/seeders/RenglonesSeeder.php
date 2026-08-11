<?php

namespace Database\Seeders;

use App\Models\Renglon;
use Illuminate\Database\Seeder;

class RenglonesSeeder extends Seeder
{
    /**
     * Cuentas del inventario, tomadas de los encabezados de seccion que
     * aparecen en los inventarios de la Direccion de Area y del Centro de
     * Salud de Momostenango. Son los mismos siete en los dos archivos.
     *
     * Se pueden agregar mas desde la pantalla de cuentas.
     *
     * @var array<int, array{codigo: string, nombre: string}>
     */
    private const RENGLONES = [
        ['codigo' => '1232.03', 'nombre' => 'MOBILIARIO Y EQUIPO DE OFICINA'],
        ['codigo' => '1232.04', 'nombre' => 'EQUIPO MEDICO SANITARIO Y DE LABORATORIO'],
        ['codigo' => '1232.05', 'nombre' => 'EQUIPO EDUCACIONAL, CULTURAL Y RECREATIVO'],
        ['codigo' => '1232.06', 'nombre' => 'TRANSPORTE, TRACCION Y ELEVACION'],
        ['codigo' => '1232.07', 'nombre' => 'EQUIPO DE COMUNICACION'],
        ['codigo' => '1232.09', 'nombre' => 'EQUIPO DE COMPUTO'],
        ['codigo' => '1237.00', 'nombre' => 'OTROS ACTIVOS'],
    ];

    public function run(): void
    {
        foreach (self::RENGLONES as $indice => $renglon) {
            Renglon::updateOrCreate(
                ['codigo' => $renglon['codigo']],
                [
                    'nombre' => $renglon['nombre'],
                    'orden' => ($indice + 1) * 10,
                    'activo' => true,
                ],
            );
        }

        $this->command->info('Cuentas sembradas: '.count(self::RENGLONES));
    }
}
