<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificación {{ $certificacion->numero }}</title>

    <style>
        /* Carta vertical. El membrete va dentro de la hoja, por eso la pagina
           no lleva margen: los margenes los pone el bloque de contenido. */
        @page { size: 215.9mm 279.4mm; margin: 0; }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: Calibri, Carlito, Candara, Arial, sans-serif;
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
            position: relative;
            width: 215.9mm;
            /* La certificacion cabe en una hoja. Si alguna vez lleva tantos
               bienes que no cabe, el texto sigue en la siguiente y no se corta. */
            min-height: 279.4mm;
            margin: 14px auto;
            padding: 32.5mm 15.9mm 30mm 15mm;
            background: #fff;
            box-shadow: 0 2px 10px rgba(15, 23, 41, .18);
        }

        /* --- Membrete: recortado del formato de la institucion --- */

        .escudo {
            position: absolute;
            top: 6.5mm;
            left: 17.1mm;
            width: 62.7mm;
        }

        .institucion {
            position: absolute;
            top: 12mm;
            right: 15.9mm;
            width: 107mm;
            text-align: right;
            font-family: 'Candara Light', Candara, Calibri, sans-serif;
            font-weight: bold;
            font-size: 11pt;
            line-height: 1.15;
            color: #000;
        }

        .pie {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 7mm;
            text-align: center;
            font-family: 'Candara Light', Candara, Calibri, sans-serif;
            color: #002060;
            line-height: 1.25;
        }

        .pie img { display: block; width: 100%; margin-bottom: 1.5mm; }
        .pie .lema { font-size: 9pt; }
        .pie .direccion,
        .pie .telefono { font-size: 10pt; }

        /* --- Cuerpo --- */

        .cuerpo { font-size: 11pt; line-height: 1.15; }

        .cuerpo p {
            margin: 0 0 11pt;
            text-align: justify;
        }

        .certifica {
            text-align: center;
            font-size: 16pt;
            margin: 11pt 0 11pt;
        }

        .puntos {
            margin: 0 0 11pt;
            padding-left: 9mm;
        }

        .puntos li {
            text-align: justify;
            margin-bottom: 8pt;
        }

        .cierre { margin-top: 22pt; }

        /* Los dos bloques de firma: el de quien certifica a la izquierda y el
           visto bueno mas abajo y a la derecha, como en el formato. */
        .firma {
            width: 62%;
            text-align: center;
            font-size: 11pt;
            line-height: 1.15;
        }

        .firma.certifica-quien { margin-top: 26mm; }
        .firma.visto-bueno { margin-top: 14mm; margin-left: auto; }

        @media print {
            html, body { background: #fff; }
            .barra { display: none; }
            .hoja { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="barra">
    <span>Certificación {{ $certificacion->numero }} · papel bond membretado tamaño carta</span>
    <button type="button" onclick="window.print()">Imprimir</button>
</div>

<section class="hoja">
    <img class="escudo" src="{{ asset('img/membrete-logo.png') }}"
         alt="Ministerio de Salud Pública y Asistencia Social">

    <div class="institucion">
        DIRECCION DEPARTAMENTAL DE REDES INTEGRADAS DE SERVICIOS DE SALUD DE TOTONICAPAN
    </div>

    <div class="cuerpo">
        <p>{{ $certificacion->apertura }}</p>

        <p class="certifica">CERTIFICA:</p>

        <p>{{ $certificacion->parrafo_libro }}</p>

        <ol class="puntos">
            @foreach ($certificacion->bienes as $punto)
                <li>{{ $punto->texto }}</li>
            @endforeach
        </ol>

        <p class="cierre">{{ $certificacion->cierre }}</p>

        <div class="firma certifica-quien">
            <div>{{ $certificacion->firmante_nombre }}</div>
            <div>{{ $certificacion->firmante_cargo }}</div>
            <div>{{ $certificacion->institucion }}</div>
        </div>

        <div class="firma visto-bueno">
            <div>Vo.Bo. {{ $certificacion->vobo_nombre }}</div>
            <div>{{ $certificacion->vobo_cargo }}</div>
            <div>{{ $certificacion->institucion }}</div>
        </div>
    </div>

    <footer class="pie">
        <img src="{{ asset('img/membrete-linea.png') }}" alt="">
        <div class="lema">&ldquo;TODO SERVICIO DE SALUD PUBLICO ES GRATUITO&rdquo;</div>
        <div class="direccion">Carretera Totonicapán, zona 0, Cantón Tierra Blanca, Totonicapán, Totonicapán</div>
        <div class="telefono">Teléfono: 7763-5694</div>
    </footer>
</section>

</body>
</html>
