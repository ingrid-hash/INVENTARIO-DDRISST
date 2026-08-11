<?php

namespace Database\Seeders;

use App\Models\Asignacion;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Empleado;
use App\Models\Renglon;
use App\Models\Tarjeta;
use App\Models\TarjetaRenglon;
use App\Models\UnidadServicio;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de verificacion: ejercita el modelo con datos reales tomados del
 * archivo de Chinimabe y comprueba que las reglas de negocio se cumplan a
 * nivel de base de datos. No deja nada: todo corre en una transaccion que se
 * revierte al final.
 *
 * Se ejecuta a mano con:  php artisan db:seed --class=PruebaReglasSeeder
 */
class PruebaReglasSeeder extends Seeder
{
    private int $pasadas = 0;

    private int $fallidas = 0;

    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->ejecutar();
        } finally {
            DB::rollBack();
            $this->command->newLine();
            $this->command->info('Todo revertido: la base queda como estaba.');
        }

        $this->command->newLine();
        $this->command->line(sprintf('RESULTADO: %d correctas, %d fallidas', $this->pasadas, $this->fallidas));
    }

    private function ejecutar(): void
    {
        $chinimabe = UnidadServicio::where('codigo', '211-CHI')->firstOrFail();
        $momos = UnidadServicio::where('codigo', '211-MOM')->firstOrFail();
        $area = UnidadServicio::where('codigo', '211')->firstOrFail();
        $mobiliario = Renglon::where('codigo', '1232.03')->firstOrFail();
        $medico = Renglon::where('codigo', '1232.04')->firstOrFail();

        // --- Empleados reales de la tarjeta de Chinimabe
        $selina = Empleado::create([
            'unidad_servicio_id' => $chinimabe->id,
            'nombre_completo' => 'SELINA ELVIRA PACHECO YAX',
            'cargo' => 'PARAMEDICO I',
            'area_trabajo' => 'ENFERMERIA',
        ]);

        $alba = Empleado::create([
            'unidad_servicio_id' => $chinimabe->id,
            'nombre_completo' => 'ALBA ROSAURA TORRES IXCAYAU',
            'cargo' => 'PROFESIONAL I',
            'area_trabajo' => 'ENFERMERIA',
        ]);

        // --- Bienes reales, con la columna CUENTA tal como viene del Excel
        $escritorio = Bien::create([
            'codigo' => '001CFC89',
            'descripcion' => 'Escritorio de metal de 2 gavetas color gris, con formica color cafe',
            'precio_unitario' => 300.00,
            'total' => 300.00,
            'unidad_servicio_id' => $chinimabe->id,
            'cuenta_texto_original' => null,
            'fecha_texto_original' => '21/08/2024',
            'fecha_ingreso' => '2024-08-21',
            'anio_ingreso' => 2024,
        ]);

        $refrigeradora = Bien::create([
            'codigo' => '2026-211-CHI-0026',
            'descripcion' => 'Refrigeradora marca CETRON de una puerta',
            'precio_unitario' => 1200.00,
            'total' => 1200.00,
            'unidad_servicio_id' => $chinimabe->id,
            'renglon_id' => $medico->id,
            'cuenta_texto_original' => 'DONACION',
            'forma_adquisicion' => 'donacion',
            'programa' => 'CRECER SANO',
            'fecha_texto_original' => '2017',
            'anio_ingreso' => 2017,
        ]);

        $impresora = Bien::create([
            'codigo' => '000C31FA',
            'descripcion' => 'Impresora HP Laser 1200 Modelo C7044A, serie No. CNCCB792120',
            'precio_unitario' => 3341.91,
            'total' => 3341.91,
            'unidad_servicio_id' => $chinimabe->id,
            'renglon_id' => $mobiliario->id,
            'tipo_movimiento' => 'adicion',
            'forma_adquisicion' => 'compra',
            'documento_respaldo' => 'S/OF.020-2025',
            'fecha_texto_original' => '07/08/2025',
            'fecha_ingreso' => '2025-08-07',
            'anio_ingreso' => 2025,
        ]);

        $this->titulo('1. Codigo unico global');
        $this->debeFallar(
            'un segundo bien con el codigo 001CFC89',
            fn () => Bien::create([
                'codigo' => '001CFC89',
                'descripcion' => 'Duplicado que no debe entrar',
                'unidad_servicio_id' => $momos->id,
            ]),
        );

        // --- Tarjeta de Selina
        $tarjeta = Tarjeta::create([
            'empleado_id' => $selina->id,
            'unidad_servicio_id' => $chinimabe->id,
            'numero' => 'No. 69',
            'fecha_apertura' => '2024-01-22',
        ]);

        $this->titulo('2. Una sola tarjeta vigente por empleado');
        $this->debeFallar(
            'una segunda tarjeta vigente para Selina',
            fn () => Tarjeta::create([
                'empleado_id' => $selina->id,
                'unidad_servicio_id' => $chinimabe->id,
            ]),
        );

        $tarjeta->update(['estado' => Tarjeta::ESTADO_REEMPLAZADA]);
        $this->debePasar(
            'una tarjeta nueva cuando la anterior quedo reemplazada',
            fn () => Tarjeta::create([
                'empleado_id' => $selina->id,
                'unidad_servicio_id' => $chinimabe->id,
                'version' => 2,
                'reemplaza_a' => $tarjeta->id,
            ]),
        );

        $vigente = $selina->tarjetaVigente()->firstOrFail();
        $this->afirmar('la tarjeta vigente de Selina es la version 2', $vigente->version === 2);

        $this->titulo('3. Un bien, un solo responsable');
        $this->debePasar(
            'asignar el escritorio a Selina',
            fn () => Asignacion::create([
                'bien_id' => $escritorio->id,
                'empleado_id' => $selina->id,
                'tarjeta_id' => $vigente->id,
                'fecha_asignacion' => '2024-08-21',
            ]),
        );

        $this->debeFallar(
            'asignar el MISMO escritorio tambien a Alba',
            fn () => Asignacion::create([
                'bien_id' => $escritorio->id,
                'empleado_id' => $alba->id,
                'fecha_asignacion' => '2025-01-10',
            ]),
        );

        // Traslado correcto: se cierra la primera y se abre la segunda.
        Asignacion::where('bien_id', $escritorio->id)->where('activa', true)->update([
            'activa' => false,
            'fecha_devolucion' => '2025-01-09',
            'motivo_cierre' => 'cambio_responsable',
        ]);

        $this->debePasar(
            'trasladar el escritorio a Alba despues de cerrar la asignacion anterior',
            fn () => Asignacion::create([
                'bien_id' => $escritorio->id,
                'empleado_id' => $alba->id,
                'fecha_asignacion' => '2025-01-10',
            ]),
        );

        $this->afirmar(
            'el escritorio conserva su historial: 2 asignaciones, 1 vigente',
            $escritorio->asignaciones()->count() === 2
                && $escritorio->asignacionVigente()->exists()
                && $escritorio->asignacionVigente->empleado_id === $alba->id,
        );

        $this->titulo('4. Orden del documento y adiciones al final');
        foreach ([$refrigeradora, $impresora] as $bien) {
            TarjetaRenglon::create([
                'tarjeta_id' => $vigente->id,
                'bien_id' => $bien->id,
                'orden' => $vigente->siguienteOrden(),
                'debe' => $bien->total,
            ]);
        }

        $ordenes = $vigente->renglones()->pluck('orden')->all();
        $this->afirmar('los renglones quedaron en orden 1, 2', $ordenes === [1, 2]);

        $this->debeFallar(
            'dos renglones en la misma posicion de la tarjeta',
            fn () => TarjetaRenglon::create([
                'tarjeta_id' => $vigente->id,
                'bien_id' => $escritorio->id,
                'orden' => 1,
            ]),
        );

        $this->debeFallar(
            'el mismo bien dos veces en la misma tarjeta',
            fn () => TarjetaRenglon::create([
                'tarjeta_id' => $vigente->id,
                'bien_id' => $impresora->id,
                'orden' => 99,
            ]),
        );

        $this->titulo('5. Columna CUENTA: historico literal vs adicion armada');
        $this->afirmar(
            'la refrigeradora imprime su celda original: DONACION',
            $refrigeradora->lineasColumnaCuenta() === ['DONACION'],
        );
        $this->afirmar(
            'la impresora arma 1232.03 + ADICION + S/OF.020-2025',
            $impresora->lineasColumnaCuenta() === ['1232.03', 'ADICION', 'S/OF.020-2025'],
        );

        $this->titulo('6. Paginacion a doble cara');
        $this->afirmar('la hoja 1 es el frente del papel 1', Tarjeta::caraDeHoja(1) === 'frente' && Tarjeta::papelDeHoja(1) === 1);
        $this->afirmar('la hoja 2 es el reverso del papel 1', Tarjeta::caraDeHoja(2) === 'reverso' && Tarjeta::papelDeHoja(2) === 1);
        $this->afirmar('la hoja 5 es el frente del papel 3', Tarjeta::caraDeHoja(5) === 'frente' && Tarjeta::papelDeHoja(5) === 3);

        $vigente->renglones()->update(['hoja_fisica' => 1, 'impreso_at' => now()]);
        $this->afirmar(
            'con 2 de 25 renglones impresos, quedan 23 libres en la hoja 1',
            $vigente->fresh()->espacioEnUltimaHoja() === 23,
        );

        $this->titulo('7. La baja es un tramite, no un borrado');
        $baja = Baja::create([
            'bien_id' => $refrigeradora->id,
            'motivo' => 'Equipo en mal estado, no reparable',
            'fecha_solicitud' => now()->toDateString(),
            'numero_acta' => 'ACTA-2026-014',
        ]);
        $refrigeradora->update(['estado' => Bien::ESTADO_BAJA_SOLICITADA]);

        $this->debeFallar(
            'una segunda baja en tramite para la misma refrigeradora',
            fn () => Baja::create([
                'bien_id' => $refrigeradora->id,
                'motivo' => 'Solicitud repetida',
                'fecha_solicitud' => now()->toDateString(),
            ]),
        );

        $this->afirmar(
            'con la baja en tramite el bien SIGUE en la tarjeta de Selina',
            $vigente->renglones()->where('bien_id', $refrigeradora->id)->exists(),
        );

        $baja->update(['estado' => Baja::ESTADO_AUTORIZADA, 'fecha_resolucion' => now()->toDateString()]);
        $refrigeradora->update(['estado' => Bien::ESTADO_BAJA]);

        $this->debePasar(
            'solicitar otra baja despues de que la anterior se resolvio',
            fn () => Baja::create([
                'bien_id' => $escritorio->id,
                'motivo' => 'Otro bien, otro expediente',
                'fecha_solicitud' => now()->toDateString(),
            ]),
        );

        $this->titulo('8. Reportes por jerarquia de unidades');
        $this->afirmar(
            'el area incluye a sus 3 unidades',
            count($area->idsConDescendientes()) === 3,
        );
        $this->afirmar(
            'Momostenango incluye a Chinimabe',
            in_array($chinimabe->id, $momos->idsConDescendientes(), true),
        );
        $this->afirmar(
            'consultando desde el area se ven los 3 bienes de Chinimabe',
            Bien::deUnidadConDescendientes($area)->count() === 3,
        );

        $this->titulo('9. Consultas que va a usar el sistema');
        $this->afirmar(
            'buscar bien por codigo parcial encuentra la impresora',
            Bien::buscar('000C31')->pluck('codigo')->contains('000C31FA'),
        );
        $this->afirmar(
            'buscar por descripcion encuentra la refrigeradora',
            Bien::buscar('refriger')->pluck('codigo')->contains('2026-211-CHI-0026'),
        );
        $this->afirmar(
            'el escritorio aparece como bien sin cuenta asignada',
            Bien::sinCuenta()->pluck('codigo')->contains('001CFC89'),
        );
        $this->afirmar(
            'las adiciones de la unidad se pueden listar',
            Bien::where('tipo_movimiento', 'adicion')->where('unidad_servicio_id', $chinimabe->id)->count() === 1,
        );
    }

    // ---------------------------------------------------------------- helpers

    private function titulo(string $texto): void
    {
        $this->command->newLine();
        $this->command->line($texto);
        $this->command->line(str_repeat('-', 74));
    }

    /**
     * En PostgreSQL una sentencia que falla aborta la transaccion completa, asi
     * que cada intento corre dentro de una transaccion anidada. Laravel la
     * traduce a un SAVEPOINT: si la operacion falla se revierte solo ese punto
     * y el resto de las pruebas puede continuar.
     */
    private function debePasar(string $descripcion, callable $accion): void
    {
        try {
            DB::transaction(fn () => $accion());
            $this->ok('permite '.$descripcion);
        } catch (QueryException $e) {
            $this->error('deberia permitir '.$descripcion.' -> '.$this->corto($e));
        }
    }

    private function debeFallar(string $descripcion, callable $accion): void
    {
        try {
            DB::transaction(fn () => $accion());
            $this->error('NO bloqueo '.$descripcion.' (deberia haberlo rechazado)');
        } catch (QueryException) {
            $this->ok('bloquea '.$descripcion);
        }
    }

    private function afirmar(string $descripcion, bool $condicion): void
    {
        $condicion ? $this->ok($descripcion) : $this->error($descripcion);
    }

    private function ok(string $texto): void
    {
        $this->pasadas++;
        $this->command->line('  [ok]    '.$texto);
    }

    private function error(string $texto): void
    {
        $this->fallidas++;
        $this->command->line('  [FALLA] '.$texto);
    }

    private function corto(QueryException $e): string
    {
        return substr(explode("\n", $e->getMessage())[0], 0, 110);
    }
}
