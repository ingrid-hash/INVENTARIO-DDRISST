<?php

namespace App\Services;

use App\Models\Tarjeta;
use App\Models\TarjetaRenglon;
use Illuminate\Support\Collection;

class PaginadorTarjeta
{
    public function __construct(private readonly MedidorTarjeta $medidor) {}

    /**
     * @return array<int, array<string, mixed>> una entrada por hoja de papel
     */
    public function paginar(Tarjeta $tarjeta): array
    {
        $renglones = $tarjeta->renglones()->with('bien.renglon')->orderBy('orden')->get();

        // El calce y el estado de cada papel viven aparte de los renglones.
        $estadoDeHoja = $tarjeta->hojasPorNumero();

        // Los cortes de TOTAL se resuelven sobre el documento completo antes de
        // repartir: dependen del orden de las adiciones, no de la hoja.
        $cierres = $this->cierresDeAdicion($renglones);

        $asignacion = $this->repartirEnHojas($renglones, $cierres, $estadoDeHoja);

        // El TOTAL de la tarjeta lo cierra la ultima hoja. Si el papel traia ese
        // total escrito, es el que vale, igual que en los cortes de adicion.
        $ultimo = $renglones->last();
        $totalDelPapel = $ultimo?->total_corte_original !== null
            ? (float) $ultimo->total_corte_original
            : null;

        $hojas = [];
        $saldoAcumulado = 0.0;

        foreach ($asignacion as $numero => $delaHoja) {
            $estado = $estadoDeHoja[$numero] ?? null;
            $cara = Tarjeta::caraDeHoja($numero);
            $escala = $estado?->escala ?? 1.0;

            $vienen = $saldoAcumulado;
            $filas = [];
            $usadoMm = 0.0;

            foreach ($delaHoja as $renglon) {
                $saldoAcumulado += (float) $renglon->debe - (float) $renglon->haber;

                $alto = $this->medidor->altoDeRenglon($renglon, $escala);
                $usadoMm += $alto;

                $filas[] = ['tipo' => 'renglon', 'renglon' => $renglon, 'alto_mm' => $alto];

                if (! isset($cierres[$renglon->id])) {
                    continue;
                }

                $literal = $renglon->total_corte_original !== null
                    ? (float) $renglon->total_corte_original
                    : null;

                $altoCorte = $this->medidor->altoDeCorte($escala);
                $usadoMm += $altoCorte;

                $filas[] = [
                    'tipo' => 'total',
                    'monto' => $literal ?? $saldoAcumulado,
                    'calculado' => $saldoAcumulado,
                    'literal' => $literal,
                    'cuadra' => $literal === null || abs($literal - $saldoAcumulado) < 0.01,
                    'ya_impreso' => $renglon->yaSeImprimio(),
                    'alto_mm' => $altoCorte,

                    // El renglon que cierra la adicion: el TOTAL corre su misma
                    // suerte cuando se decide que se imprime y que no.
                    'renglon_id' => $renglon->id,
                ];
            }

            // Lo que gastan las lineas de apertura y cierre de la hoja.
            $usadoMm += $this->reservaDeHoja($numero, $escala);

            $banda = GeometriaTarjeta::bandaMm($cara);

            $hojas[] = [
                'numero' => $numero,
                'cara' => $cara,
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

                // La hoja se mide en milimetros de papel, no en cantidad de
                // bienes: lo que gasta un renglon depende de cuantas lineas
                // necesite su descripcion.
                'banda_mm' => $banda,
                'usado_mm' => round($usadoMm, 2),
                'libres_mm' => round($banda - $usadoMm, 2),
                'escala' => $escala,

                // Cuanto habria que apretar el texto para que la hoja cierre.
                // Null cuando ya cabe, o cuando ni apretando alcanza.
                'escala_sugerida' => $this->escalaParaQueQuepa($usadoMm, $banda, $escala),
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
    private function repartirEnHojas(Collection $renglones, array $cierres, array $estadoDeHoja): array
    {
        $hojas = [];

        // Lo que ya salio impreso no se mueve: su lugar en el papel es fijo y
        // hay una hoja firmada que lo respalda. Lo que todavia no se imprimio se
        // reparte midiendo, aunque traiga una hoja asignada de antes: esa
        // asignacion se hizo contando renglones y por eso no cuadraba.
        $fijos = $renglones->filter(
            fn (TarjetaRenglon $r) => $r->hoja_fisica !== null && $r->yaSeImprimio()
        );

        foreach ($fijos as $renglon) {
            $hojas[(int) $renglon->hoja_fisica][] = $renglon;
        }

        $pendientes = $renglones->reject(
            fn (TarjetaRenglon $r) => $r->hoja_fisica !== null && $r->yaSeImprimio()
        );

        if ($pendientes->isEmpty()) {
            return $this->ordenarYColeccionar($hojas);
        }

        // Milimetros que lleva gastados cada hoja.
        $usado = [];

        foreach ($hojas as $numero => $delaHoja) {
            $escala = $estadoDeHoja[$numero]?->escala ?? 1.0;
            $usado[$numero] = $this->espacio($delaHoja, $cierres, $escala)
                + $this->reservaDeHoja($numero, $escala);
        }

        $hojaActual = $hojas === [] ? 1 : max(array_keys($hojas));
        $escalaActual = $estadoDeHoja[$hojaActual]?->escala ?? 1.0;
        $usado[$hojaActual] ??= $this->reservaDeHoja($hojaActual, $escalaActual);

        if ($usado[$hojaActual] >= $this->bandaDe($hojaActual)) {
            $hojaActual++;
            $escalaActual = $estadoDeHoja[$hojaActual]?->escala ?? 1.0;
            $usado[$hojaActual] ??= $this->reservaDeHoja($hojaActual, $escalaActual);
        }

        foreach ($pendientes as $renglon) {
            // El renglon y el TOTAL que lo sigue no se separan: si no caben los
            // dos, pasan juntos a la hoja siguiente.
            $alto = $this->medidor->altoDeRenglon($renglon, $escalaActual);

            if (isset($cierres[$renglon->id])) {
                $alto += $this->medidor->altoDeCorte($escalaActual);
            }

            $tieneAlgo = ($hojas[$hojaActual] ?? []) !== [];

            if ($this->bandaDe($hojaActual) < $usado[$hojaActual] + $alto && $tieneAlgo) {
                $hojaActual++;
                $escalaActual = $estadoDeHoja[$hojaActual]?->escala ?? 1.0;
                $usado[$hojaActual] ??= $this->reservaDeHoja($hojaActual, $escalaActual);
            }

            $hojas[$hojaActual][] = $renglon;
            $usado[$hojaActual] += $alto;
        }

        return $this->ordenarYColeccionar($hojas);
    }

    /** Milimetros de papel disponibles en una hoja, segun sea frente o reverso. */
    private function bandaDe(int $numero): float
    {
        return GeometriaTarjeta::bandaMm(Tarjeta::caraDeHoja($numero));
    }

    /**
     * Lo que la hoja gasta antes de empezar con los bienes.
     *
     * De la segunda hoja en adelante arranca con el VIENEN, y toda hoja que se
     * cierre lleva su linea de VAN o de TOTAL al pie.
     */
    private function reservaDeHoja(int $numero, float $escala): float
    {
        $lineas = $numero > 1 ? 2 : 1;

        return $this->medidor->altoDeLineas(1, $escala) * $lineas;
    }

    /**
     * Milimetros de papel que gasta un grupo de renglones, contando los TOTAL.
     *
     * @param  array<int, TarjetaRenglon>  $delaHoja
     * @param  array<int, true>  $cierres
     */
    private function espacio(array $delaHoja, array $cierres, float $escala): float
    {
        $espacio = 0.0;

        foreach ($delaHoja as $renglon) {
            $espacio += $this->medidor->altoDeRenglon($renglon, $escala);

            if (isset($cierres[$renglon->id])) {
                $espacio += $this->medidor->altoDeCorte($escala);
            }
        }

        return $espacio;
    }

    /**
     * Cuanto habria que apretar el texto para que la hoja cierre.
     *
     * Devuelve null cuando ya cabe como esta, y tambien cuando ni apretando al
     * minimo alcanza: ahi lo que corresponde es pasar renglones a la hoja
     * siguiente, no seguir achicando hasta que no se lea.
     */
    private function escalaParaQueQuepa(float $usado, float $banda, float $escala): ?float
    {
        if ($usado <= $banda || $usado <= 0.0) {
            return null;
        }

        $necesaria = floor(($banda / $usado) * $escala * 100) / 100;

        return $necesaria >= GeometriaTarjeta::ESCALA_MINIMA ? $necesaria : null;
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
