<?php

namespace App\Services\Importacion;

use ZipArchive;


class RevisorLibro
{
    /**
     * Referencia a una columna o fila completa dentro de una formula:
     *   F:F        A:C        $B:$B        'MARTA VELASQUEZ'!F:F        1:1
     */
    private const RANGO_COMPLETO = '/(?:\$?[A-Z]{1,3}:\$?[A-Z]{1,3}|\$?\d{1,7}:\$?\d{1,7})/';

    public function __construct(private readonly string $ruta) {}

    /**
     * Hojas que no conviene leer, con el motivo en palabras que entienda quien
     * esta importando.
     *
     * @return array<string, string>  nombre de la hoja => motivo
     */
    public function hojasIlegibles(): array
    {
        $zip = new ZipArchive;

        if ($zip->open($this->ruta) !== true) {
            return [];
        }

        $problemas = [];

        foreach ($this->nombresPorArchivo($zip) as $archivo => $nombre) {
            $xml = $zip->getFromName($archivo);

            if ($xml === false) {
                continue;
            }

            $motivo = $this->revisar($xml);

            if ($motivo !== null) {
                $problemas[$nombre] = $motivo;
            }
        }

        $zip->close();

        return $problemas;
    }

    /**
     * Devuelve el motivo por el que una hoja no se puede leer, o null si esta
     * bien.
     */
    private function revisar(string $xml): ?string
    {
        if (preg_match_all('#<f[^>]*>([^<]+)</f>#', $xml, $formulas) === 0) {
            return null;
        }

        $conRangoCompleto = 0;

        foreach ($formulas[1] as $formula) {
            // Las entidades del XML se decodifican: el operador <> viene como
            // &lt;&gt; y esconderia la referencia.
            $formula = html_entity_decode($formula, ENT_QUOTES | ENT_XML1);

            if (preg_match(self::RANGO_COMPLETO, $formula) === 1) {
                $conRangoCompleto++;
            }
        }

        if ($conRangoCompleto === 0) {
            return null;
        }

        return sprintf(
            'Tiene %d fórmula(s) que hacen referencia a columnas completas de otras hojas '
            .'(por ejemplo F:F). Son hojas de resumen: no contienen bienes que importar, '
            .'y leerlas consumiría toda la memoria del servidor.',
            $conRangoCompleto,
        );
    }

    /**
     * Relaciona cada archivo sheetN.xml con el nombre visible de su hoja.
     *
     * El orden de <sheet> en workbook.xml coincide con la numeracion de los
     * archivos de hoja, que es como los nombra el propio formato.
     *
     * @return array<string, string>  ruta del xml => nombre de la hoja
     */
    private function nombresPorArchivo(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');

        if ($workbook === false) {
            return [];
        }

        $xml = @simplexml_load_string($workbook);

        if ($xml === false || ! isset($xml->sheets->sheet)) {
            return [];
        }

        $mapa = [];
        $indice = 1;

        foreach ($xml->sheets->sheet as $hoja) {
            $mapa["xl/worksheets/sheet{$indice}.xml"] = (string) $hoja['name'];
            $indice++;
        }

        return $mapa;
    }
}
