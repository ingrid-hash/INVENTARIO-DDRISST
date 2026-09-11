<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de bienes no fungibles</title>

    <style>
        /* Oficio apaisado, la misma medida de la tarjeta. */
        @page { size: 330mm 215.9mm; margin: 12mm 10mm 14mm; }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000;
        }

        .barra {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #1c2e5f;
            color: #fff;
            font-size: 12px;
        }

        .barra button {
            font: inherit;
            font-weight: 600;
            padding: 7px 16px;
            border: 0;
            border-radius: 4px;
            background: #3fa8dc;
            color: #06243a;
            cursor: pointer;
        }

        .hoja {
            width: 310mm;
            margin: 12px auto;
            padding: 8mm;
            background: #fff;
            box-shadow: 0 2px 10px rgba(15, 23, 41, .18);
        }

        .titulo { text-align: center; margin-bottom: 5mm; }
        .titulo h1 { font-size: 12pt; margin: 0 0 1mm; text-transform: uppercase; }
        .titulo p { font-size: 8.5pt; margin: 0; }

        .filtros {
            margin: 0 0 4mm;
            padding: 2mm 3mm;
            border: 0.6pt solid #666;
            font-size: 7.5pt;
        }

        .filtros span + span::before { content: ' · '; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }

        th, td { border: 0.6pt solid #000; padding: 0.8mm 1.2mm; vertical-align: top; }

        thead th {
            text-align: center;
            font-size: 7pt;
            text-transform: uppercase;
            background: #e8e8e8;
        }

        td.num { text-align: right; }
        td.mid { text-align: center; }

        .grupo td {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 8.5pt;
            text-transform: uppercase;
        }

        .subtotal td {
            font-weight: bold;
            border-top: 1pt solid #000;
        }

        .general td {
            font-weight: bold;
            font-size: 9pt;
            border-top: 1pt solid #000;
            border-bottom: 2.5pt double #000;
        }

        .col-codigo { width: 26mm; }
        .col-desc { width: auto; }
        .col-cant { width: 12mm; }
        .col-unit { width: 24mm; }
        .col-total { width: 26mm; }
        .col-unidad { width: 46mm; }
        .col-cuenta { width: 18mm; }
        .col-mov { width: 20mm; }
        .col-adq { width: 20mm; }
        .col-fecha { width: 20mm; }

        .firmas {
            margin-top: 14mm;
            display: flex;
            justify-content: space-around;
            gap: 12mm;
            text-align: center;
            font-size: 8pt;
        }

        .firmas div { flex: 1; }

        .firmas .linea {
            border-top: 0.6pt solid #000;
            padding-top: 1mm;
            text-transform: uppercase;
        }

        .pie { margin-top: 4mm; font-size: 7pt; color: #333; text-align: right; }

        @media print {
            html, body { background: #fff; }
            .barra { display: none !important; }
            .hoja { width: auto; margin: 0; padding: 0; box-shadow: none; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="barra">
    <span>Reporte de bienes no fungibles · {{ $reporte['total_bienes'] }} bien(es)</span>
    <button type="button" onclick="window.print()">Imprimir o guardar como PDF</button>
</div>

<section class="hoja">
    <div class="titulo">
        <h1>Reporte de bienes no fungibles</h1>
        <p>Dirección Departamental de Redes Integradas de Servicios de Salud de Totonicapán</p>
        <p>Agrupado por: {{ $agrupacion }} · Emitido el {{ now()->format('d/m/Y') }}</p>
    </div>

    @if ($descripcion !== [])
        <div class="filtros">
            <strong>Filtros:</strong>
            @foreach ($descripcion as $linea)
                <span>{{ $linea }}</span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th class="col-codigo">Código</th>
                <th class="col-desc">Descripción</th>
                <th class="col-cant">Cant.</th>
                <th class="col-unit">Valor unitario</th>
                <th class="col-total">Valor total</th>
                <th class="col-unidad">Unidad de servicio</th>
                <th class="col-cuenta">Cuenta</th>
                <th class="col-mov">Movimiento</th>
                <th class="col-adq">Adquisición</th>
                <th class="col-fecha">Ingreso</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reporte['grupos'] as $grupo)
                <tr class="grupo">
                    <td colspan="10">{{ $grupo['rotulo'] }}</td>
                </tr>

                @foreach ($grupo['bienes'] as $bien)
                    <tr>
                        <td class="mid">{{ $bien->codigo }}</td>
                        <td>{{ $bien->descripcion }}</td>
                        <td class="mid">{{ $bien->cantidad }}</td>
                        <td class="num">{{ $formatearQ($bien->precio_unitario) }}</td>
                        <td class="num">{{ $formatearQ($bien->total) }}</td>
                        <td>{{ $bien->unidadServicio?->nombre }}</td>
                        <td class="mid">{{ $bien->renglon?->codigo }}</td>
                        <td class="mid">{{ $bien->tipo_movimiento === 'adicion' ? 'Adición' : 'Apertura' }}</td>
                        <td class="mid">
                            {{ match ($bien->forma_adquisicion) {
                                'compra' => 'Compra',
                                'donacion' => 'Donación',
                                default => '',
                            } }}
                        </td>
                        <td class="mid">{{ $bien->fecha_ingreso?->format('d/m/Y') ?? $bien->anio_ingreso }}</td>
                    </tr>
                @endforeach

                <tr class="subtotal">
                    <td></td>
                    <td style="text-align: right">Subtotal · {{ $grupo['rotulo'] }}</td>
                    <td class="mid">{{ $grupo['cantidad'] }}</td>
                    <td></td>
                    <td class="num">{{ $formatearQ($grupo['valor']) }}</td>
                    <td colspan="5"></td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center; padding: 6mm">
                        Ningún bien coincide con los filtros indicados.
                    </td>
                </tr>
            @endforelse

            @if ($reporte['grupos'] !== [])
                <tr class="general">
                    <td></td>
                    <td style="text-align: right">TOTAL GENERAL</td>
                    <td class="mid">{{ $reporte['total_cantidad'] }}</td>
                    <td></td>
                    <td class="num">{{ $formatearQ($reporte['total_valor']) }}</td>
                    <td colspan="5"></td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="firmas">
        <div>
            <div style="height: 10mm"></div>
            <div class="linea">Encargado de inventarios</div>
        </div>
        <div>
            <div style="height: 10mm"></div>
            <div class="linea">Director</div>
        </div>
    </div>

    <div class="pie">
        {{ $reporte['total_bienes'] }} bien(es) · Generado por el sistema de inventario
    </div>
</section>

</body>
</html>
