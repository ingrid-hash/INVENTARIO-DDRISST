<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bien;
use App\Models\Certificacion;
use App\Models\CertificacionFormato;
use App\Services\CertificacionService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificacionController extends Controller
{
    public function __construct(private readonly CertificacionService $textos) {}

    /**
     * Pantalla de emision. El buscador trabaja igual que el de la tarjeta: la
     * consulta va al servidor y vuelve con los bienes ya convertidos en el
     * texto que iria en el documento.
     */
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->string('buscar'));
        $buscarHechas = trim((string) $request->string('hechas'));

        return Inertia::render('inventario/certificaciones/index', [
            'buscar' => $buscar,
            'resultados' => $buscar === '' ? [] : $this->buscar($buscar),
            'buscar_hechas' => $buscarHechas,
            'hechas' => $this->hechas($buscarHechas),
            'formatos' => $this->formatos(),
            'plantillas' => $this->textos->plantillas(),
            'cierre' => $this->textos->cierre(),
            // La que se acaba de emitir, para descargarla sin tener que buscarla.
            'recien_emitida' => $request->session()->get('certificacion_emitida'),
        ]);
    }

    /**
     * Busca por codigo, por descripcion del bien o por el nombre de quien lo
     * tiene a su cargo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscar(string $termino): array
    {
        $bienes = Bien::query()
            ->with(['unidadServicio:id,nombre,tipo', 'asignacionVigente.empleado:id,nombre_completo'])
            ->where(function ($query) use ($termino) {
                $query->where('codigo', 'ilike', $termino.'%')
                    ->orWhere('descripcion', 'ilike', '%'.$termino.'%')
                    ->orWhereHas(
                        'asignacionVigente.empleado',
                        fn ($sub) => $sub->where('nombre_completo', 'ilike', '%'.$termino.'%')
                    );
            })
            ->orderBy('codigo')
            ->limit(40)
            ->get();

        $previas = $this->certificacionesPrevias($bienes->pluck('id')->all());

        return $bienes
            ->map(fn (Bien $bien) => $this->datosDeBien($bien, $previas[$bien->id] ?? null))
            ->all();
    }

    /**
     * La ultima certificacion de cada bien, en una sola consulta.
     *
     * Sirve para que al agregar un bien que ya se certifico antes se recupere
     * aquel documento en lugar de volver a escribirlo todo.
     *
     * @param  array<int, int>  $bienes
     * @return array<int, array<string, mixed>> indexado por bien
     */
    private function certificacionesPrevias(array $bienes): array
    {
        if ($bienes === []) {
            return [];
        }

        $certificaciones = Certificacion::query()
            ->with(['bienes.bien:id,codigo,descripcion,precio_unitario', 'unidadServicio:id,nombre,tipo'])
            ->whereHas('bienes', fn ($q) => $q->whereIn('bien_id', $bienes))
            ->latest('id')
            ->get();

        $previas = [];

        foreach ($certificaciones as $certificacion) {
            $datos = $this->datosDeCertificacion($certificacion);

            // Van de la mas reciente a la mas antigua: la primera que aparece
            // para un bien es la que se reutiliza.
            foreach ($certificacion->bienes as $punto) {
                if ($punto->bien_id !== null && ! isset($previas[$punto->bien_id])) {
                    $previas[$punto->bien_id] = $datos;
                }
            }
        }

        return $previas;
    }

    /**
     * Certificaciones ya emitidas. Se buscan por su numero, por el codigo o la
     * descripcion de un bien, o por el nombre de quien lo tiene a su cargo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function hechas(string $termino): array
    {
        return Certificacion::query()
            ->with([
                'emitidaPor:id,name',
                'bienes.bien:id,codigo,descripcion,precio_unitario',
                'unidadServicio:id,nombre,tipo',
            ])
            ->when($termino !== '', fn ($query) => $query->where(function ($sub) use ($termino) {
                $sub->where('numero', 'ilike', $termino.'%')
                    ->orWhereHas('bienes.bien', fn ($b) => $b
                        ->where('codigo', 'ilike', $termino.'%')
                        ->orWhere('descripcion', 'ilike', '%'.$termino.'%'))
                    ->orWhereHas(
                        'bienes.bien.asignacionVigente.empleado',
                        fn ($e) => $e->where('nombre_completo', 'ilike', '%'.$termino.'%')
                    );
            }))
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (Certificacion $c) => $this->datosDeCertificacion($c))
            ->all();
    }

    /**
     * Una certificacion emitida, con todo lo que hace falta para volver a
     * armarla en pantalla.
     *
     * De cada bien se envian dos textos: el que se imprimio entonces y el que
     * saldria hoy, porque la descripcion o el precio pueden haber cambiado.
     *
     * @return array<string, mixed>
     */
    private function datosDeCertificacion(Certificacion $c): array
    {
        return [
            'id' => $c->id,
            'numero' => $c->numero,
            'fecha' => $c->created_at?->format('d/m/Y'),
            'emitida_por' => $c->emitidaPor?->name,
            'firmante' => $c->firmante_nombre,
            'formato_id' => $c->certificacion_formato_id,
            'unidad_servicio_id' => $c->unidad_servicio_id,
            'unidad_nombre' => $this->textos->nombreDeUnidad($c->unidadServicio),
            'libro_auxiliar' => $c->libro_auxiliar,
            'libro_registro' => $c->libro_registro,
            'libro_folio' => $c->libro_folio,
            'apertura' => $c->apertura,
            'parrafo_libro' => $c->parrafo_libro,
            'bienes' => $c->bienes
                ->filter(fn ($punto) => $punto->bien_id !== null)
                ->values()
                ->map(fn ($punto) => [
                    'bien_id' => $punto->bien_id,
                    'codigo' => $punto->bien?->codigo ?? '',
                    'texto' => $punto->texto,
                    'texto_actual' => $punto->bien ? $this->textos->parrafoBien($punto->bien) : $punto->texto,
                ])->all(),
        ];
    }

    /**
     * Lo que la pantalla necesita de un bien: el texto ya armado y los datos
     * que deciden la redaccion del parrafo del libro.
     *
     * @return array<string, mixed>
     */
    private function datosDeBien(Bien $bien, ?array $previa = null): array
    {
        $unidad = $bien->unidadServicio;

        return [
            // La ultima certificacion donde salio este bien, si la hay.
            'certificacion_previa' => $previa,
            'id' => $bien->id,
            'codigo' => $bien->codigo,
            'descripcion' => $bien->descripcion,
            'precio_unitario' => (float) $bien->precio_unitario,
            'responsable' => $bien->asignacionVigente?->empleado?->nombre_completo,
            'unidad_id' => $unidad?->id,
            'unidad_nombre' => $this->textos->nombreDeUnidad($unidad),
            'libro_auxiliar' => $this->textos->usaLibroAuxiliar($unidad),
            // Lo que se escribio la ultima vez para este bien; si nunca se ha
            // certificado, el registro que se uso en su unidad.
            'libro_registro' => $bien->libro_registro ?? $this->ultimoRegistroDe($bien),
            'libro_folio' => $bien->libro_folio,
            'texto' => $this->textos->parrafoBien($bien),
        ];
    }

    /**
     * El numero de libro que se uso la ultima vez en la misma unidad. Sirve
     * para no volver a escribirlo bien por bien: el libro es de la unidad, solo
     * el folio cambia.
     */
    private function ultimoRegistroDe(Bien $bien): ?string
    {
        if ($bien->unidad_servicio_id === null) {
            return null;
        }

        return Bien::query()
            ->where('unidad_servicio_id', $bien->unidad_servicio_id)
            ->whereNotNull('libro_registro')
            ->orderByDesc('updated_at')
            ->value('libro_registro');
    }

    /**
     * Los formatos disponibles, con su linea de apertura ya escrita para que la
     * pantalla no tenga que armarla.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatos(): array
    {
        return CertificacionFormato::query()
            ->where('activo', true)
            ->orderBy('orden')->orderBy('id')
            ->get()
            ->map(fn (CertificacionFormato $f) => [
                'id' => $f->id,
                'nombre' => $f->nombre,
                'cargo_apertura' => $f->cargo_apertura,
                'genero' => $f->genero,
                'firmante_nombre' => $f->firmante_nombre,
                'firmante_cargo' => $f->firmante_cargo,
                'vobo_nombre' => $f->vobo_nombre,
                'vobo_cargo' => $f->vobo_cargo,
                'institucion' => $f->institucion,
                'predeterminado' => $f->predeterminado,
                'apertura' => $this->textos->apertura($f),
            ])
            ->all();
    }

    /**
     * Emite la certificacion: guarda el texto tal como quedo en pantalla y deja
     * anotado en cada bien su libro y su folio para la proxima vez.
     */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'certificacion_formato_id' => ['required', 'integer', Rule::exists('certificacion_formatos', 'id')],
            'unidad_servicio_id' => ['nullable', 'integer', Rule::exists('unidades_servicio', 'id')],
            'libro_auxiliar' => ['required', 'boolean'],
            'libro_registro' => ['nullable', 'string', 'max:40'],
            'libro_folio' => ['nullable', 'string', 'max:20'],
            'apertura' => ['required', 'string'],
            'parrafo_libro' => ['required', 'string'],
            'cierre' => ['required', 'string'],
            'bienes' => ['required', 'array', 'min:1'],
            'bienes.*.bien_id' => ['required', 'integer', Rule::exists('bienes', 'id')],
            'bienes.*.texto' => ['required', 'string'],
        ], attributes: [
            'parrafo_libro' => 'párrafo del libro',
            'bienes' => 'bienes de la certificación',
        ]);

        $formato = CertificacionFormato::findOrFail($datos['certificacion_formato_id']);

        $certificacion = DB::transaction(function () use ($datos, $formato) {
            $certificacion = Certificacion::create([
                'numero' => Certificacion::siguienteNumero(),
                'certificacion_formato_id' => $formato->id,
                'unidad_servicio_id' => $datos['unidad_servicio_id'] ?? null,
                'libro_auxiliar' => $datos['libro_auxiliar'],
                'libro_registro' => $datos['libro_registro'] ?? null,
                'libro_folio' => $datos['libro_folio'] ?? null,
                'apertura' => $datos['apertura'],
                'parrafo_libro' => $datos['parrafo_libro'],
                'cierre' => $datos['cierre'],
                'firmante_nombre' => $formato->firmante_nombre,
                'firmante_cargo' => $formato->firmante_cargo,
                'vobo_nombre' => $formato->vobo_nombre,
                'vobo_cargo' => $formato->vobo_cargo,
                'institucion' => $formato->institucion,
                'emitida_por' => auth()->id(),
            ]);

            foreach (array_values($datos['bienes']) as $indice => $fila) {
                $certificacion->bienes()->create([
                    'bien_id' => $fila['bien_id'],
                    'orden' => $indice + 1,
                    'texto' => $fila['texto'],
                ]);

                // El libro y el folio quedan en el bien para que la siguiente
                // certificacion salga con ellos puestos.
                if ($datos['libro_auxiliar']) {
                    Bien::whereKey($fila['bien_id'])->update([
                        'libro_registro' => $datos['libro_registro'] ?? null,
                        'libro_folio' => $datos['libro_folio'] ?? null,
                    ]);
                }
            }

            return $certificacion;
        });

        AuditLog::registrar(
            evento: 'certificacion.emitida',
            descripcion: sprintf(
                'Se emitió la certificación %s con %d bien(es), firmada por %s',
                $certificacion->numero,
                count($datos['bienes']),
                $certificacion->firmante_nombre,
            ),
            datos: [
                'certificacion_id' => $certificacion->id,
                'bienes' => array_column($datos['bienes'], 'bien_id'),
            ],
        );

        // Se vuelve a la pantalla y desde ahi se descarga el PDF: el archivo no
        // es una respuesta de React y no puede devolverse aqui.
        return back()
            ->with('status', 'Certificación '.$certificacion->numero.' emitida.')
            ->with('certificacion_emitida', $certificacion->id);
    }

    /**
     * Descarga la certificacion en PDF.
     *
     * Se arma aqui y no con la impresion del navegador porque el navegador
     * agrega su propio encabezado con la fecha y la direccion de la pagina, y
     * esto es un documento que se entrega firmado.
     */
    public function descargar(Certificacion $certificacion): StreamedResponse
    {
        $certificacion->load('bienes');

        $html = view('certificaciones.imprimir', [
            'certificacion' => $certificacion,
            // Las imagenes van incrustadas: asi el PDF no depende de que el
            // servidor este alcanzable desde donde se genera.
            'logo' => $this->incrustar('img/membrete-logo.png'),
            'linea' => $this->incrustar('img/membrete-linea.png'),
        ])->render();

        $dompdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']));
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $archivo = 'Certificacion '.$certificacion->numero.'.pdf';
        $contenido = (string) $dompdf->output();

        AuditLog::registrar(
            evento: 'certificacion.descargada',
            descripcion: 'Se descargó en PDF la certificación '.$certificacion->numero,
            datos: ['certificacion_id' => $certificacion->id],
        );

        return response()->streamDownload(
            fn () => print ($contenido),
            $archivo,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Una imagen de public/ convertida en dato incrustable.
     */
    private function incrustar(string $ruta): string
    {
        $archivo = public_path($ruta);

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($archivo));
    }
}
