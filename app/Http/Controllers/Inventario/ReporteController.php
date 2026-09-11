<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bien;
use App\Models\Renglon;
use App\Models\UnidadServicio;
use App\Services\ReporteInventario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function __construct(private readonly ReporteInventario $reportes) {}

    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);
        $reporte = $this->reportes->generar($filtros);

        return Inertia::render('inventario/reportes/index', [
            'filtros' => $filtros,
            'reporte' => [
                'grupos' => array_map(fn (array $g) => [
                    'rotulo' => $g['rotulo'],
                    'cantidad' => $g['cantidad'],
                    'valor' => $g['valor'],
                    'bienes' => $g['bienes']->map(fn (Bien $b) => $this->fila($b))->all(),
                ], $reporte['grupos']),
                'total_bienes' => $reporte['total_bienes'],
                'total_cantidad' => $reporte['total_cantidad'],
                'total_valor' => $reporte['total_valor'],
            ],
            'catalogos' => [
                'unidades' => UnidadServicio::activas()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
                'cuentas' => Renglon::orderBy('orden')->get(['id', 'codigo', 'nombre']),
                'agrupaciones' => ReporteInventario::AGRUPACIONES,
            ],
        ]);
    }

    /**
     * Vista para imprimir o guardar como PDF desde el navegador. Se resuelve
     * asi y no con una libreria de PDF para no agregar una dependencia que
     * habria que instalar en cada uno de los equipos.
     */
    public function imprimir(Request $request): View
    {
        $filtros = $this->filtros($request);
        $reporte = $this->reportes->generar($filtros);

        return view('reportes.inventario', [
            'reporte' => $reporte,
            'descripcion' => $this->reportes->descripcionDeFiltros($filtros),
            'agrupacion' => ReporteInventario::AGRUPACIONES[$reporte['agrupar_por']],
            'formatearQ' => fn (float|string $v) => number_format((float) $v, 2, '.', ','),
        ]);
    }

    /**
     * Exporta a Excel con PhpSpreadsheet, que ya esta en el proyecto para leer
     * las importaciones y tambien sabe escribir.
     */
    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $reporte = $this->reportes->generar($filtros);

        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Inventario');

        $encabezados = ['Código', 'Descripción', 'Cant.', 'Valor unitario', 'Valor total',
            'Unidad de servicio', 'Cuenta', 'Movimiento', 'Adquisición', 'Programa',
            'Fecha de ingreso', 'Estado'];

        $fila = 1;
        $hoja->fromArray(['REPORTE DE BIENES NO FUNGIBLES'], null, 'A'.$fila);
        $hoja->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(14);
        $fila++;

        $hoja->fromArray(['Dirección Departamental de Redes Integradas de Servicios de Salud de Totonicapán'], null, 'A'.$fila);
        $fila += 2;

        foreach ($this->reportes->descripcionDeFiltros($filtros) as $linea) {
            $hoja->fromArray([$linea], null, 'A'.$fila);
            $fila++;
        }

        $fila++;
        $hoja->fromArray($encabezados, null, 'A'.$fila);
        $hoja->getStyle('A'.$fila.':L'.$fila)->getFont()->setBold(true);
        $hoja->getStyle('A'.$fila.':L'.$fila)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $fila++;

        foreach ($reporte['grupos'] as $grupo) {
            $hoja->fromArray([$grupo['rotulo']], null, 'A'.$fila);
            $hoja->getStyle('A'.$fila)->getFont()->setBold(true);
            $fila++;

            foreach ($grupo['bienes'] as $bien) {
                $hoja->fromArray(array_values($this->filaExcel($bien)), null, 'A'.$fila);
                $fila++;
            }

            $hoja->fromArray(
                ['', 'Subtotal '.$grupo['rotulo'], $grupo['cantidad'], '', $grupo['valor']],
                null, 'A'.$fila
            );
            $hoja->getStyle('A'.$fila.':L'.$fila)->getFont()->setBold(true);
            $fila += 2;
        }

        $hoja->fromArray(
            ['', 'TOTAL GENERAL', $reporte['total_cantidad'], '', $reporte['total_valor']],
            null, 'A'.$fila
        );
        $hoja->getStyle('A'.$fila.':L'.$fila)->getFont()->setBold(true);

        foreach (range('A', 'L') as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        $hoja->getStyle('C:E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        AuditLog::registrar(
            evento: 'reporte.exportado',
            descripcion: sprintf('Se exportó a Excel un reporte de %d bien(es)', $reporte['total_bienes']),
            datos: ['filtros' => array_filter($filtros)],
        );

        $nombre = 'inventario-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        return [
            'unidad_servicio_id' => $request->integer('unidad_servicio_id') ?: null,
            'renglon_id' => $request->integer('renglon_id') ?: null,
            'tipo_movimiento' => trim((string) $request->string('tipo_movimiento')) ?: null,
            'forma_adquisicion' => trim((string) $request->string('forma_adquisicion')) ?: null,
            'programa' => trim((string) $request->string('programa')) ?: null,
            'buscar' => trim((string) $request->string('buscar')) ?: null,
            'desde' => trim((string) $request->string('desde')) ?: null,
            'hasta' => trim((string) $request->string('hasta')) ?: null,
            'estado' => trim((string) $request->string('estado')) ?: null,
            'agrupar_por' => trim((string) $request->string('agrupar_por')) ?: 'unidad',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Bien $bien): array
    {
        return [
            'id' => $bien->id,
            'codigo' => $bien->codigo,
            'codigo_provisional' => $bien->codigo_provisional,
            'descripcion' => $bien->descripcion,
            'cantidad' => $bien->cantidad,
            'precio_unitario' => (float) $bien->precio_unitario,
            'total' => (float) $bien->total,
            'unidad' => $bien->unidadServicio?->nombre,
            'cuenta' => $bien->renglon?->codigo,
            'tipo_movimiento' => $bien->tipo_movimiento,
            'forma_adquisicion' => $bien->forma_adquisicion,
            'programa' => $bien->programa,
            'fecha_ingreso' => $bien->fecha_ingreso?->format('d/m/Y'),
            'estado' => $bien->estado,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function filaExcel(Bien $bien): array
    {
        return [
            $bien->codigo,
            $bien->descripcion,
            $bien->cantidad,
            (float) $bien->precio_unitario,
            (float) $bien->total,
            $bien->unidadServicio?->nombre,
            $bien->renglon?->codigo,
            $bien->tipo_movimiento === 'adicion' ? 'Adición' : 'Apertura',
            match ($bien->forma_adquisicion) {
                'compra' => 'Compra',
                'donacion' => 'Donación',
                default => '',
            },
            $bien->programa,
            $bien->fecha_ingreso?->format('d/m/Y'),
            $bien->estado === 'baja' ? 'De baja' : 'Activo',
        ];
    }
}
