<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Empleado;
use App\Models\Tarjeta;
use App\Models\TarjetaHoja;
use App\Models\TarjetaRenglon;
use App\Models\UnidadServicio;
use App\Services\GeometriaTarjeta;
use App\Services\MedidorTarjeta;
use App\Services\PaginadorTarjeta;
use App\Services\TarjetaService;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TarjetaController extends Controller
{
    public function __construct(
        private readonly TarjetaService $tarjetas,
        private readonly PaginadorTarjeta $paginador,
        private readonly MedidorTarjeta $medidor,
    ) {}

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->string('buscar'));
        $unidad = $request->integer('unidad') ?: null;
        $verReemplazadas = $request->boolean('reemplazadas');

        $tarjetas = Tarjeta::query()
            ->with(['empleado:id,nombre_completo,cargo', 'unidadServicio:id,nombre'])
            ->withCount('renglones')
            ->when(! $verReemplazadas, fn ($q) => $q->vigentes())
            ->when($buscar !== '', fn ($q) => $q->whereHas(
                'empleado',
                fn ($sub) => $sub->where('nombre_completo', 'ilike', "%{$buscar}%")
            ))
            ->when($unidad !== null, fn ($q) => $q->where('unidad_servicio_id', $unidad))
            ->orderByDesc('estado')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Tarjeta $tarjeta) => [
                'id' => $tarjeta->id,
                'numero' => $tarjeta->numero,
                'version' => $tarjeta->version,
                'estado' => $tarjeta->estado,
                'empleado' => $tarjeta->empleado?->nombre_completo,
                'cargo' => $tarjeta->empleado?->cargo,
                'unidad' => $tarjeta->unidadServicio?->nombre,
                'bienes' => $tarjeta->renglones_count,
                'saldo' => (float) $tarjeta->saldo_total,
                'fecha_apertura' => $tarjeta->fecha_apertura?->format('d/m/Y'),
                'pendientes_impresion' => $tarjeta->renglonesPendientesDeImprimir(),
            ]);

        return Inertia::render('inventario/tarjetas/index', [
            'tarjetas' => $tarjetas,
            'filtros' => ['buscar' => $buscar, 'unidad' => $unidad, 'reemplazadas' => $verReemplazadas],
            'unidades' => UnidadServicio::activas()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            // Empleados que todavia no tienen tarjeta abierta.
            'empleadosSinTarjeta' => Empleado::query()
                ->activos()
                ->whereDoesntHave('tarjetaVigente')
                ->with('unidadServicio:id,nombre')
                ->orderBy('nombre_completo')
                ->get()
                ->map(fn (Empleado $e) => [
                    'id' => $e->id,
                    'nombre' => $e->nombre_completo,
                    'cargo' => $e->cargo,
                    'unidad' => $e->unidadServicio?->nombre,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'empleado_id' => ['required', 'integer', Rule::exists('empleados', 'id')],
            'numero' => ['nullable', 'string', 'max:30'],
            'fecha_apertura' => ['nullable', 'date'],
        ], attributes: [
            'empleado_id' => 'empleado',
            'fecha_apertura' => 'fecha de apertura',
        ]);

        $empleado = Empleado::findOrFail($datos['empleado_id']);

        $tarjeta = $this->tarjetas->abrir(
            $empleado,
            $datos['numero'] ?? null,
            $datos['fecha_apertura'] ?? null,
        );

        return to_route('inventario.tarjetas.show', $tarjeta)
            ->with('status', sprintf('Tarjeta de %s abierta. Ya puede agregar bienes.', $empleado->nombre_completo));
    }

    /**
     * Pantalla de armado de la tarjeta: el encabezado, los renglones en orden y
     * el buscador de bienes disponibles.
     */
    public function show(Request $request, Tarjeta $tarjeta): Response
    {
        $tarjeta->load([
            'empleado.unidadServicio',
            'renglones.bien.renglon',
            'anterior:id,version',
        ]);

        $buscarBien = trim((string) $request->string('bien'));

        // Solo se ofrecen bienes libres: los que ya tienen responsable no pueden
        // agregarse a otra tarjeta.
        $disponibles = $buscarBien === '' ? collect() : Bien::query()
            ->with('renglon:id,codigo')
            ->where('estado', '!=', Bien::ESTADO_BAJA)
            ->sinAsignar()
            ->buscar($buscarBien)
            ->orderBy('codigo')
            ->limit(25)
            ->get()
            ->map(fn (Bien $bien) => [
                'id' => $bien->id,
                'codigo' => $bien->codigo,
                'descripcion' => $bien->descripcion,
                'total' => (float) $bien->total,
                'cuenta' => $bien->renglon?->codigo,
                'unidad_propia' => $bien->unidad_servicio_id === $tarjeta->unidad_servicio_id,
            ]);

        $porHoja = $tarjeta->renglones_por_hoja;

        return Inertia::render('inventario/tarjetas/armado', [
            'tarjeta' => [
                'id' => $tarjeta->id,
                'numero' => $tarjeta->numero,
                'version' => $tarjeta->version,
                'estado' => $tarjeta->estado,
                'vigente' => $tarjeta->estaVigente(),
                'fecha_apertura' => $tarjeta->fecha_apertura?->format('d/m/Y'),
                'saldo_total' => (float) $tarjeta->saldo_total,
                'renglones_por_hoja' => $porHoja,
                'version_anterior' => $tarjeta->anterior?->version,
                'reemplaza_a' => $tarjeta->reemplaza_a,
            ],
            // Los datos que van en el encabezado impreso de cada hoja.
            'encabezado' => [
                'unidad_servicio' => $tarjeta->empleado->unidadServicio?->nombre,
                'municipio' => $tarjeta->empleado->unidadServicio?->municipio,
                'departamento' => $tarjeta->empleado->unidadServicio?->departamento,
                'nombre' => $tarjeta->empleado->nombre_completo,
                'cargo' => $tarjeta->empleado->cargo,
                'area_trabajo' => $tarjeta->empleado->area_trabajo,
            ],
            'renglones' => $tarjeta->renglones->map(fn (TarjetaRenglon $r) => [
                'id' => $r->id,
                'orden' => $r->orden,
                'bien_id' => $r->bien_id,
                'codigo' => $r->bien->codigo,
                'descripcion' => $r->bien->descripcion,
                'cantidad' => $r->bien->cantidad,
                'debe' => (float) $r->debe,
                'haber' => (float) $r->haber,
                'saldo' => (float) $r->saldo,
                // La celda CUENTA se muestra solo cuando cambia respecto al
                // renglon anterior, igual que en el documento oficial.
                'lineas_cuenta' => $r->bien->lineasColumnaCuenta(),
                'hoja_fisica' => $r->hoja_fisica,
                'impreso' => $r->yaSeImprimio(),
                // Hoja tentativa si todavia no se ha impreso.
                'hoja_estimada' => (int) ceil($r->orden / $porHoja),
            ]),
            'disponibles' => $disponibles,
            'busqueda' => $buscarBien,

            // Bienes que siguen escritos en la hoja firmada pero que ya no le
            // corresponden a esta persona: se dieron de baja o se trasladaron.
            // Salen el dia que se regenere la tarjeta.
            'pendientes_de_salir' => $this->tarjetas->bienesPendientesDeSalir($tarjeta)
                ->map(fn (TarjetaRenglon $r) => [
                    'codigo' => $r->bien->codigo,
                    'descripcion' => $r->bien->descripcion,
                    'razon' => $r->bien->estado === Bien::ESTADO_BAJA
                        ? 'dado de baja'
                        : 'trasladado a '.($r->bien->asignacionVigente?->empleado?->nombre_completo ?? 'otra tarjeta'),
                ])->values(),
            'hojas' => $this->resumenHojas($tarjeta),
        ]);
    }

    public function agregarBien(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'bien_id' => ['required', 'integer', Rule::exists('bienes', 'id')],
        ], attributes: ['bien_id' => 'bien']);

        $bien = Bien::findOrFail($datos['bien_id']);

        $this->tarjetas->agregarBien($tarjeta, $bien);

        return back()->with('status', sprintf('Se agregó el bien %s a la tarjeta.', $bien->codigo));
    }

    public function quitarBien(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'bien_id' => ['required', 'integer', Rule::exists('bienes', 'id')],
            'motivo' => ['nullable', Rule::in(['traslado', 'cambio_responsable', 'correccion'])],
        ], attributes: ['bien_id' => 'bien']);

        $bien = Bien::findOrFail($datos['bien_id']);
        $versionAntes = $tarjeta->version;

        $resultado = $this->tarjetas->quitarBien(
            $tarjeta,
            $bien,
            $datos['motivo'] ?? 'cambio_responsable',
        );

        // Si hubo que regenerar, la tarjeta vigente es otra: hay que llevar al
        // usuario a la version nueva.
        if ($resultado->id !== $tarjeta->id) {
            return to_route('inventario.tarjetas.show', $resultado)->with('status', sprintf(
                'Se retiró el bien %s. Como la tarjeta ya estaba impresa, se generó la versión %d.',
                $bien->codigo,
                $resultado->version,
            ));
        }

        return back()->with('status', sprintf(
            'Se retiró el bien %s de la tarjeta%s.',
            $bien->codigo,
            $versionAntes !== $resultado->version ? ' y se regeneró el documento' : '',
        ));
    }

    public function regenerar(Tarjeta $tarjeta): RedirectResponse
    {
        $nueva = $this->tarjetas->regenerar($tarjeta);

        return to_route('inventario.tarjetas.show', $nueva)
            ->with('status', sprintf('Se generó la versión %d de la tarjeta.', $nueva->version));
    }

    public function imprimir(Request $request, Tarjeta $tarjeta): View
    {
        return view('tarjetas.imprimir', $this->datosDeImpresion($request, $tarjeta) + ['pdf' => false]);
    }

    /**
     * La misma hoja, en PDF.
     *
     * Se arma con los mismos datos y el mismo formato que la vista: aqui no se
     * decide nada, solo cambia a donde sale. El PDF es lo que se manda a la
     * impresora, porque el navegador agrega su propio encabezado y su pie a lo
     * que imprime, y esto va sobre un formulario de la Contraloria.
     */
    public function pdf(Request $request, Tarjeta $tarjeta): StreamedResponse
    {
        $datos = $this->datosDeImpresion($request, $tarjeta);

        $html = view('tarjetas.imprimir', $datos + ['pdf' => true])->render();

        $dompdf = new Dompdf(new Options(['isRemoteEnabled' => false]));
        // Oficio apaisado, 33.0 x 21.59 cm expresado en puntos.
        $dompdf->setPaper([0, 0, 935.433, 612.0]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $contenido = (string) $dompdf->output();

        $archivo = sprintf(
            'Tarjeta %s%s.pdf',
            $tarjeta->empleado->nombre_completo,
            match (true) {
                $datos['soloPendientes'] => ' - solo lo nuevo',
                $datos['ensayo'] => ' - hasta la fecha',
                default => '',
            },
        );

        return response()->streamDownload(
            fn () => print ($contenido),
            $archivo,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Lo que necesita el formato impreso: las hojas ya paginadas y que renglones
     * no deben dejar tinta segun el modo de impresion.
     *
     * @return array<string, mixed>
     */
    private function datosDeImpresion(Request $request, Tarjeta $tarjeta): array
    {
        $tarjeta->load('empleado.unidadServicio');

        $hojas = $this->paginador->paginar($tarjeta);

        $soloPendientes = $request->boolean('pendientes');
        $ensayo = ! $soloPendientes && $request->boolean('ensayo');
        $hasta = $this->fechaDeCorte($request);

        // Renglones que no dejan tinta pero conservan su lugar en el papel.
        $ocultos = [];

        if ($soloPendientes) {
            // Se continua una hoja ya impresa: lo que salio antes no se repite.
            $ocultos = $this->renglonesImpresos($hojas, impresos: true);
            $hojas = array_values(array_filter($hojas, fn (array $h) => $h['tiene_pendientes']));
        } elseif ($ensayo) {
            // Manchote de ensayo: la tarjeta como estaba antes de la adicion.
            $ocultos = $hasta !== null
                ? $this->renglonesPosterioresA($hojas, $hasta)
                : $this->renglonesImpresos($hojas, impresos: false);
        }

        return [
            'tarjeta' => $tarjeta,
            'empleado' => $tarjeta->empleado,
            'unidad' => $tarjeta->empleado->unidadServicio,
            'hojas' => $hojas,
            'ultimaHoja' => $hojas === [] ? 0 : end($hojas)['numero'],
            'soloPendientes' => $soloPendientes,
            'ensayo' => $ensayo,
            'hasta' => $hasta,
            'ocultos' => $ocultos,
            'cuentaPreviaPorHoja' => $this->cuentaPreviaPorHoja($hojas),
            'descuadres' => $this->descuadres($hojas),
            'formatearQ' => fn (float|string $valor) => number_format((float) $valor, 2, '.', ','),
        ];
    }

    /**
     * Retracta la marca de impresion de un bien que se marco por error.
     */
    public function desmarcarImpresion(
        Request $request,
        Tarjeta $tarjeta,
        TarjetaRenglon $renglon,
    ): RedirectResponse {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:255'],
        ], attributes: ['motivo' => 'justificación']);

        $this->tarjetas->desmarcarImpresion($tarjeta, $renglon, $datos['motivo']);

        return back()->with('status', sprintf(
            'Se retractó la impresión del bien %s. Quedó constancia en la bitácora.',
            $renglon->bien->codigo,
        ));
    }

    /**
     * La fecha hasta la que se quiere reproducir la tarjeta, si se indico una.
     */
    private function fechaDeCorte(Request $request): ?string
    {
        $valor = trim((string) $request->string('hasta'));

        if ($valor === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Identificadores de los renglones segun hayan salido impresos o no.
     *
     * @param  array<int, array<string, mixed>>  $hojas
     * @return array<int, int>
     */
    private function renglonesImpresos(array $hojas, bool $impresos): array
    {
        $ids = [];

        foreach ($hojas as $hoja) {
            foreach ($hoja['renglones'] as $renglon) {
                if ($renglon->yaSeImprimio() === $impresos) {
                    $ids[] = $renglon->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Renglones que entraron despues de la fecha de corte.
     *
     * En la tarjeta la fecha se escribe una sola vez por adicion: los renglones
     * que le siguen sin fecha propia pertenecen a esa misma adicion. Por eso la
     * fecha se arrastra hacia abajo, o al cortar quedaria media adicion visible
     * y media oculta.
     *
     * @param  array<int, array<string, mixed>>  $hojas
     * @return array<int, int>
     */
    private function renglonesPosterioresA(array $hojas, string $hasta): array
    {
        $ids = [];
        $vigente = null;

        foreach ($hojas as $hoja) {
            foreach ($hoja['renglones'] as $renglon) {
                $propia = $renglon->bien->fecha_ingreso?->toDateString();

                if ($propia !== null) {
                    $vigente = $propia;
                }

                if ($vigente !== null && $vigente > $hasta) {
                    $ids[] = $renglon->id;
                }
            }
        }

        return $ids;
    }

    /**
     * Adiciones donde el TOTAL que trae el papel no coincide con la suma de sus
     * renglones.
     *
     * Se avisa en vez de corregirse: el papel esta firmado, y la diferencia
     * suele significar que a la tarjeta le falta un bien que si estaba impreso.
     *
     * @param  array<int, array<string, mixed>>  $hojas
     * @return array<int, array<string, mixed>>
     */
    private function descuadres(array $hojas): array
    {
        $descuadres = [];

        foreach ($hojas as $hoja) {
            foreach ($hoja['filas'] as $fila) {
                if ($fila['tipo'] === 'total' && ! $fila['cuadra']) {
                    $descuadres[] = [
                        'hoja' => $hoja['numero'],
                        'literal' => $fila['literal'],
                        'calculado' => $fila['calculado'],
                        'diferencia' => $fila['literal'] - $fila['calculado'],
                    ];
                }
            }

            // El TOTAL de cierre de la tarjeta se revisa aparte, pero solo si
            // no salio ya como corte de adicion: cuando el ultimo renglon trae
            // su total escrito, las dos comprobaciones miran el mismo numero y
            // el descuadre se reportaria dos veces.
            if ($hoja['termina_en_total']) {
                continue;
            }

            $papel = $hoja['total_papel'];

            if ($papel !== null && abs($papel - $hoja['van']) >= 0.01) {
                $descuadres[] = [
                    'hoja' => $hoja['numero'],
                    'literal' => $papel,
                    'calculado' => $hoja['van'],
                    'diferencia' => $papel - $hoja['van'],
                ];
            }
        }

        return $descuadres;
    }

    /**
     * La ultima cuenta escrita antes de que empiece cada hoja.
     *
     * Hace falta porque la cuenta solo se imprime cuando cambia: al pasar de
     * hoja hay que saber con que valor venia para no repetirla de mas ni
     * omitirla cuando si cambio.
     *
     * @param  array<int, array<string, mixed>>  $hojas
     * @return array<int, array<int, string>|null>
     */
    private function cuentaPreviaPorHoja(array $hojas): array
    {
        $previas = [];
        $ultima = null;

        foreach ($hojas as $hoja) {
            $previas[$hoja['numero']] = $ultima;

            foreach ($hoja['renglones'] as $renglon) {
                $lineas = $renglon->bien->lineasColumnaCuenta();

                if ($lineas !== []) {
                    $ultima = $lineas;
                }
            }
        }

        return $previas;
    }

    /**
     * Pantalla de calce: alinear la impresion sobre una hoja que ya salio
     * impresa y acomodar los renglones que todavia no estan en tinta.
     */
    public function calce(Tarjeta $tarjeta): Response
    {
        $tarjeta->load('empleado.unidadServicio');

        $hojas = $this->paginador->paginar($tarjeta);

        return Inertia::render('inventario/tarjetas/calce', [
            'geometria' => [
                'papel_ancho' => GeometriaTarjeta::PAPEL_ANCHO_MM,
                'papel_alto' => GeometriaTarjeta::PAPEL_ALTO_MM,
                'margen_izquierdo' => GeometriaTarjeta::MARGEN_IZQUIERDO_MM,
                'margen_superior_frente' => GeometriaTarjeta::MARGEN_SUPERIOR_FRENTE_MM,
                'margen_superior_reverso' => GeometriaTarjeta::MARGEN_SUPERIOR_REVERSO_MM,
                'alto_encabezado' => GeometriaTarjeta::ALTO_ENCABEZADO_MM,
                'alto_rotulos' => GeometriaTarjeta::ALTO_ROTULOS_MM,
                'alto_linea' => GeometriaTarjeta::ALTO_LINEA_MM,
                'alto_firmas' => GeometriaTarjeta::ALTO_FIRMAS_MM,
                'alto_pie' => GeometriaTarjeta::ALTO_PIE_MM,
                'margen_inferior' => GeometriaTarjeta::MARGEN_INFERIOR_MM,
                'espacio_firma' => GeometriaTarjeta::ESPACIO_FIRMA_MM,
                'escala_minima' => GeometriaTarjeta::ESCALA_MINIMA,
                'columnas' => GeometriaTarjeta::COLUMNAS,
            ],
            'tarjeta' => [
                'id' => $tarjeta->id,
                'numero' => $tarjeta->numero,
                'version' => $tarjeta->version,
                'vigente' => $tarjeta->estaVigente(),
                'renglones_por_hoja' => $tarjeta->renglones_por_hoja,
            ],
            'encabezado' => [
                'unidad_servicio' => $tarjeta->empleado->unidadServicio?->nombre,
                'municipio' => $tarjeta->empleado->unidadServicio?->municipio,
                'departamento' => $tarjeta->empleado->unidadServicio?->departamento,
                'nombre' => $tarjeta->empleado->nombre_completo,
                'cargo' => $tarjeta->empleado->cargo,
                'area_trabajo' => $tarjeta->empleado->area_trabajo,
            ],
            'hojas' => array_map(fn (array $hoja) => [
                'numero' => $hoja['numero'],
                'cara' => $hoja['cara'],
                'papel' => $hoja['papel'],
                'banda_mm' => $hoja['banda_mm'],
                'usado_mm' => $hoja['usado_mm'],
                'libres_mm' => $hoja['libres_mm'],
                'escala' => $hoja['escala'],
                'escala_sugerida' => $hoja['escala_sugerida'],
                'cerrada' => $hoja['cerrada'],
                'impresa' => $hoja['impresa'],
                'desfase_x_mm' => (float) $hoja['desfase_x_mm'],
                'desfase_y_mm' => (float) $hoja['desfase_y_mm'],
                'vienen' => (float) $hoja['vienen'],
                // Las filas tal como van en el papel: los bienes y, entre
                // ellos, los TOTAL que cierran cada adicion. La pantalla dibuja
                // la hoja con esto, para que se vea igual que impresa.
                'filas' => array_map(fn (array $fila) => $fila['tipo'] === 'total'
                    ? [
                        'tipo' => 'total',
                        'monto' => (float) $fila['monto'],
                        'alto_mm' => $fila['alto_mm'],
                        'impreso' => $fila['ya_impreso'],
                    ]
                    : [
                        'tipo' => 'renglon',
                        'renglon_id' => $fila['renglon']->id,
                        'alto_mm' => $fila['alto_mm'],
                        'impreso' => $fila['renglon']->yaSeImprimio(),
                    ], $hoja['filas']),

                // Cierre de la hoja: el VAN que pasa a la siguiente, o el TOTAL
                // si es la ultima.
                'cierre' => $hoja['cerrada'] && ! ($hoja['es_ultima'] && $hoja['termina_en_total'])
                    ? [
                        'rotulo' => $hoja['es_ultima'] ? 'TOTAL' : 'VAN',
                        'monto' => (float) ($hoja['total_papel'] ?? $hoja['van']),
                    ]
                    : null,

                'renglones' => $hoja['renglones']->map(fn (TarjetaRenglon $r) => [
                    'id' => $r->id,
                    'orden' => $r->orden,
                    'codigo' => $r->bien->codigo,
                    'descripcion' => $r->bien->descripcion,
                    'cantidad' => $r->bien->cantidad,
                    'fecha' => $r->bien->fechaColumnaTarjeta(),
                    'debe' => (float) $r->debe,
                    'haber' => (float) $r->haber,
                    'saldo' => (float) $r->saldo,
                    'observaciones' => $r->observaciones,
                    'lineas_cuenta' => $r->bien->lineasColumnaCuenta(),
                    'impreso' => $r->yaSeImprimio(),
                    // Lo que mide este renglon en el papel: la pantalla dibuja
                    // la hoja con las mismas alturas que sale impresa.
                    'alto_mm' => $this->medidor->altoDeRenglon($r, $hoja['escala']),
                ])->values(),
            ], $hojas),
        ]);
    }

    /** Guarda los milimetros de calce de una hoja. */
    public function guardarCalce(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $tope = TarjetaHoja::DESFASE_MAXIMO_MM;

        $datos = $request->validate([
            'hoja' => ['required', 'integer', 'min:1', 'max:999'],
            // Van sueltos: desde la vista de impresion solo se aprieta el
            // texto, y el calce que la hoja ya tenia no se toca.
            'desfase_x_mm' => ['nullable', 'numeric', "min:-{$tope}", "max:{$tope}"],
            'desfase_y_mm' => ['nullable', 'numeric', "min:-{$tope}", "max:{$tope}"],
            'escala' => ['nullable', 'numeric', 'min:'.GeometriaTarjeta::ESCALA_MINIMA, 'max:1'],
        ], attributes: [
            'hoja' => 'número de hoja',
            'desfase_x_mm' => 'desplazamiento horizontal',
            'desfase_y_mm' => 'desplazamiento vertical',
            'escala' => 'ajuste del texto',
        ]);

        $papel = $this->tarjetas->guardarCalce(
            $tarjeta,
            $datos['hoja'],
            isset($datos['desfase_x_mm']) ? (float) $datos['desfase_x_mm'] : null,
            isset($datos['desfase_y_mm']) ? (float) $datos['desfase_y_mm'] : null,
            isset($datos['escala']) ? (float) $datos['escala'] : null,
        );

        return back()->with('status', sprintf(
            'Se guardó la hoja %d: calce %s / %s mm, texto al %d %%.',
            $papel->numero,
            $papel->desfase_x_mm,
            $papel->desfase_y_mm,
            round($papel->escala * 100),
        ));
    }

    /** Pasa un renglon todavia sin imprimir a otra hoja de papel. */
    public function moverRenglon(Request $request, Tarjeta $tarjeta, TarjetaRenglon $renglon): RedirectResponse
    {
        $datos = $request->validate([
            'hoja' => ['required', 'integer', 'min:1', 'max:999'],
        ], attributes: ['hoja' => 'número de hoja']);

        $this->tarjetas->moverRenglonAHoja($tarjeta, $renglon, $datos['hoja']);

        return back()->with('status', sprintf(
            'El bien %s pasó a la hoja %d.',
            $renglon->bien->codigo,
            $datos['hoja'],
        ));
    }

    /** Cierra o reabre una hoja. Solo la hoja cerrada lleva su linea de VAN. */
    public function cambiarEstadoHoja(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'hoja' => ['required', 'integer', 'min:1', 'max:999'],
            'cerrada' => ['required', 'boolean'],
        ], attributes: ['hoja' => 'número de hoja']);

        if ($datos['cerrada']) {
            $this->tarjetas->cerrarHoja($tarjeta, $datos['hoja']);

            return back()->with('status', sprintf(
                'Se cerró la hoja %d. Al imprimirla saldrá su línea de VAN.',
                $datos['hoja'],
            ));
        }

        $this->tarjetas->reabrirHoja($tarjeta, $datos['hoja']);

        return back()->with('status', sprintf(
            'Se reabrió la hoja %d. Vuelve a admitir bienes y no se le imprime el VAN.',
            $datos['hoja'],
        ));
    }

    /**
     * Registra que una hoja de papel ya salio impresa, para poder reutilizarla
     * si le quedo espacio.
     */
    public function marcarImpreso(Request $request, Tarjeta $tarjeta): RedirectResponse
    {
        $datos = $request->validate([
            'hoja' => ['required', 'integer', 'min:1', 'max:999'],
            'renglones' => ['required', 'array', 'min:1'],
            'renglones.*' => ['integer', Rule::exists('tarjeta_renglones', 'id')],
        ], attributes: ['hoja' => 'número de hoja', 'renglones' => 'renglones']);

        $this->tarjetas->marcarImpreso($tarjeta, $datos['renglones'], $datos['hoja']);

        return back()->with('status', sprintf(
            'Se registró la impresión de la hoja %d (%s).',
            $datos['hoja'],
            Tarjeta::caraDeHoja($datos['hoja']),
        ));
    }

    /**
     * Estado de cada hoja de papel: cuantos renglones lleva, cuanto le queda y
     * si es frente o reverso.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resumenHojas(Tarjeta $tarjeta): array
    {
        $porHoja = $tarjeta->renglones_por_hoja;

        $impresos = $tarjeta->renglones()
            ->whereNotNull('hoja_fisica')
            ->get()
            ->groupBy('hoja_fisica');

        $hojas = [];

        foreach ($impresos as $numero => $renglones) {
            $numero = (int) $numero;

            $hojas[] = [
                'numero' => $numero,
                'cara' => Tarjeta::caraDeHoja($numero),
                'papel' => Tarjeta::papelDeHoja($numero),
                'ocupados' => $renglones->count(),
                'capacidad' => $porHoja,
                'libre' => max(0, $porHoja - $renglones->count()),
                'impresa' => true,
            ];
        }

        $pendientes = $tarjeta->renglonesPendientesDeImprimir();

        if ($pendientes > 0) {
            $ultima = $hojas === [] ? 0 : max(array_column($hojas, 'numero'));
            $espacio = $tarjeta->espacioEnUltimaHoja();

            $hojas[] = [
                'numero' => $espacio > 0 && $ultima > 0 ? $ultima : $ultima + 1,
                'cara' => Tarjeta::caraDeHoja($espacio > 0 && $ultima > 0 ? $ultima : $ultima + 1),
                'papel' => Tarjeta::papelDeHoja($espacio > 0 && $ultima > 0 ? $ultima : $ultima + 1),
                'ocupados' => $pendientes,
                'capacidad' => $porHoja,
                'libre' => max(0, $porHoja - $pendientes),
                'impresa' => false,
                'reutiliza_hoja' => $espacio > 0 && $ultima > 0,
                'espacio_disponible' => $espacio,
            ];
        }

        return $hojas;
    }
}
