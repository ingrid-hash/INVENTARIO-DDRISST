<?php

namespace App\Support\Excel;

/**
 * Averigua donde esta cada dato dentro de una hoja de Excel.
 *
 * No se apoya en posiciones fijas porque no existen: en los archivos entregados
 * el codigo aparece en la columna C, E, F, G u H segun quien capturo la hoja. Lo
 * que se busca es la fila que rotula las columnas y, si no aparece, se deduce
 * mirando el contenido de cada columna.
 */
class DetectorFormato
{
    /**
     * Sinonimos de cada campo tal como aparecen rotulados en los archivos.
     *
     * @var array<string, array<int, string>>
     */
    private const ROTULOS = [
        'codigo' => ['CODIGO', 'CÓDIGO', 'COD', 'NO. INVENTARIO', 'TARJETA'],
        'descripcion' => ['DESCRIPCION', 'DESCRIPCIÓN', 'DETALLE', 'BIEN', 'CONCEPTO'],
        'cantidad' => ['CANT', 'CANT.', 'CANTIDAD', 'NO.', 'NO', 'No.'],
        'precio_unitario' => ['PRECIO/U', 'PRECIO U', 'PRECIO UNITARIO', 'VALOR UNITARIO', 'P/U'],
        'total' => ['TOTAL', 'MONTO', 'DEBE', 'VALOR', 'SUB TOTAL'],
        'cuenta' => ['CUENTA', 'RENGLON', 'RENGLÓN'],
        'fecha' => ['FECHA'],
        'haber' => ['HABER'],
        'saldo' => ['SALDO'],
        'observaciones' => ['OBSERVACIONES', 'OBSEVACIONES', 'OBS'],
        'responsable' => ['RESPONSABLE', 'NOMBRE', 'ENCARGADO'],
        'oficina' => ['OFICINA', 'AREA', 'ÁREA', 'UBICACION', 'UBICACIÓN'],
    ];

    /**
     * @param  array<int, array<int, mixed>>  $filas  matriz cruda de la hoja
     * @return array{
     *     fila_encabezado: int|null,
     *     mapeo: array<string, int>,
     *     tipo_sugerido: string,
     *     confianza: int,
     *     columnas: array<int, string>
     * }
     */
    public function detectar(array $filas): array
    {
        $encabezado = $this->buscarFilaEncabezado($filas);

        $mapeo = $encabezado !== null
            ? $this->mapearDesdeRotulos($filas[$encabezado])
            : [];

        // Lo que no se pudo leer del rotulo se deduce del contenido.
        $mapeo = $this->completarPorContenido($filas, $mapeo, $encabezado);

        return [
            'fila_encabezado' => $encabezado !== null ? $encabezado + 1 : null,
            'mapeo' => $mapeo,
            'tipo_sugerido' => $this->sugerirTipo($filas, $mapeo),
            'confianza' => $this->confianza($mapeo),
            'columnas' => $this->muestraDeColumnas($filas),
        ];
    }

    /**
     * La fila de rotulos es la que reune mas nombres de columna conocidos.
     *
     * @param  array<int, array<int, mixed>>  $filas
     */
    private function buscarFilaEncabezado(array $filas): ?int
    {
        $mejor = null;
        $mejorPuntaje = 0;

        // Los rotulos siempre estan en las primeras filas de la hoja.
        foreach (array_slice($filas, 0, 30, true) as $indice => $fila) {
            $puntaje = 0;

            foreach ($fila as $celda) {
                $texto = mb_strtoupper(ValoresExcel::texto($celda));

                if ($texto === '') {
                    continue;
                }

                foreach (self::ROTULOS as $sinonimos) {
                    if (in_array($texto, $sinonimos, true)) {
                        $puntaje++;
                        break;
                    }
                }
            }

            // Con dos rotulos ya se distingue de una fila de datos.
            if ($puntaje >= 2 && $puntaje > $mejorPuntaje) {
                $mejor = $indice;
                $mejorPuntaje = $puntaje;
            }
        }

        return $mejor;
    }

    /**
     * @param  array<int, mixed>  $fila
     * @return array<string, int>
     */
    private function mapearDesdeRotulos(array $fila): array
    {
        $mapeo = [];

        foreach ($fila as $columna => $celda) {
            $texto = mb_strtoupper(ValoresExcel::texto($celda));

            if ($texto === '') {
                continue;
            }

            foreach (self::ROTULOS as $campo => $sinonimos) {
                // El primer rotulo gana: en las tarjetas hay dos columnas
                // rotuladas CODIGO y la buena es la primera que trae codigos.
                if (! isset($mapeo[$campo]) && in_array($texto, $sinonimos, true)) {
                    $mapeo[$campo] = $columna;
                    break;
                }
            }
        }

        return $mapeo;
    }

    /**
     * Deduce las columnas que faltan mirando que contiene cada una.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<string, int>  $mapeo
     * @return array<string, int>
     */
    private function completarPorContenido(array $filas, array $mapeo, ?int $filaEncabezado): array
    {
        $desde = ($filaEncabezado ?? -1) + 1;
        $datos = array_slice($filas, $desde, 400);

        $codigos = [];
        $textos = [];
        $numeros = [];

        foreach ($datos as $fila) {
            foreach ($fila as $columna => $celda) {
                if (ValoresExcel::esCodigo($celda)) {
                    $codigos[$columna] = ($codigos[$columna] ?? 0) + 1;

                    continue;
                }

                $texto = ValoresExcel::texto($celda);

                if ($texto === '') {
                    continue;
                }

                // Una descripcion es texto largo, no un numero ni un rotulo.
                if (mb_strlen($texto) >= 15 && ! is_numeric($texto)) {
                    $textos[$columna] = ($textos[$columna] ?? 0) + 1;
                }

                if (ValoresExcel::monto($celda) !== null) {
                    $numeros[$columna] = ($numeros[$columna] ?? 0) + 1;
                }
            }
        }

        // La columna del codigo es la que mas codigos validos contiene.
        if (! isset($mapeo['codigo']) && $codigos !== []) {
            $mapeo['codigo'] = (int) array_search(max($codigos), $codigos, true);
        } elseif (isset($mapeo['codigo']) && ($codigos[$mapeo['codigo']] ?? 0) === 0 && $codigos !== []) {
            // El rotulo decia CODIGO pero la columna no trae codigos: manda el
            // contenido. Pasa en las tarjetas, donde hay dos columnas asi.
            $mapeo['codigo'] = (int) array_search(max($codigos), $codigos, true);
        }

        if (! isset($mapeo['descripcion']) && $textos !== []) {
            $mapeo['descripcion'] = (int) array_search(max($textos), $textos, true);
        }

        // Si no hubo rotulo de monto, se toma la columna numerica mas poblada
        // que no sea ya la cantidad.
        if (! isset($mapeo['total']) && ! isset($mapeo['precio_unitario']) && $numeros !== []) {
            $candidatas = $numeros;
            unset($candidatas[$mapeo['cantidad'] ?? -1]);

            if ($candidatas !== []) {
                $mapeo['total'] = (int) array_search(max($candidatas), $candidatas, true);
            }
        }

        return $mapeo;
    }

    /**
     * Una hoja es una tarjeta de responsabilidad si trae el encabezado con el
     * nombre del empleado; si no, es un listado general de la unidad.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<string, int>  $mapeo
     */
    private function sugerirTipo(array $filas, array $mapeo): string
    {
        foreach (array_slice($filas, 0, 20) as $fila) {
            foreach ($fila as $celda) {
                $texto = mb_strtoupper(ValoresExcel::texto($celda));

                if ($texto !== '' && preg_match('/^NOMBRE\s*:/u', $texto) === 1) {
                    return 'tarjeta';
                }
            }
        }

        return 'listado';
    }

    /**
     * @param  array<string, int>  $mapeo
     */
    private function confianza(array $mapeo): int
    {
        // Sin codigo y descripcion no se puede importar nada.
        $puntos = 0;
        $puntos += isset($mapeo['codigo']) ? 45 : 0;
        $puntos += isset($mapeo['descripcion']) ? 35 : 0;
        $puntos += isset($mapeo['total']) || isset($mapeo['precio_unitario']) ? 15 : 0;
        $puntos += isset($mapeo['cantidad']) ? 5 : 0;

        return $puntos;
    }

    /**
     * Un ejemplo del contenido de cada columna, para que quien importa pueda
     * corregir el mapeo con algo concreto a la vista.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @return array<int, string>
     */
    private function muestraDeColumnas(array $filas): array
    {
        $muestra = [];

        foreach (array_slice($filas, 0, 120) as $fila) {
            foreach ($fila as $columna => $celda) {
                $texto = ValoresExcel::texto($celda);

                if ($texto === '' || isset($muestra[$columna])) {
                    continue;
                }

                if (ValoresExcel::esRotuloDeFormato($texto) || ValoresExcel::esCorteContable($texto)) {
                    continue;
                }

                $muestra[$columna] = mb_substr($texto, 0, 40);
            }
        }

        ksort($muestra);

        return $muestra;
    }
}
