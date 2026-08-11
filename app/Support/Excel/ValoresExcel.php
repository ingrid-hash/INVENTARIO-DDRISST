<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

/**
 * Interpretacion de las celdas tal como vienen en los archivos del MSPAS.
 *
 * Los libros fueron capturados por distintas personas a lo largo de los anos, y
 * traen de todo: formulas rotas, fechas guardadas como numero de serie, montos
 * con simbolo de quetzal y codigos en dos formatos oficiales distintos.
 */
class ValoresExcel
{
    /** Codigo hexadecimal de 8 caracteres: el formato historico. */
    private const CODIGO_VIEJO = '/^[0-9A-Fa-f]{8}$/';

    /** Formato nuevo con anio y unidad: 2026-211-CHI-0013. */
    private const CODIGO_NUEVO = '/^\d{4}-\d{3}-[A-Za-z]{2,4}-\d{3,4}$/';

    /** Errores de formula que hay que leer como celda vacia. */
    private const ERRORES = ['#REF!', '#N/A', '#VALUE!', '#DIV/0!', '#NAME?', '#NULL!', '#NUM!'];

    public static function texto(mixed $valor): string
    {
        if (! is_scalar($valor)) {
            return '';
        }

        $texto = trim((string) $valor);

        // Un error de formula no es un dato.
        if (in_array(mb_strtoupper($texto), self::ERRORES, true)) {
            return '';
        }

        // Espacios duros y saltos repetidos que ensucian la comparacion.
        $texto = str_replace("\u{A0}", ' ', $texto);
        $texto = preg_replace('/[ \t]+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    public static function estaVacio(mixed $valor): bool
    {
        return self::texto($valor) === '';
    }

    public static function esCodigo(mixed $valor): bool
    {
        $texto = self::texto($valor);

        return $texto !== ''
            && (preg_match(self::CODIGO_VIEJO, $texto) === 1 || preg_match(self::CODIGO_NUEVO, $texto) === 1);
    }

    /** Normaliza el codigo para guardarlo: hexadecimal en mayusculas. */
    public static function codigo(mixed $valor): ?string
    {
        $texto = self::texto($valor);

        if ($texto === '') {
            return null;
        }

        return preg_match(self::CODIGO_VIEJO, $texto) === 1
            ? mb_strtoupper($texto)
            : mb_strtoupper($texto);
    }

    /**
     * Monto en quetzales. Tolera "Q 1,505.00", "1505", "1.505,00" y celdas que
     * ya vienen como numero.
     */
    public static function monto(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }

        $texto = self::texto($valor);

        if ($texto === '') {
            return null;
        }

        $texto = preg_replace('/[Qq\s]/', '', $texto) ?? $texto;

        // Formato con coma decimal: 1.505,00
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d{1,2}$/', $texto) === 1) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }

        return is_numeric($texto) ? round((float) $texto, 2) : null;
    }

    public static function entero(mixed $valor): ?int
    {
        $monto = self::monto($valor);

        return $monto === null ? null : (int) round($monto);
    }

    /**
     * Lo que la celda de fecha dice literalmente, para poder reimprimir la
     * tarjeta igual al papel firmado. En los archivos aparece como fecha
     * completa (21/08/2024), como numero de serie de Excel (45525) o solo como
     * el anio (2018).
     */
    public static function fechaTexto(mixed $valor): ?string
    {
        if (is_int($valor) || is_float($valor)) {
            $numero = (float) $valor;

            // Un anio suelto se guarda tal cual; un serial se convierte.
            if ($numero >= 1900 && $numero <= 2100 && floor($numero) === $numero) {
                return (string) (int) $numero;
            }

            if ($numero > 2100) {
                try {
                    return FechaExcel::excelToDateTimeObject($numero)->format('d/m/Y');
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }

        $texto = self::texto($valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * @return array{fecha: string|null, anio: int|null}  fecha en Y-m-d
     */
    public static function fecha(mixed $valor): array
    {
        if (is_int($valor) || is_float($valor)) {
            $numero = (float) $valor;

            if ($numero >= 1900 && $numero <= 2100 && floor($numero) === $numero) {
                return ['fecha' => null, 'anio' => (int) $numero];
            }

            if ($numero > 2100) {
                try {
                    $fecha = FechaExcel::excelToDateTimeObject($numero);

                    return ['fecha' => $fecha->format('Y-m-d'), 'anio' => (int) $fecha->format('Y')];
                } catch (\Throwable) {
                    return ['fecha' => null, 'anio' => null];
                }
            }

            return ['fecha' => null, 'anio' => null];
        }

        $texto = self::texto($valor);

        if ($texto === '') {
            return ['fecha' => null, 'anio' => null];
        }

        // Solo el anio
        if (preg_match('/^(19|20)\d{2}$/', $texto) === 1) {
            return ['fecha' => null, 'anio' => (int) $texto];
        }

        // d/m/Y o d-m-Y
        if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $texto, $partes) === 1) {
            [, $dia, $mes, $anio] = $partes;

            if (checkdate((int) $mes, (int) $dia, (int) $anio)) {
                return [
                    'fecha' => sprintf('%04d-%02d-%02d', $anio, $mes, $dia),
                    'anio' => (int) $anio,
                ];
            }
        }

        // Cualquier anio que aparezca dentro del texto sirve de referencia.
        if (preg_match('/\b((?:19|20)\d{2})\b/', $texto, $encontrado) === 1) {
            return ['fecha' => null, 'anio' => (int) $encontrado[1]];
        }

        return ['fecha' => null, 'anio' => null];
    }

    /**
     * Fila de renglon presupuestario: "1232.03 MOBILIARIO Y EQUIPO DE OFICINA".
     * No es un bien, es el encabezado de la seccion que sigue.
     *
     * @return array{codigo: string, nombre: string}|null
     */
    public static function renglonPresupuestario(mixed $valor): ?array
    {
        $texto = self::texto($valor);

        if ($texto === '' || preg_match('/^\s*(12\d\d\.\d\d)\s*(.*)$/u', $texto, $partes) !== 1) {
            return null;
        }

        $nombre = trim((string) preg_replace('/[.\s…]+$/u', '', $partes[2]));

        return ['codigo' => $partes[1], 'nombre' => mb_strtoupper($nombre)];
    }

    /** Filas de corte contable que no son bienes. */
    public static function esCorteContable(string $texto): bool
    {
        $t = mb_strtoupper(self::texto($texto));

        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(VAN|VIENEN?|VIENE DE LA TARJETA|TOTAL|SUMA|SALDO|SALDO INICIAL|SUB ?TOTAL|VIENE)\b/u',
            $t,
        );
    }

    /**
     * Linea de relleno del formato impreso: los espacios para firmar, que en el
     * archivo aparecen como una fila de guiones bajos.
     */
    public static function esLineaDeRelleno(string $texto): bool
    {
        $t = self::texto($texto);

        if ($t === '' || mb_strlen($t) < 4) {
            return false;
        }

        // Se mide la proporcion en vez de exigir un patron exacto, porque estas
        // celdas suelen traer una letra o un rotulo corto pegado adelante.
        $relleno = preg_match_all('/[_\-.…]/u', $t);

        return $relleno !== false && $relleno / mb_strlen($t) >= 0.6;
    }

    /** Bloque de firmas y rotulos del encabezado impreso. */
    public static function esRotuloDeFormato(string $texto): bool
    {
        $t = mb_strtoupper(self::texto($texto));

        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/(UNIDAD DE SERVICIO|EMPLEADO RESPONSABLE|ENCARGAD[OA] DE INVENTARIO|VO\.? ?BO\.?|DIRECTOR|APERTURA DEL INVENTARIO|^NOMBRE\s*:|^CARGO\s*:|^MUNICIPIO\s*:|^DEPTO|^DEPARTAMENTO\s*:|^FECHA$|^DESCRIPCION$|^CODIGO$)/u',
            $t,
        );
    }
}
