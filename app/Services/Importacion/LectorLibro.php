<?php

namespace App\Services\Importacion;

use App\Support\Excel\DetectorFormato;
use App\Support\Excel\ValoresExcel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;


class LectorLibro
{
    public function __construct(private readonly string $ruta) {}

    /**
     * Hojas del libro con su tamano, marcando las que no se pueden leer.
     *
     * Se usa listWorksheetInfo, que lee solo las dimensiones declaradas en el
     * archivo sin cargar ninguna celda. Contar los codigos de cada hoja
     * obligaria a abrir las veinte hojas del libro de tarjetas y la pantalla
     * tardaria minutos en aparecer.
     *
     * @return array<int, array{nombre: string, filas: int, columnas: int, legible: bool, motivo: string|null}>
     */
    public function hojas(): array
    {
        $info = $this->lector()->listWorksheetInfo($this->ruta);
        $ilegibles = (new RevisorLibro($this->ruta))->hojasIlegibles();

        return array_map(fn (array $hoja) => [
            'nombre' => $hoja['worksheetName'],
            'filas' => (int) ($hoja['totalRows'] ?? 0),
            'columnas' => (int) ($hoja['totalColumns'] ?? 0),
            'legible' => ! isset($ilegibles[$hoja['worksheetName']]),
            'motivo' => $ilegibles[$hoja['worksheetName']] ?? null,
        ], $info);
    }

    /**
     * Cuantos codigos de inventario trae una hoja. Se calcula solo para la hoja
     * que el usuario elige, no para todas.
     */
    public function contarBienes(string $hoja): int
    {
        $codigos = 0;

        foreach ($this->filas($hoja) as $fila) {
            foreach ($fila as $celda) {
                if (ValoresExcel::esCodigo($celda)) {
                    $codigos++;
                    break;
                }
            }
        }

        return $codigos;
    }

    
    private const ULTIMA_COLUMNA = 'AF';

    /** Tope de filas por hoja, por la misma razon. */
    private const MAXIMO_FILAS = 20000;

    /**
     * Matriz cruda de la hoja, sin formato y con las columnas por indice.
     *
     * @return array<int, array<int, mixed>>
     *
     * @throws \RuntimeException si la hoja excede los limites razonables
     */
    public function filas(string $hoja): array
    {
        // Antes de tocar la hoja se comprueba que se pueda leer. Es lo que
        // impide que una hoja de resumen con referencias a columnas completas
        // deje el servidor sin memoria.
        $ilegibles = (new RevisorLibro($this->ruta))->hojasIlegibles();

        if (isset($ilegibles[$hoja])) {
            throw new \RuntimeException($ilegibles[$hoja]);
        }

        $lector = $this->lector();
        $lector->setLoadSheetsOnly([$hoja]);

        // Se descartan las celdas fuera del rango util antes de cargarlas: es lo
        // que impide que una hoja con formato disperso tumbe el proceso.
        $lector->setReadFilter(new RangoUtil(self::ULTIMA_COLUMNA, self::MAXIMO_FILAS));

        $libro = $lector->load($this->ruta);
        $pagina = $libro->getSheetByName($hoja);

        if ($pagina === null) {
            return [];
        }

        $ultimaFila = min($pagina->getHighestDataRow(), self::MAXIMO_FILAS);

        $ultimaColumna = $pagina->getHighestDataColumn();

        if (Coordinate::columnIndexFromString($ultimaColumna)
            > Coordinate::columnIndexFromString(self::ULTIMA_COLUMNA)) {
            $ultimaColumna = self::ULTIMA_COLUMNA;
        }

        // Se pide un rango acotado en vez de toda la hoja: toArray() sin rango
        // materializa una matriz hasta la ultima columna que la hoja crea tener.
        $filas = $pagina->rangeToArray(
            sprintf('A1:%s%d', $ultimaColumna, max(1, $ultimaFila)),
            null,
            true,
            false,
            false,
        );

        $libro->disconnectWorksheets();
        unset($libro);

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    public function detectar(string $hoja): array
    {
        return (new DetectorFormato)->detectar($this->filas($hoja));
    }

    private function lector(): IReader
    {
        $lector = IOFactory::createReaderForFile($this->ruta);

        // Solo interesan los valores: sin estilos ni formulas se lee mucho mas
        // rapido y los libros del MSPAS son grandes.
        $lector->setReadDataOnly(true);
        $lector->setReadEmptyCells(false);

        return $lector;
    }
}
