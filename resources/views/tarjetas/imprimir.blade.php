<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarjeta de responsabilidad — {{ $empleado->nombre_completo }}</title>

    <style>
        /*
          Formato de la tarjeta de responsabilidad de la Contraloria General de
          Cuentas. Las medidas estan en milimetros porque lo que manda es el
          papel, no la pantalla.
        */
        @page {
            size: legal landscape;
            margin: 10mm 8mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000;
        }

        /* --- Barra de control: no se imprime --- */
        .barra {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #1c2e5f;
            color: #fff;
            font-size: 12px;
        }

        .barra strong { font-size: 13px; }

        .barra .datos { display: flex; gap: 18px; flex-wrap: wrap; align-items: center; }

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

        .barra button:hover { background: #62bce6; }

        .barra a { color: #bfe3f5; text-decoration: none; }
        .barra a:hover { text-decoration: underline; }

        .aviso {
            padding: 10px 16px;
            background: #fbf1e3;
            border-bottom: 1px solid #b45309;
            color: #7c3d06;
            font-size: 12px;
            line-height: 1.5;
        }

        /* --- La hoja --- */
        .hoja {
            width: 330mm;
            min-height: 200mm;
            margin: 12px auto;
            padding: 6mm;
            background: #fff;
            box-shadow: 0 2px 10px rgba(15, 23, 41, .18);
            display: flex;
            flex-direction: column;
        }

        .encabezado { margin-bottom: 3mm; }

        .encabezado table { width: 100%; border-collapse: collapse; }

        .encabezado td {
            padding: 0.6mm 0;
            font-size: 8.5pt;
            white-space: nowrap;
        }

        .encabezado .rotulo { font-weight: bold; }

        .detalle {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .detalle th,
        .detalle td {
            border: 0.6pt solid #000;
            padding: 0.8mm 1.2mm;
            vertical-align: top;
        }

        .detalle thead th {
            text-align: center;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            background: #fff;
        }

        .detalle td.num { text-align: right; }
        .detalle td.mid { text-align: center; }

        /* Anchos del formato nuevo de 2026. El orden de las columnas es el del
           papel oficial: FECHA, CODIGO, CANT., DESCRIPCION, DEBE, HABER, SALDO,
           FIRMA y OBSEVACIONES (asi, sin la R, como viene rotulado). */
        .detalle .col-fecha { width: 22mm; }
        .detalle .col-codigo { width: 26mm; }
        .detalle .col-cant { width: 12mm; }
        .detalle .col-desc { width: auto; }
        .detalle .col-debe { width: 26mm; }
        .detalle .col-haber { width: 26mm; }
        .detalle .col-saldo { width: 28mm; }
        .detalle .col-firma { width: 28mm; }
        .detalle .col-obs { width: 34mm; }

        /* La columna FIRMA va vacia a proposito: la firma el empleado a mano. */
        .detalle td.firma { border-bottom: 0.6pt solid #000; }

        .detalle td.obs {
            font-size: 6.5pt;
            line-height: 1.25;
        }

        .corte td {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8pt;
        }

        /* Los renglones que ya salieron impresos en este mismo papel ocupan su
           espacio pero no dejan tinta: asi se continua una hoja sin imprimir
           encima de lo anterior. */
        .ya-impreso td > * { visibility: hidden; }
        .ya-impreso td { border-color: transparent; }

        .relleno td {
            border-color: transparent;
            height: 4.6mm;
        }

        .firmas {
            margin-top: auto;
            padding-top: 10mm;
            display: flex;
            justify-content: space-around;
            gap: 10mm;
            text-align: center;
            font-size: 8pt;
        }

        .firmas div { flex: 1; }

        .firmas .linea {
            border-top: 0.6pt solid #000;
            padding-top: 1mm;
            font-weight: bold;
            text-transform: uppercase;
        }

        .pie {
            margin-top: 2mm;
            display: flex;
            justify-content: space-between;
            font-size: 7pt;
            color: #333;
        }

        @media print {
            html, body { background: #fff; }

            .barra, .aviso { display: none !important; }

            .hoja {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
                page-break-after: always;
            }

            .hoja:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>

<div class="barra">
    <div class="datos">
        <strong>{{ $empleado->nombre_completo }}</strong>
        <span>{{ $tarjeta->numero ?? 'Sin número' }} · versión {{ $tarjeta->version }}</span>
        <span>{{ count($hojas) }} {{ count($hojas) === 1 ? 'hoja' : 'hojas' }}</span>
        @if ($soloPendientes)
            <span>continuando hoja impresa</span>
        @endif
        <a href="{{ route('inventario.tarjetas.show', $tarjeta) }}">Volver a la tarjeta</a>
    </div>

    <button type="button" onclick="window.print()">Imprimir</button>
</div>

<div class="aviso">
    <strong>Imprima al 100 % y con los mismos márgenes de siempre.</strong>
    Si usa «Ajustar a la página», el navegador encoge la hoja cerca de un 6 % y el calce guardado deja de
    servir: la tinta caería encima de lo que ya está firmado.
</div>

@if ($soloPendientes)
    <div class="aviso">
        <strong>Modo continuar hoja.</strong>
        Los renglones que ya salieron impresos se dejan en blanco para no imprimir encima: coloque en la
        impresora la misma hoja de papel y solo se marcarán los renglones nuevos.
    </div>
@elseif ($tarjeta->renglonesPendientesDeImprimir() > 0)
    <div class="aviso">
        Esta tarjeta tiene {{ $tarjeta->renglonesPendientesDeImprimir() }} renglón(es) sin imprimir.
        Después de imprimir, registre la hoja en la pantalla de la tarjeta para que el sistema sepa qué
        salió en papel.
    </div>
@endif

@if ($descuadres !== [])
    <div class="aviso">
        <strong>Hay {{ count($descuadres) }} total(es) que no cuadran con la suma de sus renglones.</strong>
        Se imprime el total que trae el papel, porque es el que se firmó. La diferencia suele significar que
        a la tarjeta le falta un bien que sí estaba en la hoja original.
        <ul style="margin: 6px 0 0; padding-left: 18px">
            @foreach ($descuadres as $descuadre)
                <li>
                    Hoja {{ $descuadre['hoja'] }}: el papel dice Q {{ $formatearQ($descuadre['literal']) }}
                    y la suma da Q {{ $formatearQ($descuadre['calculado']) }}
                    (diferencia de Q {{ $formatearQ(abs($descuadre['diferencia'])) }}).
                </li>
            @endforeach
        </ul>
    </div>
@endif

@foreach ($hojas as $hoja)
    {{-- El calce corre toda la impresion de esta hoja los milimetros que hagan
         falta para que la tinta caiga en los espacios libres del papel que ya
         salio impreso. Cada papel se alimenta distinto, por eso es por hoja. --}}
    @php
        $calce = ($hoja['desfase_x_mm'] != 0 || $hoja['desfase_y_mm'] != 0)
            ? sprintf('transform: translate(%smm, %smm)', $hoja['desfase_x_mm'], $hoja['desfase_y_mm'])
            : null;
    @endphp

    <section class="hoja" @style([$calce => $calce !== null])>
        {{-- El encabezado se repite en cada hoja: el formato exige que cada
             pagina pueda leerse por si sola. El formato de 2026 pide DEPTO del
             empleado donde el anterior pedia CARGO; si el archivo viejo no lo
             traia, la casilla queda en blanco. --}}
        <div class="encabezado">
            <table>
                <tr>
                    <td colspan="2">
                        <span class="rotulo">UNIDAD DE SERVICIO:</span> {{ $unidad?->nombre }}
                    </td>
                    <td><span class="rotulo">MUNICIPIO:</span> {{ $unidad?->municipio }}</td>
                    <td style="text-align: right">
                        <span class="rotulo">DEPTO:</span> {{ $unidad?->departamento }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="rotulo">NOMBRE:</span> {{ $empleado->nombre_completo }}
                    </td>
                    <td colspan="2" style="text-align: right">
                        <span class="rotulo">DEPTO:</span> {{ $empleado->area_trabajo }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="detalle">
            <thead>
                <tr>
                    <th class="col-fecha">Fecha</th>
                    <th class="col-codigo">Código</th>
                    <th class="col-cant">Cant.</th>
                    <th class="col-desc">Descripción</th>
                    <th class="col-debe">Debe</th>
                    <th class="col-haber">Haber</th>
                    <th class="col-saldo">Saldo</th>
                    <th class="col-firma">Firma</th>
                    <th class="col-obs">Obsevaciones</th>
                </tr>
            </thead>
            <tbody>
                {{-- Apertura de hoja: de donde viene el saldo --}}
                @if ($hoja['es_primera'])
                    @if ($tarjeta->version > 1 || $hoja['vienen'] > 0)
                        <tr class="corte">
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>VIENE DE LA TARJETA DE RESPONSABILIDAD {{ $tarjeta->numero }}</td>
                            <td></td>
                            <td></td>
                            <td class="num">{{ $hoja['vienen'] > 0 ? $formatearQ($hoja['vienen']) : '' }}</td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endif
                @else
                    <tr class="corte">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>VIENEN</td>
                        <td></td>
                        <td></td>
                        <td class="num">{{ $formatearQ($hoja['vienen']) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                @endif

                @php $cuentaAnterior = $hoja['es_primera'] ? null : $cuentaPreviaPorHoja[$hoja['numero']] ?? null; @endphp

                @foreach ($hoja['filas'] as $fila)
                    @if ($fila['tipo'] === 'total')
                        {{-- Cierre de una adicion: el saldo acumulado hasta aqui.
                             Si la tarjeta venia de Excel con el total escrito, se
                             reimprime ese, que es el que se firmo. --}}
                        <tr @class(['corte', 'ya-impreso' => $soloPendientes && $fila['ya_impreso']])>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right"><span>TOTAL</span></td>
                            <td class="num"><span>{{ $formatearQ($fila['monto']) }}</span></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @else
                        @php
                            $renglon = $fila['renglon'];
                            $lineasCuenta = $renglon->bien->lineasColumnaCuenta();
                            // La cuenta se escribe solo cuando cambia respecto al
                            // renglon anterior, igual que en el documento a mano.
                            $mostrarCuenta = $lineasCuenta !== [] && $lineasCuenta !== $cuentaAnterior;
                            $cuentaAnterior = $lineasCuenta !== [] ? $lineasCuenta : $cuentaAnterior;

                            $oculto = $soloPendientes && $renglon->yaSeImprimio();

                            // INTERINO: el formato de 2026 no tiene columna
                            // CUENTA, asi que el renglon presupuestario y sus
                            // anexos (forma de adquisicion, programa, oficio)
                            // se escriben en OBSEVACIONES, que es donde los
                            // archivos del MSPAS ya ponen anotaciones de ese
                            // tipo. Los datos siguen guardados aparte en la base;
                            // cuando la DDRISST confirme donde va la cuenta,
                            // cambia solo este bloque.
                            $anotaciones = $mostrarCuenta ? $lineasCuenta : [];

                            if ($renglon->observaciones) {
                                $anotaciones[] = $renglon->observaciones;
                            }
                        @endphp

                        <tr @class(['ya-impreso' => $oculto])>
                            <td class="mid"><span>{{ $renglon->bien->fechaColumnaTarjeta() }}</span></td>
                            <td class="mid"><span>{{ $renglon->bien->codigo }}</span></td>
                            <td class="mid"><span>{{ $renglon->bien->cantidad }}</span></td>
                            <td><span>{{ $renglon->bien->descripcion }}</span></td>
                            <td class="num"><span>{{ $renglon->debe > 0 ? $formatearQ($renglon->debe) : '' }}</span></td>
                            <td class="num"><span>{{ $renglon->haber > 0 ? $formatearQ($renglon->haber) : '' }}</span></td>
                            <td class="num"><span>{{ $formatearQ($renglon->saldo) }}</span></td>
                            <td class="firma"></td>
                            <td class="obs">
                                <span>
                                    @foreach ($anotaciones as $linea)
                                        {{ $linea }}@if (! $loop->last)<br>@endif
                                    @endforeach
                                </span>
                            </td>
                        </tr>
                    @endif
                @endforeach

                {{-- Renglones en blanco para que la hoja conserve su altura --}}
                @for ($i = 0; $i < $hoja['libres']; $i++)
                    <tr class="relleno">
                        <td></td><td></td><td></td><td></td><td></td>
                        <td></td><td></td><td></td><td></td>
                    </tr>
                @endfor

                {{-- Cierre de hoja. El VAN se escribe cuando la hoja se cierra,
                     no antes: mientras quede espacio libre siguen entrando
                     bienes, y un VAN impreso de mas dejaria en el papel un saldo
                     que deja de cuadrar con el proximo renglon. --}}
                @if ($hoja['cerrada'])
                    <tr class="corte">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td style="text-align: right">{{ $hoja['es_ultima'] ? 'TOTAL' : 'VAN' }}</td>
                        <td></td>
                        <td></td>
                        <td class="num">{{ $formatearQ($hoja['total_papel'] ?? $hoja['van']) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{-- Rotulos de firma del formato de 2026. --}}
        <div class="firmas">
            <div>
                <div style="height: 8mm"></div>
                <div class="linea">Responsable encargado de inventarios</div>
            </div>
            <div>
                <div style="height: 8mm"></div>
                <div class="linea">Encargada de inventarios</div>
            </div>
            <div>
                <div style="height: 8mm"></div>
                <div class="linea">Director</div>
            </div>
        </div>

        <div class="pie">
            <span>Hoja {{ $hoja['numero'] }} de {{ $ultimaHoja }} · {{ ucfirst($hoja['cara']) }} del papel {{ $hoja['papel'] }}</span>
            <span>
                @if ($hoja['desfase_x_mm'] != 0 || $hoja['desfase_y_mm'] != 0)
                    calce {{ $hoja['desfase_x_mm'] }} / {{ $hoja['desfase_y_mm'] }} mm ·
                @endif
                {{ $tarjeta->numero }} · versión {{ $tarjeta->version }}
            </span>
        </div>
    </section>
@endforeach

</body>
</html>
