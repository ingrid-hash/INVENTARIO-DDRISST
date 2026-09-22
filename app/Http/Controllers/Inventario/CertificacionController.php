<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bien;
use App\Models\Certificacion;
use App\Models\CertificacionFormato;
use App\Services\CertificacionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('inventario/certificaciones/index', [
            'buscar' => $buscar,
            'resultados' => $buscar === '' ? [] : $this->buscar($buscar),
            'formatos' => $this->formatos(),
            'plantillas' => $this->textos->plantillas(),
            'cierre' => $this->textos->cierre(),
            'emitidas' => $this->emitidas(),
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
        return Bien::query()
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
            ->get()
            ->map(fn (Bien $bien) => $this->datosDeBien($bien))
            ->all();
    }

    /**
     * Lo que la pantalla necesita de un bien: el texto ya armado y los datos
     * que deciden la redaccion del parrafo del libro.
     *
     * @return array<string, mixed>
     */
    private function datosDeBien(Bien $bien): array
    {
        $unidad = $bien->unidadServicio;

        return [
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
     * Las ultimas certificaciones, para volver a imprimir una ya emitida.
     *
     * @return array<int, array<string, mixed>>
     */
    private function emitidas(): array
    {
        return Certificacion::query()
            ->with('emitidaPor:id,name')
            ->withCount('bienes')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (Certificacion $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'fecha' => $c->created_at?->format('d/m/Y H:i'),
                'firmante' => $c->firmante_nombre,
                'bienes' => $c->bienes_count,
                'emitida_por' => $c->emitidaPor?->name,
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

        // Se vuelve a la pantalla y desde ahi se abre la vista de impresion:
        // esa vista no es de React y no puede devolverse como respuesta aqui.
        return back()->with('status', 'Certificación '.$certificacion->numero.' emitida.');
    }

    /**
     * Vista para imprimir o guardar como PDF desde el navegador, en carta y con
     * el membrete de la institucion.
     */
    public function imprimir(Certificacion $certificacion): View
    {
        $certificacion->load('bienes');

        return view('certificaciones.imprimir', ['certificacion' => $certificacion]);
    }
}
