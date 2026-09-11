<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\UnidadServicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Arma el reporte general del inventario a partir de los filtros que elija
 * quien consulta.
 *
 * Es un solo reporte con filtros y no varios reportes distintos, porque lo que
 * cambia entre "el inventario de una unidad", "lo adquirido por donacion" y
 * "los bienes de una cuenta" es el recorte, no la informacion. La agrupacion
 * con subtotales cubre lo que en papel serian listados separados.
 */
class ReporteInventario
{
    /** Por que se puede agrupar, y de donde sale el rotulo de cada grupo. */
    public const AGRUPACIONES = [
        'unidad' => 'Unidad de servicio',
        'cuenta' => 'Cuenta presupuestaria',
        'forma_adquisicion' => 'Forma de adquisición',
        'tipo_movimiento' => 'Tipo de movimiento',
        'programa' => 'Programa donante',
        'ninguna' => 'Sin agrupar',
    ];

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     grupos: array<int, array<string, mixed>>,
     *     total_bienes: int,
     *     total_cantidad: int,
     *     total_valor: float,
     *     agrupar_por: string
     * }
     */
    public function generar(array $filtros): array
    {
        $agrupar = $filtros['agrupar_por'] ?? 'unidad';

        if (! array_key_exists($agrupar, self::AGRUPACIONES)) {
            $agrupar = 'unidad';
        }

        $bienes = $this->consulta($filtros)->get();

        $grupos = $bienes
            ->groupBy(fn (Bien $bien) => $this->clave($bien, $agrupar))
            ->map(fn (Collection $delGrupo, string $rotulo) => [
                'rotulo' => $rotulo,
                'bienes' => $delGrupo->values(),
                'cantidad' => (int) $delGrupo->sum('cantidad'),
                'valor' => round((float) $delGrupo->sum('total'), 2),
            ])
            ->sortKeys()
            ->values()
            ->all();

        return [
            'grupos' => $grupos,
            'total_bienes' => $bienes->count(),
            'total_cantidad' => (int) $bienes->sum('cantidad'),
            'total_valor' => round((float) $bienes->sum('total'), 2),
            'agrupar_por' => $agrupar,
        ];
    }

    /**
     * La consulta con los filtros aplicados, sin agrupar.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Bien>
     */
    public function consulta(array $filtros): Builder
    {
        $unidad = ! empty($filtros['unidad_servicio_id'])
            ? UnidadServicio::find($filtros['unidad_servicio_id'])
            : null;

        return Bien::query()
            ->with(['unidadServicio:id,codigo,nombre', 'renglon:id,codigo,nombre'])

            // La unidad arrastra a sus dependientes: pedir el distrito trae los
            // puestos y centros que cuelgan de el.
            ->when($unidad !== null, fn (Builder $q) => $q->deUnidadConDescendientes($unidad))

            ->when(! empty($filtros['renglon_id']),
                fn (Builder $q) => $q->where('renglon_id', $filtros['renglon_id']))

            ->when(! empty($filtros['tipo_movimiento']),
                fn (Builder $q) => $q->where('tipo_movimiento', $filtros['tipo_movimiento']))

            ->when(! empty($filtros['forma_adquisicion']),
                fn (Builder $q) => $q->where('forma_adquisicion', $filtros['forma_adquisicion']))

            ->when(! empty($filtros['programa']),
                fn (Builder $q) => $q->where('programa', 'ilike', '%'.$filtros['programa'].'%'))

            // Sirve para buscar por marca: la marca no es un campo aparte
            // porque no todas las descripciones la traen, asi que se busca
            // dentro del texto del bien.
            ->when(! empty($filtros['buscar']),
                fn (Builder $q) => $q->buscar((string) $filtros['buscar']))

            ->when(! empty($filtros['desde']),
                fn (Builder $q) => $q->whereDate('fecha_ingreso', '>=', $filtros['desde']))

            ->when(! empty($filtros['hasta']),
                fn (Builder $q) => $q->whereDate('fecha_ingreso', '<=', $filtros['hasta']))

            ->when(! empty($filtros['estado']),
                fn (Builder $q) => $q->where('estado', $filtros['estado']))

            ->orderBy('unidad_servicio_id')
            ->orderBy('renglon_id')
            ->orderBy('codigo');
    }

    /** El rotulo del grupo al que pertenece un bien. */
    private function clave(Bien $bien, string $agrupar): string
    {
        return match ($agrupar) {
            'unidad' => $bien->unidadServicio?->nombre ?? 'Sin unidad asignada',
            'cuenta' => $bien->renglon
                ? $bien->renglon->codigo.'  '.$bien->renglon->nombre
                : 'Sin cuenta asignada',
            'forma_adquisicion' => match ($bien->forma_adquisicion) {
                'compra' => 'Compra',
                'donacion' => 'Donación',
                default => 'Sin indicar',
            },
            'tipo_movimiento' => $bien->tipo_movimiento === 'adicion'
                ? 'Adición'
                : 'Apertura de inventario',
            'programa' => $bien->programa ?: 'Sin programa',
            default => 'Inventario',
        };
    }

    /**
     * Descripcion en palabras de los filtros aplicados, para que el reporte
     * impreso diga de que recorte se trata.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<int, string>
     */
    public function descripcionDeFiltros(array $filtros): array
    {
        $texto = [];

        if (! empty($filtros['unidad_servicio_id'])) {
            $unidad = UnidadServicio::find($filtros['unidad_servicio_id']);
            if ($unidad) {
                $texto[] = 'Unidad: '.$unidad->nombre;
            }
        }

        if (! empty($filtros['renglon_id'])) {
            $renglon = \App\Models\Renglon::find($filtros['renglon_id']);
            if ($renglon) {
                $texto[] = 'Cuenta: '.$renglon->codigo.' '.$renglon->nombre;
            }
        }

        foreach ([
            'tipo_movimiento' => 'Tipo de movimiento',
            'forma_adquisicion' => 'Forma de adquisición',
            'programa' => 'Programa',
            'estado' => 'Estado',
            'buscar' => 'Contiene',
        ] as $campo => $rotulo) {
            if (! empty($filtros[$campo])) {
                $texto[] = $rotulo.': '.$filtros[$campo];
            }
        }

        if (! empty($filtros['desde']) || ! empty($filtros['hasta'])) {
            $texto[] = sprintf(
                'Ingreso %s',
                match (true) {
                    ! empty($filtros['desde']) && ! empty($filtros['hasta']) => sprintf(
                        'del %s al %s',
                        $this->fecha($filtros['desde']),
                        $this->fecha($filtros['hasta']),
                    ),
                    ! empty($filtros['desde']) => 'desde el '.$this->fecha($filtros['desde']),
                    default => 'hasta el '.$this->fecha($filtros['hasta']),
                },
            );
        }

        return $texto;
    }

    private function fecha(string $valor): string
    {
        try {
            return \Carbon\CarbonImmutable::parse($valor)->format('d/m/Y');
        } catch (\Throwable) {
            return $valor;
        }
    }
}
