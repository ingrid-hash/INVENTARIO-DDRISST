<?php

namespace App\Services;

/**
 * Las medidas de la tarjeta de responsabilidad, en milimetros.
 *
 * Estaban repartidas entre la plantilla de impresion, la pantalla de calce y el
 * archivo de Excel de la institucion, y las tres no coincidian. Ahora viven
 * aqui y las tres leen de este mismo lugar.
 *
 * Todo sale de medir el formato oficial "FORMATO TARJETAS 2026", que la
 * institucion arma en Excel: papel apaisado, escala de impresion al 75 % y los
 * anchos de columna que trae ese archivo. La unica diferencia es el ancho del
 * papel: el formato esta configurado en Legal y aca se usa oficio de Guatemala,
 * que es 26 mm mas corto, asi que el margen izquierdo baja de 33 a 20 mm para
 * que las columnas entren con su medida original.
 */
final class GeometriaTarjeta
{
    /** Oficio de Guatemala, apaisado. */
    public const PAPEL_ANCHO_MM = 330.0;

    public const PAPEL_ALTO_MM = 215.9;

    public const MARGEN_IZQUIERDO_MM = 20.0;

    public const MARGEN_DERECHO_MM = 9.2;

    public const MARGEN_INFERIOR_MM = 9.0;

    /**
     * El frente del papel trae impreso el escudo de la Contraloria General de
     * Cuentas y hay que dejarlo libre. El reverso no lo lleva.
     */
    public const MARGEN_SUPERIOR_FRENTE_MM = 32.0;

    public const MARGEN_SUPERIOR_REVERSO_MM = 10.0;

    /**
     * Unidad de servicio, nombre del empleado y su cargo. Solo va en el frente:
     * el formato oficial no lo repite al dorso, que arranca directo en los
     * rotulos de columna y el VIENEN.
     */
    public const ALTO_ENCABEZADO_MM = 14.5;

    public const ALTO_ROTULOS_MM = 7.9;

    /** La linea de firma y su rotulo, al pie de cada hoja. */
    public const ALTO_FIRMAS_MM = 8.3;

    /**
     * Espacio en blanco arriba de la linea de firma. Esta reservado siempre:
     * sobre la linea tiene que caber una firma de puno y letra, asi que el
     * texto de la tarjeta no puede llegar hasta ahi por mas que sobre trabajo.
     */
    public const ESPACIO_FIRMA_MM = 10.0;

    /** La linea de pie: hoja, papel, calce y version. */
    public const ALTO_PIE_MM = 4.0;

    /**
     * Lo que el formato oficial deja sin usar al pie. Se respeta para que la
     * tinta nunca llegue a tocar la linea de firmas.
     */
    public const RESERVA_MM = 7.9;

    /** Alto de un renglon de una sola linea, y lo que suma cada linea de mas. */
    public const ALTO_LINEA_MM = 3.97;

    public const LINEA_EXTRA_MM = 3.26;

    /**
     * Anchos de columna del formato oficial, ya llevados a la escala del papel.
     *
     * @var array<string, float>
     */
    public const COLUMNAS = [
        'fecha' => 16.5,
        'codigo' => 18.5,
        'cant' => 16.3,
        'desc' => 97.8,
        'debe' => 19.1,
        'haber' => 18.1,
        'saldo' => 21.6,
        'firma' => 46.7,
        'obs' => 46.4,
    ];

    /**
     * Tipografia y tamano del detalle.
     *
     * El 7 pt no es un gusto: es la medida con la que una descripcion parte en
     * los mismos renglones que el archivo de la institucion. Se comprobo contra
     * las catorce descripciones de ese archivo y coinciden todas.
     */
    public const FUENTE = 'Helvetica';

    public const TAMANO_PT = 7.0;

    /** Relleno horizontal de la celda, a cada lado. */
    public const RELLENO_CELDA_MM = 1.2;

    /**
     * Lo que ocupa la raya de la cuadricula entre un renglon y el siguiente.
     * Entra en la cuenta del alto: sin esto el sistema mide de menos y la hoja
     * se desborda por unos milimetros.
     */
    public const BORDE_MM = 0.33;

    /**
     * El encabezado arranca con un renglon de aire que el generador de PDF pone
     * por su cuenta. Se descuenta del interlineado para que el bloque mida los
     * milimetros que el formato le da y no mas.
     */
    public const DESFASE_ENCABEZADO_MM = 6.2;

    /**
     * Hasta donde se puede apretar el texto de una hoja para que cierre sin
     * pasarse. Por debajo de esto deja de leerse.
     */
    public const ESCALA_MINIMA = 0.85;

    public static function anchoTabla(): float
    {
        return array_sum(self::COLUMNAS);
    }

    public static function margenSuperior(string $cara): float
    {
        return $cara === 'frente'
            ? self::MARGEN_SUPERIOR_FRENTE_MM
            : self::MARGEN_SUPERIOR_REVERSO_MM;
    }

    /**
     * Milimetros disponibles para renglones en una hoja.
     *
     * Frente: 126.3 mm, que son 31 renglones de una linea.
     * Reverso: 162.8 mm, que son 41, porque no gasta ni el escudo ni el
     * encabezado.
     */
    public static function bandaMm(string $cara): float
    {
        $alto = self::PAPEL_ALTO_MM
            - self::margenSuperior($cara)
            - self::MARGEN_INFERIOR_MM
            - self::ALTO_ROTULOS_MM
            - self::ALTO_FIRMAS_MM
            - self::ESPACIO_FIRMA_MM
            - self::RESERVA_MM;

        if ($cara === 'frente') {
            $alto -= self::ALTO_ENCABEZADO_MM;
        }

        return round($alto, 2);
    }

    /** Ancho util de una columna, descontado el relleno de la celda. */
    public static function anchoUtil(string $columna): float
    {
        return self::COLUMNAS[$columna] - 2 * self::RELLENO_CELDA_MM;
    }
}
