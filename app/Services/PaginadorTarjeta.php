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

        $asignacion = $this->repartirEnHojas($renglones, $porHoja);

        $hojas = [];
        $saldoAcumulado = 0.0;

        foreach ($asignacion as $numero => $delaHoja) {
            $vienen = $saldoAcumulado;

            foreach ($delaHoja as $renglon) {
                $saldoAcumulado += (float) $renglon->debe - (float) $renglon->haber;
            }

            $hojas[] = [
                'numero' => $numero,
                'cara' => Tarjeta::caraDeHoja($numero),
                'papel' => Tarjeta::papelDeHoja($numero),
                'renglones' => $delaHoja->values(),
                // El corte contable: lo que viene de la hoja anterior y lo que
                // pasa a la siguiente.
                'vienen' => $vienen,
                'van' => $saldoAcumulado,
                'es_primera' => $numero === array_key_first($asignacion),
                'es_ultima' => $numero === array_key_last($asignacion),
                'capacidad' => $porHoja,
                'libres' => max(0, $porHoja - $delaHoja->count()),
                'tiene_pendientes' => $delaHoja->contains(fn (TarjetaRenglon $r) => ! $r->yaSeImprimio()),
            ];
        }

        return $hojas;
    }

    /**
     * Agrupa los renglones por numero de hoja.
     *
     * @param  Collection<int, TarjetaRenglon>  $renglones
     * @return array<int, Collection<int, TarjetaRenglon>>
     */
    private function repartirEnHojas(Collection $renglones, int $porHoja): array
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

        // La ultima hoja usada puede tener espacio libre: se aprovecha antes de
        // pasar a una hoja nueva, que es lo que se hace hoy a mano con el papel
        // ya impreso.
        $hojaActual = $hojas === [] ? 1 : max(array_keys($hojas));

        if (isset($hojas[$hojaActual]) && count($hojas[$hojaActual]) >= $porHoja) {
            $hojaActual++;
        }

        foreach ($pendientes as $renglon) {
            if (isset($hojas[$hojaActual]) && count($hojas[$hojaActual]) >= $porHoja) {
                $hojaActual++;
            }

            $hojas[$hojaActual][] = $renglon;
        }

        return $this->ordenarYColeccionar($hojas);
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
