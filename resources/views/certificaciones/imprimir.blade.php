<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificación {{ $certificacion->numero }}</title>

    <style>
        /* Carta vertical, con los mismos margenes del formato de la institucion.
           El membrete y el pie van fijos al borde de la hoja: no se mueven
           aunque el cuerpo crezca, y se repiten si hiciera falta otra hoja. */
        @page { size: 215.9mm 279.4mm; margin: 32.5mm 15.9mm 30mm 15mm; }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
        }

        /* Lo fijo se mide desde el area de texto, no desde el borde del papel,
           por eso sale del margen con valores negativos: con margen superior de
           32.5mm, -26.1mm cae a 6.4mm del borde de la hoja. */
        .escudo {
            position: fixed;
            top: -26.1mm;
            left: 2.1mm;
            width: 62.7mm;
        }

        .institucion {
            position: fixed;
            top: -20.5mm;
            left: 78mm;
            width: 107mm;
            text-align: right;
            font-weight: bold;
            font-size: 10pt;
            line-height: 1.15;
        }

        .pie {
            position: fixed;
            bottom: -26.5mm;
            left: -15mm;
            width: 215.9mm;
            text-align: center;
            color: #002060;
            line-height: 1.25;
        }

        .pie img { display: block; width: 100%; margin-bottom: 1.5mm; }
        .pie .lema { font-size: 9pt; }
        .pie .direccion,
        .pie .telefono { font-size: 10pt; }

        p { margin: 0 0 11pt; text-align: justify; }

        .certifica { text-align: center; font-size: 14pt; margin: 14pt 0; }

        .puntos { margin: 0 0 11pt; padding-left: 8mm; }
        .puntos li { text-align: justify; margin-bottom: 8pt; }

        .cierre { margin-top: 16pt; }

        /* El espacio que el formato deja en blanco antes de las firmas. */
        .firmas { padding-top: 48mm; }

        /* El ancho decide donde parte la linea de la institucion: con este
           queda en dos renglones, como en el formato. */
        .firma { width: 54%; text-align: center; line-height: 1.2; }
        .firma.visto-bueno { margin-top: 14mm; margin-left: auto; }
    </style>
</head>
<body>

<img class="escudo" src="{{ $logo }}" alt="Ministerio de Salud Pública y Asistencia Social">

<div class="institucion">
    DIRECCION DEPARTAMENTAL DE REDES INTEGRADAS DE SERVICIOS DE SALUD DE TOTONICAPAN
</div>

<p>{{ $certificacion->apertura }}</p>

<p class="certifica">CERTIFICA:</p>

<p>{{ $certificacion->parrafo_libro }}</p>

<ol class="puntos">
    @foreach ($certificacion->bienes as $punto)
        <li>{{ $punto->texto }}</li>
    @endforeach
</ol>

<p class="cierre">{{ $certificacion->cierre }}</p>

<div class="firmas">
    <div class="firma">
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

<div class="pie">
    <img src="{{ $linea }}" alt="">
    <div class="lema">&ldquo;TODO SERVICIO DE SALUD PUBLICO ES GRATUITO&rdquo;</div>
    <div class="direccion">Carretera Totonicapán, zona 0, Cantón Tierra Blanca, Totonicapán, Totonicapán</div>
    <div class="telefono">Teléfono: 7763-5694</div>
</div>

</body>
</html>
