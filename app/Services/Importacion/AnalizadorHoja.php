<?php

namespace App\Services\Importacion;

use App\Support\Excel\ValoresExcel;


class AnalizadorHoja
{
    /**
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<string, int>  $mapeo  campo => indice de columna
     * @return array{
     *     bienes: array<int, array<string, mixed>>,
     *     errores: array<int, array<string, mixed>>,
     *     encabezado: array<string, string|null>,
     *     renglones_detectados: array<string, string>,
     *     descartadas: int
     * }
     */
    public function analizar(array $filas, array $mapeo, ?int $filaEncabezado = null): array
    {
        $bienes = [];
        $errores = [];
        $renglonesDetectados = [];
        $descartadas = 0;

        // La cuenta se arrastra hacia abajo: en el documento se escribe una sola
        // vez y vale para los bienes que siguen, hasta que aparece otra.
        $cuentaVigente = null;
        $textoCuentaVigente = null;

        $encabezado = $this->leerEncabezado($filas);

        $vistos = [];
        $desde = ($filaEncabezado ?? 0);

        
        $hojaOrigen = 1;

       
        $puedeCortar = false;

        
        if ($filaEncabezado !== null && isset($filas[$filaEncabezado - 1])) {
            $renglonInicial = $this->buscarRenglonPresupuestario($filas[$filaEncabezado - 1]);

            if ($renglonInicial !== null) {
                $cuentaVigente = $renglonInicial['codigo'];
                $renglonesDetectados[$renglonInicial['codigo']] = $renglonInicial['nombre'];
            }
        }

        foreach ($filas as $indice => $fila) {
            $numeroFila = $indice + 1;

            if ($numeroFila <= $desde) {
                continue;
            }

            // Fila de renglon presupuestario: marca la seccion que sigue.
            $renglon = $this->buscarRenglonPresupuestario($fila);

            if ($renglon !== null) {
                $cuentaVigente = $renglon['codigo'];
                $renglonesDetectados[$renglon['codigo']] = $renglon['nombre'];
                $descartadas++;

                continue;
            }

            // Fila de TOTAL: cierra la adicion anterior. No es un bien, pero el
            // monto escrito en el papel si se guarda, pegado al ultimo bien del
            // grupo, para poder reimprimir la tarjeta con el mismo total que
            // firmaron aunque no cuadre con la suma.
            $totalDeGrupo = $this->montoDeTotalDeGrupo($fila, $mapeo);

            if ($totalDeGrupo !== null) {
                if ($bienes !== []) {
                    $bienes[count($bienes) - 1]['total_corte_original'] = $totalDeGrupo;
                }

                $descartadas++;

                continue;
            }

            // Corte de hoja: lo que sigue ya se imprimio en otro papel.
            if ($puedeCortar && $this->esCortePagina($fila)) {
                $hojaOrigen++;
                $puedeCortar = false;
                $descartadas++;

                continue;
            }

            if ($this->esFilaDescartable($fila)) {
                $descartadas++;

                continue;
            }

            $codigo = ValoresExcel::codigo($this->celda($fila, $mapeo, 'codigo'));
            $descripcion = ValoresExcel::texto($this->celda($fila, $mapeo, 'descripcion'));

            // Sin descripcion no hay bien que registrar; suele ser una fila de
            // relleno del formato.
            if ($descripcion === '' || mb_strlen($descripcion) < 5) {
                $descartadas++;

                continue;
            }

            // La celda CUENTA de las tarjetas mezcla cuatro cosas distintas.
            $celdaCuenta = ValoresExcel::texto($this->celda($fila, $mapeo, 'cuenta'));
            $desglose = $this->desglosarCeldaCuenta($celdaCuenta);

            if ($desglose['cuenta'] !== null) {
                $cuentaVigente = $desglose['cuenta'];
                $textoCuentaVigente = $celdaCuenta;
            }

            // Una fila sin codigo no se descarta: de ella se sabe lo esencial
            // —que bien es, cuanto vale, de que unidad y de quien— y en los
            // archivos del MSPAS hay bastantes asi. Entra con un codigo que
            // genera el sistema y queda marcada para corregirla despues.
            $sinCodigo = $codigo === null;

            if (! $sinCodigo && isset($vistos[$codigo])) {
                $errores[] = [
                    'fila' => $numeroFila,
                    'codigo' => $codigo,
                    'motivo_clave' => 'codigo_duplicado',
                    'motivo' => sprintf('El código ya apareció en la fila %d de esta misma hoja.', $vistos[$codigo]),
                    'datos' => ['descripcion' => mb_substr($descripcion, 0, 120)],
                ];

                continue;
            }

            if (! $sinCodigo) {
                $vistos[$codigo] = $numeroFila;
            }

            $cantidad = ValoresExcel::entero($this->celda($fila, $mapeo, 'cantidad')) ?? 1;
            $cantidad = max(1, $cantidad);

            $precio = ValoresExcel::monto($this->celda($fila, $mapeo, 'precio_unitario'));
            $total = ValoresExcel::monto($this->celda($fila, $mapeo, 'total'));

            // Los listados traen precio unitario y total; las tarjetas solo el
            // monto en la columna DEBE. Se completa el que falte.
            if ($precio === null && $total !== null) {
                $precio = $cantidad > 0 ? round($total / $cantidad, 2) : $total;
            }

            if ($total === null && $precio !== null) {
                $total = round($precio * $cantidad, 2);
            }

            // La columna HABER es un descargo: el bien sale de la tarjeta y su
            // monto se resta del saldo. Sin esto los totales de las adiciones
            // posteriores al descargo quedan inflados.
            $haber = ValoresExcel::monto($this->celda($fila, $mapeo, 'haber')) ?? 0.0;

            // Una linea de descargo no trae monto en el DEBE, pero el bien si
            // vale lo que dice el HABER.
            if (($total === null || $total === 0.0) && $haber > 0) {
                $total = $haber;
                $precio = $cantidad > 0 ? round($haber / $cantidad, 2) : $haber;
            }

            $celdaFecha = $this->celda($fila, $mapeo, 'fecha');
            $fecha = ValoresExcel::fecha($celdaFecha);

            $bienes[] = [
                'fila' => $numeroFila,

                // La posicion que ocupa dentro del documento. Se cuenta aparte
                // del numero de fila del Excel porque entre bien y bien hay
                // encabezados, cortes y filas de seccion.
                'posicion' => count($bienes) + 1,

                'codigo' => $codigo,
                'sin_codigo' => $sinCodigo,
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio ?? 0.0,
                'total' => $total ?? 0.0,

                // Lo que va en la columna HABER de la tarjeta: un descargo.
                'haber' => $haber,

                // Cuenta estructurada, arrastrada de la seccion o de la celda.
                'cuenta' => $desglose['cuenta'] ?? $cuentaVigente,

                // El texto literal de la celda, para reimprimir igual al papel.
                'cuenta_texto_original' => $celdaCuenta !== '' ? $celdaCuenta : $textoCuentaVigente,

                'tipo_movimiento' => $desglose['es_adicion'] ? 'adicion' : 'apertura',
                'forma_adquisicion' => $desglose['forma_adquisicion'],
                'programa' => $desglose['programa'],
                'documento_respaldo' => $desglose['documento'],

                'fecha_texto_original' => ValoresExcel::fechaTexto($celdaFecha),
                'fecha_ingreso' => $fecha['fecha'],
                'anio_ingreso' => $fecha['anio'],

                // Lo llena la fila de TOTAL que venga despues, si la hay.
                'total_corte_original' => null,

                'observaciones' => ValoresExcel::texto($this->celda($fila, $mapeo, 'observaciones')) ?: null,
                'responsable_en_archivo' => ValoresExcel::texto($this->celda($fila, $mapeo, 'responsable')) ?: null,

                // Hoja de papel donde este bien venia impreso en el archivo.
                'hoja_origen' => $hojaOrigen,
            ];

            $puedeCortar = true;
        }

        return [
            'bienes' => $bienes,
            'errores' => $errores,
            'encabezado' => $encabezado,
            'renglones_detectados' => $renglonesDetectados,
            'descartadas' => $descartadas,
        ];
    }

    /**
     * Desarma la celda CUENTA, que en los archivos lleva mezclados hasta cuatro
     * datos separados por saltos de linea: la cuenta, como se adquirio el bien,
     * si es una adicion y el oficio de respaldo.
     *
     * @return array{cuenta: string|null, forma_adquisicion: string|null, programa: string|null, documento: string|null, es_adicion: bool}
     */
    private function desglosarCeldaCuenta(string $celda): array
    {
        $resultado = [
            'cuenta' => null,
            'forma_adquisicion' => null,
            'programa' => null,
            'documento' => null,
            'es_adicion' => false,
        ];

        if ($celda === '') {
            return $resultado;
        }

        foreach (preg_split('/\r\n|\r|\n/', $celda) ?: [] as $linea) {
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            $mayus = mb_strtoupper($linea);

            if (preg_match('/^(12\d\d\.\d\d)/', $linea, $partes) === 1) {
                $resultado['cuenta'] = $partes[1];

                continue;
            }

            if (str_contains($mayus, 'ADICION') || str_contains($mayus, 'ADICIÓN')) {
                $resultado['es_adicion'] = true;

                // A veces la adicion trae el oficio en la misma linea.
                if (preg_match('#(S/?OF[.\s]?[\w.\-/]+)#i', $linea, $oficio) === 1) {
                    $resultado['documento'] = trim($oficio[1]);
                }

                continue;
            }

            if (str_contains($mayus, 'COMPRA')) {
                $resultado['forma_adquisicion'] = 'compra';

                continue;
            }

            if (str_contains($mayus, 'DONACION') || str_contains($mayus, 'DONACIÓN')) {
                $resultado['forma_adquisicion'] = 'donacion';

                continue;
            }

            if (preg_match('#^(S/?OF|OFICIO|OF\.)#i', $linea) === 1) {
                $resultado['documento'] = $linea;

                continue;
            }

            // Lo que queda y no es un numero suelto es el programa que financio
            // el bien: CRECER SANO, VIH, etc.
            if (! is_numeric($linea) && mb_strlen($linea) >= 3) {
                $resultado['programa'] = $mayus;
                $resultado['forma_adquisicion'] ??= 'donacion';
            }
        }

        return $resultado;
    }

    /**
     * Datos del encabezado impreso de una tarjeta: a quien pertenece y de que
     * unidad es.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @return array<string, string|null>
     */
    private function leerEncabezado(array $filas): array
    {
        $encabezado = [
            'unidad_servicio' => null,
            'municipio' => null,
            'departamento' => null,
            'nombre' => null,
            'cargo' => null,
            'area_trabajo' => null,
        ];

        // En una misma celda vienen varias etiquetas seguidas, por ejemplo:
        // "UNIDAD DE SERVICIO: PUESTO DE SALUD DE X MUNICIPIO: Y DEPARTAMENTO: Z"
        // Se corta en la siguiente etiqueta conocida, no por espacios: la
        // limpieza de celdas ya los reduce a uno solo.
        $etiquetas = 'UNIDAD DE SERVICIO|MUNICIPIO|DEPARTAMENTO|DEPTO|CARGO|NOMBRE|FECHA';

        $extraer = function (string $texto, string $etiqueta) use ($etiquetas): ?string {
            $patron = sprintf('/%s\s*:\s*(.+?)(?=\s*(?:%s)\s*:|$)/iu', $etiqueta, $etiquetas);

            if (preg_match($patron, $texto, $partes) !== 1) {
                return null;
            }

            $valor = trim((string) preg_replace('/\s+/', ' ', $partes[1]));

            return $valor === '' ? null : mb_strtoupper($valor);
        };

        foreach (array_slice($filas, 0, 25) as $fila) {
            foreach ($fila as $celda) {
                $texto = ValoresExcel::texto($celda);

                if ($texto === '' || ! str_contains($texto, ':')) {
                    continue;
                }

                $mayus = mb_strtoupper($texto);

                // El formato repite la palabra DEPARTAMENTO con dos sentidos: en
                // la linea de la unidad es el departamento geografico, y en la
                // del empleado es su area de trabajo. Se distinguen por la
                // etiqueta que las acompana en la misma celda.
                $esLineaDeUnidad = str_contains($mayus, 'UNIDAD DE SERVICIO')
                    || str_contains($mayus, 'MUNICIPIO');

                $esLineaDeEmpleado = preg_match('/(^|\s)(NOMBRE|CARGO)\s*:/u', $mayus) === 1;

                if ($esLineaDeUnidad) {
                    $encabezado['unidad_servicio'] ??= $extraer($texto, 'UNIDAD DE SERVICIO');
                    $encabezado['municipio'] ??= $extraer($texto, 'MUNICIPIO');
                    $encabezado['departamento'] ??= $extraer($texto, 'DEPARTAMENTO')
                        ?? $extraer($texto, 'DEPTO');
                }

                if ($esLineaDeEmpleado) {
                    $encabezado['nombre'] ??= $extraer($texto, 'NOMBRE');
                    $encabezado['cargo'] ??= $extraer($texto, 'CARGO');
                    $encabezado['area_trabajo'] ??= $extraer($texto, 'DEPARTAMENTO')
                        ?? $extraer($texto, 'DEPTO');
                }
            }
        }

        // Los titulos delante del nombre son la causa mas comun de que la misma
        // persona termine registrada dos veces.
        if ($encabezado['nombre'] !== null) {
            $encabezado['nombre'] = trim((string) preg_replace(
                '/^(DOCTOR|DOCTORA|DR|DRA|LIC|LICDA|ING|MSC|EP|SR|SRA)\.?\s+/u',
                '',
                $encabezado['nombre'],
            ));
        }

        return $encabezado;
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    private function buscarRenglonPresupuestario(array $fila): ?array
    {
        foreach ($fila as $celda) {
            $renglon = ValoresExcel::renglonPresupuestario($celda);

            // Solo cuenta como fila de seccion si trae nombre: "1232.03" a secas
            // dentro de la columna CUENTA pertenece a un bien.
            if ($renglon !== null && $renglon['nombre'] !== '') {
                return $renglon;
            }
        }

        return null;
    }

    /**
     * El monto de una fila de TOTAL, que es la que cierra una adicion.
     *
     * Se distingue de VAN y VIENEN a proposito: esos dos son cortes de hoja, no
     * cierres de adicion, y el sistema los calcula solo al paginar.
     *
     * @param  array<int, mixed>  $fila
     * @param  array<string, int>  $mapeo
     */
    private function montoDeTotalDeGrupo(array $fila, array $mapeo): ?float
    {
        $rotulada = false;

        foreach ($fila as $celda) {
            $texto = mb_strtoupper(ValoresExcel::texto($celda));

            if ($texto !== '' && preg_match('/^(TOTAL|SUB ?TOTAL|SUMA)\b/u', $texto) === 1) {
                $rotulada = true;

                break;
            }
        }

        if (! $rotulada) {
            return null;
        }

        // Lo normal es que el monto este en la misma columna del DEBE.
        $monto = ValoresExcel::monto($this->celda($fila, $mapeo, 'total'));

        if ($monto !== null) {
            return $monto;
        }

        // Si no, se toma el ultimo monto de la fila: en algunos archivos el
        // TOTAL queda corrido una columna.
        foreach (array_reverse($fila, true) as $celda) {
            $valor = ValoresExcel::monto($celda);

            if ($valor !== null) {
                return $valor;
            }
        }

        return null;
    }

    /**
     * Fila que marca el final de una hoja de papel y el comienzo de la
     * siguiente: VAN al pie, VIENEN en la cabecera.
     *
     * "VIENE DE LA TARJETA" queda fuera a proposito: eso no corta una hoja, dice
     * de que tarjeta anterior arrastra el saldo.
     *
     * @param  array<int, mixed>  $fila
     */
    private function esCortePagina(array $fila): bool
    {
        foreach ($fila as $celda) {
            $texto = mb_strtoupper(ValoresExcel::texto($celda));

            if ($texto !== '' && preg_match('/^(VAN|VIENEN)\b/u', $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    private function esFilaDescartable(array $fila): bool
    {
        $textos = [];

        foreach ($fila as $celda) {
            $texto = ValoresExcel::texto($celda);

            if ($texto !== '') {
                $textos[] = $texto;
            }
        }

        if ($textos === []) {
            return true;
        }

        foreach ($textos as $texto) {
            if (ValoresExcel::esCorteContable($texto)
                || ValoresExcel::esRotuloDeFormato($texto)
                || ValoresExcel::esLineaDeRelleno($texto)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array<string, int>  $mapeo
     */
    private function celda(array $fila, array $mapeo, string $campo): mixed
    {
        if (! isset($mapeo[$campo])) {
            return null;
        }

        return $fila[$mapeo[$campo]] ?? null;
    }
}
