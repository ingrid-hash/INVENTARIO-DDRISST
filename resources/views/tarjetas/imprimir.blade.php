@php
    use App\Services\GeometriaTarjeta as G;

    /*
      Tarjeta de responsabilidad, formato de la Contraloria General de Cuentas.

      La misma hoja sirve para la pantalla y para el PDF: el contenido es el
      mismo y las medidas tambien, asi lo que se ve es lo que sale impreso. Solo
      cambian tres cosas que el generador de PDF no entiende igual que el
      navegador, y van marcadas una por una mas abajo.

      Todas las medidas salen de App\Services\GeometriaTarjeta, que las toma del
      formato oficial de la institucion. Aqui no se inventa ninguna.
    */
    $pdf = $pdf ?? false;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarjeta de responsabilidad — {{ $empleado->nombre_completo }}</title>

    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            font-size: {{ G::TAMANO_PT }}pt;
            color: #000;
        }

        /* --- La hoja de papel --- */

        .hoja {
            position: relative;
            width: {{ G::PAPEL_ANCHO_MM }}mm;
            /* Un pelo menos que el papel: asi el generador no se pasa a una
               pagina en blanco por el redondeo. */
            height: {{ G::PAPEL_ALTO_MM - 2 }}mm;
            padding: 0 {{ G::MARGEN_DERECHO_MM }}mm {{ G::MARGEN_INFERIOR_MM }}mm {{ G::MARGEN_IZQUIERDO_MM }}mm;
            overflow: hidden;

            /* Los saltos de linea del propio HTML cuentan como texto y dejan
               renglones vacios entre bloque y bloque. Se apagan aqui y cada
               parte vuelve a encender su letra. */
            font-size: 0;
            line-height: 0;
        }

        /* El frente del papel trae impreso el escudo de la Contraloria y hay
           que dejarlo libre. El reverso no lo lleva. */
        .hoja.frente { padding-top: {{ G::MARGEN_SUPERIOR_FRENTE_MM }}mm; }
        .hoja.reverso { padding-top: {{ G::MARGEN_SUPERIOR_REVERSO_MM }}mm; }

        /* --- Encabezado: solo en el frente, el formato no lo repite al dorso --- */

        /* Dos renglones que entre los dos miden lo que el formato le da al
           encabezado. Van en tabla de una fila cada uno y sin alto propio: el
           generador de PDF suma el alto declarado al del texto que lleva
           dentro, asi que la medida la pone el interlineado. */
        .encabezado table { width: {{ G::anchoTabla() }}mm; border-collapse: collapse; }

        .encabezado td {
            padding: 0;
            white-space: nowrap;
            font-size: {{ G::TAMANO_PT }}pt;
            line-height: {{ round((G::ALTO_ENCABEZADO_MM - G::DESFASE_ENCABEZADO_MM) / 2, 3) }}mm;
        }
        .encabezado .rotulo { font-weight: bold; }

        /* --- El detalle --- */

        .detalle {
            width: {{ G::anchoTabla() }}mm;
            border-collapse: collapse;
        }

        .detalle th,
        .detalle td {
            font-size: {{ G::TAMANO_PT }}pt;
            border: 0.6pt solid #000;
            padding: 0 {{ G::RELLENO_CELDA_MM }}mm;
            vertical-align: top;
        }

        .detalle thead th {
            height: {{ G::ALTO_ROTULOS_MM }}mm;
            line-height: {{ G::ALTO_ROTULOS_MM }}mm;
            padding-top: 0;
            padding-bottom: 0;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }

@foreach (G::COLUMNAS as $columna => $milimetros)
        .detalle .col-{{ $columna }} { width: {{ round($milimetros - 2 * G::RELLENO_CELDA_MM, 2) }}mm; }
@endforeach

        /* El alto de cada renglon sale del interlineado mas el relleno de la
           celda. Son los mismos numeros con que el sistema mide la hoja, asi lo
           que se ve, lo que se calcula y lo que se imprime coinciden. Cada hoja
           lleva su regla porque cada una puede ir comprimida distinto. */
@foreach ($hojas as $hojaEstilo)
@php
    $e = $hojaEstilo['escala'];
    $linea = round(G::LINEA_EXTRA_MM * $e, 3);
    $relleno = round((G::ALTO_LINEA_MM - G::LINEA_EXTRA_MM - G::BORDE_MM) / 2 * $e, 3);
@endphp
        .hoja-{{ $hojaEstilo['numero'] }} .detalle td {
            line-height: {{ $linea }}mm;
            padding: {{ $relleno }}mm {{ G::RELLENO_CELDA_MM }}mm;
        }
@endforeach

        .detalle td.num { text-align: right; }
        .detalle td.mid { text-align: center; }

        /* La columna FIRMA va vacia a proposito: la firma el empleado a mano. */
        .detalle td.firma { border-bottom: 0.6pt solid #000; }

        .corte td { font-weight: bold; text-transform: uppercase; }

        /* Doble raya bajo el total de la adicion: es como cierra una suma en el
           documento contable. */
        .detalle td.doble { border-bottom: 2.5pt double #000; }

        /* Los renglones que ya salieron impresos en este mismo papel ocupan su
           espacio pero no dejan tinta: asi se continua una hoja sin imprimir
           encima de lo anterior. */
        .ya-impreso td > * { visibility: hidden; }
        .ya-impreso td { border-color: transparent; }

        /* --- Firmas, siempre al pie de la hoja --- */

        .firmas { width: 100%; }

        .firmas .columna {
            text-align: center;
            font-size: {{ G::TAMANO_PT }}pt;
            line-height: {{ G::ALTO_FIRMAS_MM }}mm;
        }

        .firmas .linea {
            border-top: 0.6pt solid #000;
            padding-top: 0.8mm;
            font-weight: bold;
            text-transform: uppercase;
        }

        .pie { width: 100%; color: #333; }
        .pie span { font-size: 5.5pt; line-height: 3.5mm; }

@if (! $pdf)
        /* --- Solo en pantalla --- */

        html, body { background: #f1f5f9; }

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
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .barra strong { font-size: 13px; }
        .barra .datos { display: flex; gap: 18px; flex-wrap: wrap; align-items: center; }

        .barra .boton {
            font: inherit;
            font-weight: 600;
            padding: 7px 16px;
            border: 0;
            border-radius: 4px;
            background: #3fa8dc;
            color: #06243a;
            cursor: pointer;
            text-decoration: none;
        }

        .barra .boton:hover { background: #62bce6; }
        .barra a { color: #bfe3f5; text-decoration: none; }

        /* Apretar el texto de una hoja desde aqui mismo: es donde se ve si le
           falta papel y si queda sitio para firmar. */
        .ajuste {
            display: flex;
            gap: 6px;
            align-items: center;
            font-size: 12px;
        }

        .ajuste select {
            font: inherit;
            padding: 5px 6px;
            border: 0;
            border-radius: 4px;
            background: #0f1d3d;
            color: #e8f3fb;
        }

        .ajuste button {
            font: inherit;
            padding: 6px 12px;
            border: 1px solid #5b7fb9;
            border-radius: 4px;
            background: transparent;
            color: #cfe6f5;
            cursor: pointer;
        }

        .ajuste .falta { color: #ffd38a; }

        /* Los avisos son de consulta y estorban la vista del papel, asi que van
           plegados. Se usa <details> nativo: no hace falta JavaScript. */
        .avisos { background: #fdf8f0; border-bottom: 1px solid #b45309; font-family: Arial, sans-serif; }

        .avisos > summary {
            padding: 8px 16px;
            cursor: pointer;
            color: #7c3d06;
            font-size: 12px;
            font-weight: 600;
            list-style: none;
        }

        .avisos > summary::-webkit-details-marker { display: none; }
        .avisos > summary::before { content: '\25B8  '; }
        .avisos[open] > summary::before { content: '\25BE  '; }

        .aviso {
            padding: 10px 16px;
            background: #fbf1e3;
            border-bottom: 1px solid #b45309;
            color: #7c3d06;
            font-size: 12px;
            line-height: 1.5;
            font-family: Arial, sans-serif;
        }

        .avisos .aviso:last-child { border-bottom: 0; }

        /* La hoja se dibuja del tamano del papel y con su borde: lo que se ve
           aqui es exactamente lo que lleva el PDF, ni un milimetro mas. */
        .hoja {
            margin: 12px auto;
            background: #fff;
            box-shadow: 0 2px 10px rgba(15, 23, 41, .18);
            display: flex;
            flex-direction: column;
        }

        /* Lo que sobra de la hoja queda arriba de las firmas: su lugar en el
           papel es el pie y de ahi no se mueven. */
        .firmas { display: flex; margin-top: auto; }
        .firmas .columna { flex: 1; padding: 0 5mm; }
        .pie { display: flex; justify-content: space-between; }

        @media print {
            html, body { background: #fff; }
            .barra, .avisos { display: none !important; }
            .hoja { margin: 0; box-shadow: none; }
            .hoja + .hoja { page-break-before: always; }
        }
@else
        /* --- Solo en el PDF ---
           El generador reparte las columnas por igual si la tabla es de ancho
           fijo, no descuenta el relleno de la celda del ancho declarado y no
           entiende las cajas flexibles. Por eso aqui las firmas y el pie van en
           tabla y la tabla del detalle se deja a su medida. */

        @page { size: {{ G::PAPEL_ANCHO_MM }}mm {{ G::PAPEL_ALTO_MM }}mm; margin: 0; }

        .detalle { table-layout: auto; }

        .hoja + .hoja { page-break-before: always; }

        /* La hoja no lleva alto propio en el PDF: la pagina ya mide lo que debe
           y un bloque del alto exacto del papel empuja una pagina en blanco. */
        .hoja { height: auto; overflow: visible; }

        .firmas, .pie {
            position: absolute;
            left: {{ G::MARGEN_IZQUIERDO_MM }}mm;
            width: {{ G::anchoTabla() }}mm;
            display: table;
            table-layout: fixed;
        }

        /* Medido desde arriba: el generador de PDF no coloca por el borde
           inferior. Son los milimetros que el formato le deja al pie. */
        .firmas { top: {{ round(G::PAPEL_ALTO_MM - G::MARGEN_INFERIOR_MM - G::ALTO_PIE_MM - G::ALTO_FIRMAS_MM, 2) }}mm; }
        .pie { top: {{ round(G::PAPEL_ALTO_MM - G::MARGEN_INFERIOR_MM - G::ALTO_PIE_MM, 2) }}mm; }
        .firmas .columna { display: table-cell; padding: 0 5mm; }
        .pie .izquierda { display: table-cell; text-align: left; }
        .pie .derecha { display: table-cell; text-align: right; }
@endif
    </style>
</head>
<body @class(['continuacion' => $soloPendientes])>

@if (! $pdf)
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

    @php
        $consulta = request()->getQueryString();
        $hojaAjustable = collect($hojas)->firstWhere('libres_mm', '<', 0) ?? ($hojas[0] ?? null);
    @endphp

    <div class="datos">
        @if ($hojaAjustable !== null && ! $soloPendientes)
            <form class="ajuste" method="POST"
                  action="{{ route('inventario.tarjetas.calce.guardar', $tarjeta) }}">
                @csrf

                @if ($hojaAjustable['libres_mm'] < 0)
                    <span class="falta">
                        A la hoja {{ $hojaAjustable['numero'] }} le faltan
                        {{ number_format(abs($hojaAjustable['libres_mm']), 1) }} mm
                    </span>
                @endif

                <label>
                    Hoja
                    <select name="hoja">
                        @foreach ($hojas as $h)
                            <option value="{{ $h['numero'] }}"
                                @selected($h['numero'] === $hojaAjustable['numero'])>
                                {{ $h['numero'] }} ({{ round($h['escala'] * 100) }} %)
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Texto
                    <select name="escala">
                        @foreach (range(100, (int) round(\App\Services\GeometriaTarjeta::ESCALA_MINIMA * 100), -2) as $porciento)
                            <option value="{{ $porciento / 100 }}"
                                @selected(round($hojaAjustable['escala'] * 100) == $porciento)>
                                {{ $porciento }} %
                            </option>
                        @endforeach
                    </select>
                </label>

                <button type="submit">Aplicar</button>
            </form>
        @endif

        <a class="boton" href="{{ route('inventario.tarjetas.pdf', $tarjeta) }}{{ $consulta ? '?'.$consulta : '' }}">
            Descargar PDF
        </a>
    </div>
</div>

@php
    $apretadas = array_filter($hojas, fn (array $h) => $h['libres_mm'] < 0);

    $cuantosAvisos = 1
        + (int) ($soloPendientes || $ensayo || $tarjeta->renglonesPendientesDeImprimir() > 0)
        + (int) ($descuadres !== [])
        + (int) ($apretadas !== []);
@endphp

<details class="avisos">
    <summary>{{ $cuantosAvisos }} {{ $cuantosAvisos === 1 ? 'aviso' : 'avisos' }} sobre esta impresión</summary>

<div class="aviso">
    <strong>Imprima al 100 % y con los mismos márgenes de siempre.</strong>
    No use «Ajustar a la página»: la tinta caería encima de lo que ya está firmado.
</div>

@if ($apretadas !== [])
    <div class="aviso">
        <strong>Hay {{ count($apretadas) }} hoja(s) a las que no les alcanza el papel.</strong>
        <ul style="margin: 6px 0 0; padding-left: 18px">
            @foreach ($apretadas as $hojaApretada)
                <li>
                    Hoja {{ $hojaApretada['numero'] }}: el contenido se pasa
                    {{ number_format(abs($hojaApretada['libres_mm']), 1) }} mm.
                    @if ($hojaApretada['escala_sugerida'] !== null)
                        Desde el calce de impresión se puede ajustar al
                        {{ round($hojaApretada['escala_sugerida'] * 100) }} % y cierra.
                    @else
                        Hay que pasar bienes a la hoja siguiente.
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($soloPendientes)
    <div class="aviso">
        <strong>Modo continuar hoja.</strong>
        Solo se imprime el texto de los renglones nuevos. El encabezado, los rótulos de columna, la
        cuadrícula y las firmas no salen, porque ya están en el papel: coloque en la impresora la misma
        hoja y únicamente se marcarán las líneas que faltan.
    </div>
@elseif ($ensayo)
    <div class="aviso">
        <strong>Manchote de ensayo.</strong>
        Sale la tarjeta como estaba {{ $hasta ? 'hasta el '.\Carbon\CarbonImmutable::parse($hasta)->format('d/m/Y') : 'antes de la adición nueva' }},
        con su formato completo. Imprímalo en papel de desecho, vuelva a meter esa misma hoja y use
        «Imprimir solo lo nuevo» para comprobar si la línea cae en su lugar antes de arriesgar el
        documento firmado.
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
</details>
@endif

@foreach ($hojas as $hoja)
    @php
        $escala = $hoja['escala'];

        // El calce corre toda la impresion de esta hoja los milimetros que
        // hagan falta para que la tinta caiga en los espacios libres del papel
        // que ya salio impreso. Cada papel se alimenta distinto, por eso es por
        // hoja. De lado solo tiene sentido al continuar una hoja: en la
        // impresion completa la cuadricula la dibuja el sistema.
        $desfaseX = $soloPendientes ? (float) $hoja['desfase_x_mm'] : 0.0;
        $desfaseY = (float) $hoja['desfase_y_mm'];

        $calce = match (true) {
            $desfaseX == 0.0 && $desfaseY == 0.0 => null,
            // El generador de PDF no entiende transform: se corre el relleno de
            // la hoja, que da el mismo resultado al milimetro.
            $pdf => sprintf(
                'padding-top: %smm; padding-left: %smm',
                G::margenSuperior($hoja['cara']) + $desfaseY,
                G::MARGEN_IZQUIERDO_MM + $desfaseX,
            ),
            default => sprintf('transform: translate(%smm, %smm)', $desfaseX, $desfaseY),
        };

        // Alto de cada linea de texto y relleno vertical de la celda, ya con la
        // compresion de esta hoja aplicada.
        // La hoja lleva una linea de apertura cuando no es la primera, y una de
        // cierre cuando esta cerrada.
        $lineasExtra = (int) ! $hoja['es_primera']
            + (int) ($hoja['cerrada'] && ! ($hoja['es_ultima'] && $hoja['termina_en_total']));

        $altoFilas = array_sum(array_column($hoja['filas'], 'alto_mm'))
            + $lineasExtra * G::ALTO_LINEA_MM * $escala;

    @endphp

    <section @class(['hoja', $hoja['cara'], 'hoja-'.$hoja['numero'], 'ultima' => $loop->last]) @style([$calce => $calce !== null])>
        {{-- El encabezado solo va en el frente: el formato oficial no lo repite
             al dorso, que arranca directo en los rotulos y el VIENEN. --}}
        @if ($hoja['cara'] === 'frente')
            <div class="encabezado">
                <table>
                    <tr>
                        <td colspan="2">
                            <span class="rotulo">UNIDAD DE SERVICIO:</span> {{ $unidad?->nombre }}
                        </td>
                        <td><span class="rotulo">DEPARTAMENTO:</span> {{ $unidad?->departamento }}</td>
                        <td style="text-align: right">
                            <span class="rotulo">MUNICIPIO:</span> {{ $unidad?->municipio }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <span class="rotulo">NOMBRE:</span> {{ $empleado->nombre_completo }}
                        </td>
                        <td colspan="2" style="text-align: right">
                            <span class="rotulo">CARGO:</span> {{ $empleado->cargo }}
                        </td>
                    </tr>
                </table>
            </div>
        @endif

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
                             reimprime ese, que es el que se firmo. El monto va en
                             SALDO, no en DEBE: es el acumulado de la adicion, no
                             un cargo. Y cierra con doble raya. --}}
                        <tr @class(['corte', 'ya-impreso' => in_array($fila['renglon_id'], $ocultos, true)])>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right"><span>TOTAL</span></td>
                            <td></td>
                            <td></td>
                            <td class="num doble"><span>{{ $formatearQ($fila['monto']) }}</span></td>
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

                            // Un renglon oculto ocupa su lugar pero no deja tinta:
                            // asi el que si se imprime cae sobre su linea.
                            $oculto = in_array($renglon->id, $ocultos, true);

                            // INTERINO: el formato de 2026 no tiene columna
                            // CUENTA, asi que el renglon presupuestario y sus
                            // anexos se escriben en OBSEVACIONES, que es donde los
                            // archivos del MSPAS ya ponen anotaciones de ese tipo.
                            // Cuando la DDRISST confirme donde va la cuenta,
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

                {{-- Cierre de hoja. El VAN se escribe cuando la hoja se cierra,
                     no antes: mientras quede espacio libre siguen entrando
                     bienes, y un VAN impreso de mas dejaria en el papel un saldo
                     que deja de cuadrar con el proximo renglon.
                     En la ultima hoja, si el documento ya termino con el TOTAL de
                     una adicion, el cierre repetiria el mismo numero justo
                     debajo. En las intermedias el VAN si va siempre. --}}
                @if ($hoja['cerrada'] && ! ($hoja['es_ultima'] && $hoja['termina_en_total']))
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

        <div class="firmas">
            <div class="columna">
                <div class="linea">Responsable encargado de inventarios</div>
            </div>
            <div class="columna">
                <div class="linea">Encargada de inventarios</div>
            </div>
            <div class="columna">
                <div class="linea">Director</div>
            </div>
        </div>

        <div class="pie">
            <span class="izquierda">Hoja {{ $hoja['numero'] }} de {{ $ultimaHoja }} · {{ ucfirst($hoja['cara']) }} del papel {{ $hoja['papel'] }}</span>
            <span class="derecha">
                @if ($escala != 1.0)
                    ajuste {{ round($escala * 100) }}% ·
                @endif
                @if ($desfaseX != 0.0 || $desfaseY != 0.0)
                    calce {{ $desfaseX }} / {{ $desfaseY }} mm ·
                @endif
                {{ $tarjeta->numero }} · versión {{ $tarjeta->version }}
            </span>
        </div>
    </section>
@endforeach

</body>
</html>
