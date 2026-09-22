<?php

namespace Database\Seeders;

use App\Models\CertificacionFormato;
use App\Services\CertificacionService;
use Illuminate\Database\Seeder;

class CertificacionFormatosSeeder extends Seeder
{
    /**
     * Las cuatro combinaciones de firma del documento oficial.
     *
     * Los cargos van tal como estan escritos en el formato de la institucion,
     * incluso donde no concuerdan en genero con el nombre: el papel se imprime
     * asi y no corresponde corregirlo desde aqui.
     *
     * @var array<int, array<string, string>>
     */
    private const FORMATOS = [
        [
            'nombre' => 'Encargada de Inventarios · Vo.Bo. Jefe Administrativo Financiero',
            'cargo_apertura' => 'ENCARGADA DE INVENTARIOS',
            'genero' => 'f',
            'firmante_nombre' => 'Mónica Aldina Alvarado de León',
            'firmante_cargo' => 'Encargado de Inventarios',
            'vobo_nombre' => 'Aldo Alberto de León Garzona',
            'vobo_cargo' => 'Jefe del Departamento Administrativo Financiero',
        ],
        [
            'nombre' => 'Encargado a.i. de Inventarios · Vo.Bo. Jefe Administrativo Financiero',
            'cargo_apertura' => 'ENCARGADO a.i. DE INVENTARIOS',
            'genero' => 'm',
            'firmante_nombre' => 'Freddy Adonai Barrios de León',
            'firmante_cargo' => 'Encargado de Inventarios a.i.',
            'vobo_nombre' => 'Aldo Alberto de León Garzona',
            'vobo_cargo' => 'Jefe del Departamento Administrativo Financiero',
        ],
        [
            'nombre' => 'Encargada de Inventarios · Vo.Bo. Contador',
            'cargo_apertura' => 'ENCARGADA DE INVENTARIOS',
            'genero' => 'f',
            'firmante_nombre' => 'Mónica Aldina Alvarado de León',
            'firmante_cargo' => 'Encargado de Inventarios',
            'vobo_nombre' => 'Giovanni Fernando Ovalle Loarca',
            'vobo_cargo' => 'Contador',
        ],
        [
            'nombre' => 'Auxiliar de Inventarios · Vo.Bo. Contador',
            'cargo_apertura' => 'AUXILIAR DE INVENTARIOS',
            'genero' => 'm',
            'firmante_nombre' => 'Jose Luis Pu Sic',
            'firmante_cargo' => 'Auxiliar de Inventarios',
            'vobo_nombre' => 'Giovanni Fernando Ovalle Loarca',
            'vobo_cargo' => 'Contador',
        ],
    ];

    public function run(): void
    {
        foreach (self::FORMATOS as $orden => $formato) {
            // firstOrCreate y no updateOrCreate: si en la institucion ya
            // cambiaron un nombre desde la pantalla, volver a sembrar no debe
            // devolverlo al del documento original.
            CertificacionFormato::firstOrCreate(
                ['nombre' => $formato['nombre']],
                $formato + [
                    'institucion' => CertificacionService::INSTITUCION,
                    'orden' => $orden + 1,
                    'activo' => true,
                    'predeterminado' => true,
                ],
            );
        }

        $this->command?->info('Formatos de certificación sembrados: '.count(self::FORMATOS));
    }
}
