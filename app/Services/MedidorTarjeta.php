<?php

namespace App\Services;

use App\Models\TarjetaRenglon;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;

/**
 * Cuanto papel ocupa cada renglon de la tarjeta.
 *
 * El paginador repartia los bienes contandolos de a uno, y por eso una
 * descripcion de seis lineas pesaba lo mismo que una de una: la hoja se
 * desbordaba sin que el sistema se enterara. Aqui se mide de verdad, con las
 * mismas medidas de letra que usa la impresion, para que lo que el sistema
 * calcula sea lo que termina saliendo en el papel.
 */
class MedidorTarjeta
{
    private readonly FontMetrics $metricas;

    public function __construct()
    {
        $this->metricas = (new Dompdf)->getFontMetrics();
    }

    /**
     * Lineas que ocupa un texto en una columna, partiendo por palabras como lo
     * hace la impresion.
     */
    public function lineas(?string $texto, float $anchoMm, float $escala = 1.0): int
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return 1;
        }

        $fuente = $this->metricas->getFont(GeometriaTarjeta::FUENTE, 'normal');
        $tamano = GeometriaTarjeta::TAMANO_PT;
        $disponible = $anchoMm / 25.4 * 72;

        $lineas = 1;
        $actual = '';

        foreach (preg_split('/\s+/u', $texto) ?: [] as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual.' '.$palabra;

            if ($this->metricas->getTextWidth($prueba, $fuente, $tamano) <= $disponible) {
                $actual = $prueba;

                continue;
            }

            $lineas++;
            $actual = $palabra;
        }

        return $lineas;
    }

    /**
     * Alto de un renglon de la tarjeta.
     *
     * Manda la columna que mas lineas necesite: la descripcion casi siempre,
     * pero las observaciones de una adicion pueden llevar tres renglones de
     * anotacion y entonces mandan ellas.
     */
    public function altoDeRenglon(TarjetaRenglon $renglon, float $escala = 1.0): float
    {
        // Lo que ya salio impreso conserva el alto que tuvo en el papel: si una
        // descripcion se corrige despues, las lineas de esa hoja no se mueven y
        // la continuacion sigue cayendo donde debe.
        if ($renglon->alto_mm !== null) {
            return (float) $renglon->alto_mm;
        }

        // Manda la columna que mas lineas necesite. Casi siempre la
        // descripcion, pero un codigo provisional no cabe de un tiron y las
        // anotaciones de una adicion pueden llevar tres renglones.
        $lineas = max(
            $this->lineas($renglon->bien?->descripcion, GeometriaTarjeta::anchoUtil('desc')),
            $this->lineas($renglon->bien?->codigo, GeometriaTarjeta::anchoUtil('codigo')),
            count($this->anotaciones($renglon)),
        );

        return $this->altoDeLineas($lineas, $escala);
    }

    /** Alto en milimetros de un bloque de tantas lineas. */
    public function altoDeLineas(int $lineas, float $escala = 1.0): float
    {
        $lineas = max(1, $lineas);

        $alto = GeometriaTarjeta::ALTO_LINEA_MM
            + ($lineas - 1) * GeometriaTarjeta::LINEA_EXTRA_MM;

        return round($alto * $escala, 3);
    }

    /** Alto de una fila de TOTAL o de VAN, que siempre es de una linea. */
    public function altoDeCorte(float $escala = 1.0): float
    {
        return $this->altoDeLineas(1, $escala);
    }

    /**
     * Las lineas que van en OBSEVACIONES: la cuenta presupuestaria y lo que el
     * renglon traiga anotado.
     *
     * @return array<int, string>
     */
    private function anotaciones(TarjetaRenglon $renglon): array
    {
        $lineas = $renglon->bien?->lineasColumnaCuenta() ?? [];

        if ($renglon->observaciones) {
            $lineas[] = $renglon->observaciones;
        }

        return $lineas;
    }
}
