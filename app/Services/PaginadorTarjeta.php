<?php

namespace App\Services;

use App\Models\Tarjeta;
use App\Models\TarjetaRenglon;
use Illuminate\Support\Collection;


class PaginadorTarjeta
{
    /**
     * @return array<int, array<string, mixed>>  una entrada por hoja de papel
     */
    public function paginar(Tarjeta $tarjeta): array
    {
        $porHoja = max(1, $tarjeta->renglones_por_hoja);
        $renglones = $tarjeta->renglones()->with('bien.renglon')->orderBy('orden')->get();

        // El calce y el estado de cada papel viven aparte de los renglones.
        $estadoDeHoja = $tarjeta->hojasPorNumero();

        // Los cortes de TOTAL se resuelven sobre el documento completo antes de
        // repartir: dependen del orden de las adiciones, no de la hoja.
        $cierres = $this->cierresDeAdicion($renglones);

        $asignacion = $this->repartirEnHojas($renglones, $porHoja, $cierres);

        // El TOTAL de la tarjeta lo cierra la ultima hoja. Si el papel traia ese
        // total escrito, es el que vale, igual que en los cortes de adicion.
        $ultimo = $renglones->last();
        $totalDelPapel = $ultimo?->total_corte_original !== null
            ? (float) $ultimo->total_corte_original
            : null;

        $hojas = [];
        $saldoAcumulado = 0.0;

        foreach ($asignacion as $numero => $delaHoja) {
            $vienen = $saldoAcumulado;
            $filas = [];
            $ocupadas = 0;

            foreach ($delaHoja as $renglon) {
                $saldoAcumulado += (float) $renglon->debe - (float) $renglon->haber;

                $filas[] = ['tipo' => 'renglon', 'renglon' => $renglon];
                $ocupadas++;

                if (! isset($cierres[$renglon->id])) {
                    continue;
                }

                
                $literal = $renglon->total_corte_original !== null
                    ? (float) $renglon->total_corte_original
                    : null;

                $filas[] = [
                    'tipo' => 'total',
                    'monto' => $literal ?? $saldoAcumulado,
                    'calculado' => $saldoAcumulado,
                    'literal' => $literal,
                    'cuadra' => $literal === null || abs($literal - $saldoAcumulado) < 0.01,
                    'ya_impreso' => $renglon->yaSeImprimio(),

                    // El renglon que cierra la adicion: el TOTAL corre su misma
                    // suerte cuando se decide que se imprime y que no.
                    'renglon_id' => $renglon->id,
                ];
                $ocupadas++;
            }

            $estado = $estadoDeHoja[$numero] ?? null;

            $hojas[] = [
                'numero' => $numero,
                'cara' => Tarjeta::caraDeHoja($numero),
                'papel' => Tarjeta::papelDeHoja($numero),
                'renglones' => $delaHoja->values(),


                'filas' => $filas,


                'vienen' => $vienen,
                'van' => $saldoAcumulado,


                'total_papel' => $numero === array_key_last($asignacion) ? $totalDelPapel : null,

                'es_primera' => $numero === array_key_first($asignacion),
                'es_ultima' => $numero === array_key_last($asignacion),

                // Si la hoja ya termina con un TOTAL de adicion, el cierre de
                // hoja repetiria el mismo numero justo debajo.
                'termina_en_total' => ($filas !== [] && end($filas)['tipo'] === 'total'),
                'capacidad' => $porHoja,
                'libres' => max(0, $porHoja - $ocupadas),
                'tiene_pendientes' => $delaHoja->contains(fn (TarjetaRenglon $r) => ! $r->yaSeImprimio()),

                // Una hoja cerrada ya no admite bienes y lleva su linea de VAN.
                // Mientras siga abierta el corte no se imprime: el saldo del
                // papel cambiaria en cuanto entre el proximo bien.
                'cerrada' => $estado?->estaCerrada() ?? false,
                'impresa' => $estado?->seImprimio() ?? false,

                // Milimetros que hay que correr la impresion para que la tinta
                // caiga en los espacios libres de este papel.
                'desfase_x_mm' => $estado?->desfase_x_mm ?? 0.0,
                'desfase_y_mm' => $estado?->desfase_y_mm ?? 0.0,
            ];
        }

        return $hojas;
    }

   
    private function cierresDeAdicion(Collection $renglones): array
    {
        $lista = $renglones->values();
        $ultimo = $lista->count() - 1;
        $cierres = [];

        foreach ($lista as $indice => $renglon) {
            $esUltimo = $indice === $ultimo;

            // El ultimo renglon siempre cierra su adicion, este marcada como
            // impresa o no. Asi la tanda sale del papel ya con su total y el
            // encargado la marca despues, que es el orden en que se trabaja.
            if ($renglon->total_corte_original !== null) {
                $cierres[$renglon->id] = 'papel';

                continue;
            }

            if ($esUltimo) {
                $cierres[$renglon->id] = 'calculado';

                continue;
            }

            // Un renglon importado sin TOTAL en el papel se queda sin corte.
            if ($renglon->bien->importacion_id !== null) {
                continue;
            }

            // Lo capturado en el sistema se agrupa por el momento en que se
            // marco impreso, no por la fecha del bien: cada tanda que sale de
            // la impresora es una adicion distinta, aunque dos tandas del mismo
            // dia compartan fecha. Los renglones que aun no se han marcado
            // comparten el momento nulo y forman la adicion en preparacion.
            $momento = $renglon->impreso_at?->toDateTimeString();
            $siguiente = $lista[$indice + 1]->impreso_at?->toDateTimeString();

            if ($momento !== $siguiente) {
                $cierres[$renglon->id] = 'calculado';
            }
        }

        return $cierres;
    }

    /**
     * Agrupa los renglones por numero de hoja.
     *
     * @param  Collection<int, TarjetaRenglon>  $renglones
     * @param  array<int, true>  $cierres
     * @return array<int, Collection<int, TarjetaRenglon>>
     */
    private function repartirEnHojas(Collection $renglones, int $porHoja, array $cierres): array
    {
        $hojas = [];

        // Primero los que ya tienen hoja asignada: su lugar en el papel es fijo.
        foreach ($renglones->filter(fn (TarjetaRenglon $r) => $r->hoja_fisica !== null) as $renglon) {
            $hojas[(int) $renglon->hoja_fisica][] = $renglon;
        }

        $pendientes = $renglones->filter(fn (TarjetaRenglon $r) => $r->hoja_fisica === null);

        if ($pendientes->isEmpty()) {
            return $this->ordenarYColeccionar($hojas);
        }

       
        $usado = [];

        foreach ($hojas as $numero => $delaHoja) {
            $usado[$numero] = $this->espacio($delaHoja, $cierres);
        }

       
        $hojaActual = $hojas === [] ? 1 : max(array_keys($hojas));

        if (($usado[$hojaActual] ?? 0) >= $porHoja) {
            $hojaActual++;
        }

        foreach ($pendientes as $renglon) {
            // El renglon y el TOTAL que lo sigue no se separan: si no caben los
            // dos, pasan juntos a la hoja siguiente.
            $peso = isset($cierres[$renglon->id]) ? 2 : 1;

            if (($usado[$hojaActual] ?? 0) + $peso > $porHoja && ($usado[$hojaActual] ?? 0) > 0) {
                $hojaActual++;
            }

            $hojas[$hojaActual][] = $renglon;
            $usado[$hojaActual] = ($usado[$hojaActual] ?? 0) + $peso;
        }

        return $this->ordenarYColeccionar($hojas);
    }

    /**
     * Renglones de papel que gasta un grupo de lineas, contando los TOTAL.
     *
     * @param  array<int, TarjetaRenglon>  $delaHoja
     * @param  array<int, true>  $cierres
     */
    private function espacio(array $delaHoja, array $cierres): int
    {
        $espacio = 0;

        foreach ($delaHoja as $renglon) {
            $espacio += isset($cierres[$renglon->id]) ? 2 : 1;
        }

        return $espacio;
    }

    /**
     * @param  array<int, array<int, TarjetaRenglon>>  $hojas
     * @return array<int, Collection<int, TarjetaRenglon>>
     */
    private function ordenarYColeccionar(array $hojas): array
    {
        ksort($hojas);

        return array_map(
            fn (array $renglones) => collect($renglones)->sortBy('orden'),
            $hojas,
        );
    }
}
