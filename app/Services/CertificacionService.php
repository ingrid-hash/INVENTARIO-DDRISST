<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\CertificacionFormato;
use App\Models\UnidadServicio;
use Carbon\CarbonInterface;

/**
 * Arma el texto de una certificacion de inventario.
 *
 * Toda la redaccion vive aqui y no en la pantalla, porque el mismo texto lo
 * necesitan tres momentos distintos: la vista previa donde el usuario puede
 * corregirlo, el documento que se guarda al emitir y la reimpresion posterior.
 */
class CertificacionService
{
    /**
     * Nombre de la institucion tal como se escribe en el documento.
     */
    public const INSTITUCION = 'Dirección Departamental de Redes Integradas de Servicios de Salud de Totonicapán.';

    private const MESES = [
        1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE',
    ];

    /**
     * Linea de apertura. Lo unico que cambia entre los formatos es si dice
     * INFRASCRITA o INFRASCRITO y el cargo de quien certifica.
     */
    public function apertura(CertificacionFormato $formato): string
    {
        $tratamiento = $formato->genero === 'm' ? 'EL INFRASCRITO' : 'LA INFRASCRITA';

        return sprintf(
            '%s %s DE LA %s:',
            $tratamiento,
            trim($formato->cargo_apertura),
            $this->rotulo(rtrim($formato->institucion, '.')),
        );
    }

    /**
     * Parrafo del libro. Hay dos redacciones y la que corresponde depende de
     * donde esta el bien, no de quien firma: si el bien es de la Direccion se
     * certifica sobre el libro de hojas movibles, que no lleva folio; si es de
     * un puesto o un centro, sobre la copia de su libro auxiliar.
     */
    public function parrafoLibro(
        ?UnidadServicio $unidad,
        ?string $registro,
        ?string $folio,
    ): string {
        if (! $this->usaLibroAuxiliar($unidad)) {
            return $this->plantillas()['hojas_movibles'];
        }

        return strtr($this->plantillas()['libro_auxiliar'], [
            ':unidad' => $this->nombreDeUnidad($unidad),
            ':registro' => $this->rotulo(trim((string) $registro)) ?: '__________',
            ':folio' => trim((string) $folio) ?: '____',
        ]);
    }

    /**
     * Las dos redacciones, con sus huecos.
     *
     * Se exponen asi porque la pantalla rearma el parrafo mientras el usuario
     * escribe el registro y el folio, y la redaccion oficial debe estar escrita
     * en un solo lugar.
     *
     * @return array{libro_auxiliar: string, hojas_movibles: string}
     */
    public function plantillas(): array
    {
        return [
            'libro_auxiliar' => 'TENER A LA VISTA COPIA DEL LIBRO AUXILIAR DE INVENTARIO '
                .'DE BIENES NO FUNGIBLES, :unidad, SEGÚN REGISTRO :registro, AUTORIZADO '
                .'POR LA CONTRALORIA GENERAL DE CUENTAS, QUE EN FOLIO No. :folio, SE '
                .'ENCUENTRA REGISTRADO LO SIGUIENTE:',

            'hojas_movibles' => 'TENER A LA VISTA EL LIBRO DE INVENTARIOS HOJAS MOVIBLES '
                .'DE LA DIRECCION DE AREA DE SALUD DE TOTONICAPAN, AUTORIZADO POR LA '
                .'CONTRALORIA GENERAL DE CUENTAS, SE ENCUENTRA REGISTRADO LO SIGUIENTE:',
        ];
    }

    /**
     * Un bien de la Direccion de Area va en el libro de hojas movibles; todo lo
     * demas, en el libro auxiliar de su unidad.
     */
    public function usaLibroAuxiliar(?UnidadServicio $unidad): bool
    {
        return $unidad !== null && $unidad->tipo !== 'area';
    }

    /**
     * Cada punto numerado. La descripcion del bien ya trae la marca, el modelo
     * y el serie, asi que solo se le agrega el precio y el codigo.
     */
    public function parrafoBien(Bien $bien): string
    {
        return sprintf(
            '%s, Con un precio de Q. %s Código: %s.',
            rtrim(trim($bien->descripcion), " \t\n\r.,;"),
            number_format((float) $bien->precio_unitario, 2, '.', ','),
            trim((string) $bien->codigo),
        );
    }

    /**
     * Parrafo de cierre. El mes y el ano salen de la fecha en que se emite.
     */
    public function cierre(?CarbonInterface $fecha = null): string
    {
        $fecha ??= now();

        return sprintf(
            'Y PARA LOS USOS LEGALES QUE CORRESPONDAN, SE EXTIENDE SELLA Y FIRMA LA '
            .'PRESENTE CERTIFICACION EN UNA HOJA DE PAPEL BOND MEMBRETADO TAMAÑO CARTA '
            .'UTILIZADA UNICAMENTE EN SU LADO ANVERSO, TOTONICAPAN %s DE %s.',
            self::MESES[$fecha->month],
            $this->anioEnLetras($fecha->year),
        );
    }

    /**
     * El nombre de la unidad en mayusculas, con el tipo adelante: en el papel
     * se lee "PUESTO DE SALUD PUEBLO VIEJO" y no solo "PUEBLO VIEJO".
     */
    public function nombreDeUnidad(?UnidadServicio $unidad): string
    {
        if ($unidad === null) {
            return '__________';
        }

        $nombre = $this->rotulo(trim($unidad->nombre));

        $tipo = match ($unidad->tipo) {
            'puesto_salud' => 'PUESTO DE SALUD',
            'centro_salud' => 'CENTRO DE SALUD',
            'distrito' => 'DISTRITO DE SALUD',
            default => '',
        };

        // Si el nombre ya lo dice no se repite.
        if ($tipo === '' || str_contains($nombre, $tipo)) {
            return $nombre;
        }

        return $tipo.' '.$nombre;
    }

    /**
     * El ano escrito con letras, como lo pide el documento: DOS MIL VEINTISEIS.
     */
    public function anioEnLetras(int $anio): string
    {
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE',
            'OCHO', 'NUEVE'];

        $diez = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS',
            'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];

        $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA',
            'SETENTA', 'OCHENTA', 'NOVENTA'];

        $millar = intdiv($anio, 1000);
        $resto = $anio % 1000;

        $texto = $millar === 1 ? 'MIL' : $unidades[$millar].' MIL';

        if ($resto === 0) {
            return $texto;
        }

        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS',
            'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $centena = intdiv($resto, 100);
        $resto %= 100;

        if ($centena > 0) {
            $texto .= ' '.($centena === 1 && $resto === 0 ? 'CIEN' : $centenas[$centena]);
        }

        if ($resto === 0) {
            return $texto;
        }

        if ($resto < 10) {
            return $texto.' '.$unidades[$resto];
        }

        if ($resto < 20) {
            return $texto.' '.$diez[$resto - 10];
        }

        $decena = intdiv($resto, 10);
        $unidad = $resto % 10;

        if ($decena === 2) {
            // VEINTIUNO, VEINTISEIS: van pegadas.
            return $texto.' '.($unidad === 0 ? 'VEINTE' : 'VEINTI'.$unidades[$unidad]);
        }

        return $texto.' '.$decenas[$decena].($unidad === 0 ? '' : ' Y '.$unidades[$unidad]);
    }

    /**
     * Los nombres propios en el cuerpo del documento van en mayusculas y sin
     * tilde, como estan escritos en el formato de la institucion. La enie si se
     * conserva, porque cambia la palabra.
     */
    private function rotulo(string $texto): string
    {
        return strtr(mb_strtoupper($texto, 'UTF-8'), [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        ]);
    }
}
