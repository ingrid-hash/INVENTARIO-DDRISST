import { AlertaEstado } from '@/components/alerta-estado';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Lock,
    Printer,
    RotateCcw,
    Save,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

/* ------------------------------------------------------------------
   Geometria del formato nuevo de 2026, en milimetros. Es la misma que
   usa la plantilla de impresion: papel oficio horizontal.
   ------------------------------------------------------------------ */
const PAPEL_ANCHO = 356;
const PAPEL_ALTO = 216;
const MARGEN = 10;
const Y_ENCABEZADO_1 = 15;
const Y_ENCABEZADO_2 = 21;
const Y_ROTULOS = 28;
const ALTO_ROTULOS = 6;
const Y_PRIMERA_FILA = Y_ROTULOS + ALTO_ROTULOS;
const ALTO_FILA = 4.6;
const TOPE_MM = 12;

/** Media fila: pasado eso la tinta ya invade el renglón vecino. */
const RIESGO_VERTICAL = ALTO_FILA / 2;
const RIESGO_HORIZONTAL = 6;

const COLUMNAS = [
    { rotulo: 'FECHA', ancho: 6.55, alinear: 'center' as const },
    { rotulo: 'CODIGO', ancho: 7.74, alinear: 'center' as const },
    { rotulo: 'CANT.', ancho: 3.57, alinear: 'center' as const },
    { rotulo: 'DESCRIPCION', ancho: 39.88, alinear: 'left' as const },
    { rotulo: 'DEBE', ancho: 7.74, alinear: 'right' as const },
    { rotulo: 'HABER', ancho: 7.74, alinear: 'right' as const },
    { rotulo: 'SALDO', ancho: 8.33, alinear: 'right' as const },
    { rotulo: 'FIRMA', ancho: 8.33, alinear: 'center' as const },
    { rotulo: 'OBSEVACIONES', ancho: 10.12, alinear: 'left' as const },
];

interface Renglon {
    id: number;
    orden: number;
    codigo: string;
    descripcion: string;
    cantidad: number;
    fecha: string;
    debe: number;
    haber: number;
    saldo: number;
    observaciones: string | null;
    lineas_cuenta: string[];
    impreso: boolean;
}

interface Hoja {
    numero: number;
    cara: string;
    papel: number;
    capacidad: number;
    libres: number;
    cerrada: boolean;
    impresa: boolean;
    desfase_x_mm: number;
    desfase_y_mm: number;
    vienen: number;
    renglones: Renglon[];
}

interface Props {
    tarjeta: {
        id: number;
        numero: string | null;
        version: number;
        vigente: boolean;
        renglones_por_hoja: number;
    };
    encabezado: {
        unidad_servicio: string | null;
        municipio: string | null;
        departamento: string | null;
        nombre: string;
        area_trabajo: string | null;
    };
    hojas: Hoja[];
}

const quetzales = (v: number) =>
    v.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const conSigno = (v: number) => `${v >= 0 ? '+' : ''}${v.toFixed(1)}`;

const porcentajeX = (mm: number) => `${(mm / PAPEL_ANCHO) * 100}%`;
const porcentajeY = (mm: number) => `${(mm / PAPEL_ALTO) * 100}%`;

/** Posición izquierda de una columna, en milímetros. */
const izquierdaDeColumna = (indice: number) => {
    const util = PAPEL_ANCHO - MARGEN * 2;
    let acumulado = MARGEN;

    for (let i = 0; i < indice; i++) {
        acumulado += (util * COLUMNAS[i].ancho) / 100;
    }

    return acumulado;
};

/** Una celda dibujada sobre el papel. */
function Celda({
    texto,
    columna,
    y,
    nueva,
}: {
    texto: string;
    columna: number;
    y: number;
    nueva: boolean;
}) {
    const util = PAPEL_ANCHO - MARGEN * 2;
    const ancho = (util * COLUMNAS[columna].ancho) / 100;

    return (
        <div
            className={cn(
                'pointer-events-none absolute overflow-hidden font-sans whitespace-nowrap',
                nueva ? 'font-semibold text-slate-900' : 'text-neutral-500',
            )}
            style={{
                left: porcentajeX(izquierdaDeColumna(columna) + 1),
                width: porcentajeX(ancho - 2),
                top: porcentajeY(y + ALTO_FILA / 2),
                transform: 'translateY(-50%)',
                textAlign: COLUMNAS[columna].alinear,
                fontSize: '0.74cqw',
                lineHeight: 1,
                textOverflow: 'ellipsis',
            }}
        >
            {texto}
        </div>
    );
}

export default function CalceTarjeta({ tarjeta, encabezado, hojas }: Props) {
    const [numeroHoja, setNumeroHoja] = useState(hojas[0]?.numero ?? 1);
    const hoja = hojas.find((h) => h.numero === numeroHoja) ?? hojas[0];

    const [x, setX] = useState(hoja?.desfase_x_mm ?? 0);
    const [y, setY] = useState(hoja?.desfase_y_mm ?? 0);
    const [paso, setPaso] = useState(0.5);
    const [verImpreso, setVerImpreso] = useState(true);
    const [guardando, setGuardando] = useState(false);

    const papelRef = useRef<HTMLDivElement>(null);
    const arrastre = useRef<{ px: number; py: number; x0: number; y0: number } | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Tarjetas', href: '/inventario/tarjetas' },
        { title: encabezado.nombre, href: `/inventario/tarjetas/${tarjeta.id}` },
        { title: 'Calce de impresión', href: '#' },
    ];

    const limitar = (v: number) => Math.max(-TOPE_MM, Math.min(TOPE_MM, v));

    const guardado =
        Math.abs(x - (hoja?.desfase_x_mm ?? 0)) < 0.05 &&
        Math.abs(y - (hoja?.desfase_y_mm ?? 0)) < 0.05;

    const pendientes = useMemo(
        () => (hoja?.renglones ?? []).filter((r) => !r.impreso),
        [hoja],
    );

    const hayImpresos = (hoja?.renglones ?? []).some((r) => r.impreso);

    /* El riesgo solo existe si en este papel ya hay tinta. */
    const enRiesgo =
        hayImpresos &&
        pendientes.length > 0 &&
        (Math.abs(y) > RIESGO_VERTICAL || Math.abs(x) > RIESGO_HORIZONTAL);

    const cambiarHoja = (numero: number) => {
        const destino = hojas.find((h) => h.numero === numero);
        if (!destino) return;

        setNumeroHoja(numero);
        setX(destino.desfase_x_mm);
        setY(destino.desfase_y_mm);
    };

    /* ---------------- Arrastre del bloque de tinta ---------------- */
    const alPresionar = (e: React.PointerEvent<HTMLDivElement>) => {
        arrastre.current = { px: e.clientX, py: e.clientY, x0: x, y0: y };
        e.currentTarget.setPointerCapture(e.pointerId);
    };

    const alMover = (e: React.PointerEvent<HTMLDivElement>) => {
        const inicio = arrastre.current;
        if (!inicio || !papelRef.current) return;

        const mmPorPx = PAPEL_ANCHO / papelRef.current.clientWidth;
        setX(limitar(inicio.x0 + (e.clientX - inicio.px) * mmPorPx));
        setY(limitar(inicio.y0 + (e.clientY - inicio.py) * mmPorPx));
    };

    const alSoltar = () => {
        arrastre.current = null;
    };

    const alTeclear = (e: React.KeyboardEvent<HTMLDivElement>) => {
        const mapa: Record<string, [0 | 1, number]> = {
            ArrowUp: [1, -1],
            ArrowDown: [1, 1],
            ArrowLeft: [0, -1],
            ArrowRight: [0, 1],
        };

        const orden = mapa[e.key];
        if (!orden) return;

        e.preventDefault();
        const [eje, direccion] = orden;

        if (eje === 0) setX((v) => limitar(v + direccion * paso));
        else setY((v) => limitar(v + direccion * paso));
    };

    /* ---------------- Acciones ---------------- */
    const guardarCalce = () => {
        setGuardando(true);
        router.post(
            `/inventario/tarjetas/${tarjeta.id}/calce`,
            { hoja: numeroHoja, desfase_x_mm: x, desfase_y_mm: y },
            {
                preserveScroll: true,
                onFinish: () => setGuardando(false),
            },
        );
    };

    const moverRenglon = (renglon: Renglon, destino: number) => {
        router.post(
            `/inventario/tarjetas/${tarjeta.id}/renglones/${renglon.id}/hoja`,
            { hoja: destino },
            { preserveScroll: true },
        );
    };

    const cambiarEstadoHoja = (cerrada: boolean) => {
        const aviso = cerrada
            ? `Al cerrar la hoja ${numeroHoja} se le imprimirá su línea de VAN y dejará de admitir bienes. ¿Continuar?`
            : `¿Reabrir la hoja ${numeroHoja}? Volverá a admitir bienes y no se le imprimirá el VAN.`;

        if (!window.confirm(aviso)) return;

        router.post(
            `/inventario/tarjetas/${tarjeta.id}/hoja`,
            { hoja: numeroHoja, cerrada },
            { preserveScroll: true },
        );
    };

    if (!hoja) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Calce de impresión" />
                <div className="p-6">
                    <p className="text-muted-foreground text-sm">
                        Esta tarjeta todavía no tiene bienes, así que no hay nada que calzar.
                    </p>
                </div>
            </AppLayout>
        );
    }

    const anchoUtil = PAPEL_ANCHO - MARGEN * 2;
    const yFinTabla = Y_PRIMERA_FILA + hoja.capacidad * ALTO_FILA;

    /* Posición del bloque de renglones pendientes dentro de la hoja. */
    const indicePrimerPendiente = hoja.renglones.findIndex((r) => !r.impreso);
    const yBloque = Y_PRIMERA_FILA + Math.max(0, indicePrimerPendiente) * ALTO_FILA;
    const altoBloque = pendientes.length * ALTO_FILA;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Calce · ${encabezado.nombre}`} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <AlertaEstado />

                {/* ---------- Encabezado ---------- */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                            Tarjeta de responsabilidad
                        </p>
                        <h1 className="mt-1 text-xl font-semibold">Calce de impresión</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Alinear la tinta nueva sobre una hoja que ya salió impresa ·{' '}
                            <span className="text-foreground font-medium">{encabezado.nombre}</span>
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/inventario/tarjetas/${tarjeta.id}`}>
                                <ArrowLeft className="size-4" />
                                Volver a la tarjeta
                            </Link>
                        </Button>
                        <Button size="sm" asChild>
                            <a
                                href={`/inventario/tarjetas/${tarjeta.id}/imprimir?pendientes=1`}
                                target="_blank"
                                rel="noopener"
                            >
                                <Printer className="size-4" />
                                Imprimir lo pendiente
                            </a>
                        </Button>
                    </div>
                </div>

                {/* ---------- Aviso de ajustes de impresión ---------- */}
                <div className="flex items-start gap-3 rounded-md border-l-4 border-amber-500 bg-amber-50 p-3 text-sm dark:bg-amber-950/40">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <p className="text-amber-900 dark:text-amber-200">
                        <strong className="font-semibold">
                            Imprima siempre al 100 % y con los mismos márgenes.
                        </strong>{' '}
                        Si usa «Ajustar a la página», el navegador encoge la hoja cerca de un 6 % y el
                        calce guardado deja de servir: la tinta caería encima de lo que ya está firmado.
                    </p>
                </div>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                    {/* ================= MESA DE LUZ ================= */}
                    <section className="bg-muted/40 rounded-lg border p-4">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-baseline gap-3">
                                <h2 className="text-sm font-semibold">
                                    Hoja {hoja.numero} · {hoja.cara} del papel {hoja.papel}
                                </h2>
                                <span className="text-muted-foreground text-xs">
                                    {PAPEL_ANCHO} × {PAPEL_ALTO} mm
                                </span>
                            </div>

                            <label className="bg-background flex cursor-pointer items-center gap-2 rounded border px-2.5 py-1.5 text-xs">
                                <input
                                    id="ver-impreso"
                                    type="checkbox"
                                    checked={verImpreso}
                                    onChange={(e) => setVerImpreso(e.target.checked)}
                                    className="accent-primary"
                                />
                                Ver lo ya impreso
                            </label>
                        </div>

                        {/* --------- El papel --------- */}
                        <div
                            ref={papelRef}
                            className="relative w-full overflow-hidden rounded-sm border border-stone-300 shadow-md"
                            style={{
                                aspectRatio: `${PAPEL_ANCHO} / ${PAPEL_ALTO}`,
                                background: '#fbf8f1',
                                containerType: 'inline-size',
                                touchAction: 'none',
                            }}
                        >
                            {/* Encabezado impreso del formato nuevo */}
                            <div
                                className="pointer-events-none absolute font-sans whitespace-nowrap text-neutral-500"
                                style={{
                                    left: porcentajeX(MARGEN),
                                    top: porcentajeY(Y_ENCABEZADO_1),
                                    fontSize: '0.86cqw',
                                    lineHeight: 1,
                                }}
                            >
                                <b>UNIDAD DE SERVICIO:</b> {encabezado.unidad_servicio} &nbsp;
                                <b>MUNICIPIO:</b> {encabezado.municipio} &nbsp;
                                <b>DEPTO:</b> {encabezado.departamento}
                            </div>

                            <div
                                className="pointer-events-none absolute font-sans whitespace-nowrap text-neutral-500"
                                style={{
                                    left: porcentajeX(MARGEN),
                                    top: porcentajeY(Y_ENCABEZADO_2),
                                    fontSize: '0.86cqw',
                                    lineHeight: 1,
                                }}
                            >
                                <b>NOMBRE:</b> {encabezado.nombre} &nbsp;
                                <b>DEPTO:</b>{' '}
                                {encabezado.area_trabajo ?? (
                                    <span className="opacity-50">(sin dato)</span>
                                )}
                            </div>

                            {/* Rejilla horizontal */}
                            {Array.from({ length: hoja.capacidad + 2 }).map((_, i) => (
                                <div
                                    key={`h${i}`}
                                    className="pointer-events-none absolute bg-stone-300"
                                    style={{
                                        left: porcentajeX(MARGEN),
                                        width: porcentajeX(anchoUtil),
                                        top: porcentajeY(
                                            i === 0
                                                ? Y_ROTULOS
                                                : Y_ROTULOS + ALTO_ROTULOS + (i - 1) * ALTO_FILA,
                                        ),
                                        height: 1,
                                    }}
                                />
                            ))}

                            {/* Rejilla vertical */}
                            {COLUMNAS.map((_, i) => (
                                <div
                                    key={`v${i}`}
                                    className="pointer-events-none absolute bg-stone-300"
                                    style={{
                                        left: porcentajeX(izquierdaDeColumna(i)),
                                        top: porcentajeY(Y_ROTULOS),
                                        width: 1,
                                        height: porcentajeY(yFinTabla - Y_ROTULOS),
                                    }}
                                />
                            ))}
                            <div
                                className="pointer-events-none absolute bg-stone-300"
                                style={{
                                    left: porcentajeX(MARGEN + anchoUtil),
                                    top: porcentajeY(Y_ROTULOS),
                                    width: 1,
                                    height: porcentajeY(yFinTabla - Y_ROTULOS),
                                }}
                            />

                            {/* Rótulos de columna */}
                            {COLUMNAS.map((columna, i) => (
                                <div
                                    key={`r${i}`}
                                    className="pointer-events-none absolute font-sans font-bold whitespace-nowrap text-neutral-500"
                                    style={{
                                        left: porcentajeX(izquierdaDeColumna(i) + 1),
                                        width: porcentajeX(
                                            (anchoUtil * columna.ancho) / 100 - 2,
                                        ),
                                        top: porcentajeY(Y_ROTULOS + ALTO_ROTULOS / 2),
                                        transform: 'translateY(-50%)',
                                        textAlign: 'center',
                                        fontSize: '0.78cqw',
                                        lineHeight: 1,
                                    }}
                                >
                                    {columna.rotulo}
                                </div>
                            ))}

                            {/* Renglones ya impresos: son la tinta que el papel ya tiene */}
                            <div style={{ opacity: verImpreso ? 1 : 0.15 }}>
                                {hoja.renglones.map((renglon, i) =>
                                    renglon.impreso ? (
                                        <FilaDibujada
                                            key={renglon.id}
                                            renglon={renglon}
                                            y={Y_PRIMERA_FILA + i * ALTO_FILA}
                                            nueva={false}
                                        />
                                    ) : null,
                                )}
                            </div>

                            {/* Hueco donde deberían caer los pendientes */}
                            {pendientes.length > 0 && (
                                <div
                                    className="pointer-events-none absolute rounded-sm border border-dotted border-stone-400"
                                    style={{
                                        left: porcentajeX(MARGEN),
                                        width: porcentajeX(anchoUtil),
                                        top: porcentajeY(yBloque),
                                        height: porcentajeY(altoBloque),
                                    }}
                                />
                            )}

                            {/* Capa móvil: la tinta nueva */}
                            {pendientes.length > 0 && (
                                <div
                                    role="application"
                                    tabIndex={0}
                                    aria-label="Bloque de renglones pendientes. Arrastre o use las flechas para calzar la impresión."
                                    onPointerDown={alPresionar}
                                    onPointerMove={alMover}
                                    onPointerUp={alSoltar}
                                    onPointerCancel={alSoltar}
                                    onKeyDown={alTeclear}
                                    className="absolute inset-0 cursor-grab focus-visible:outline-none active:cursor-grabbing"
                                    style={{
                                        transform: `translate(${(x / PAPEL_ANCHO) * 100}%, ${(y / PAPEL_ALTO) * 100}%)`,
                                    }}
                                >
                                    <div
                                        className={cn(
                                            'pointer-events-none absolute rounded-sm border border-dashed',
                                            enRiesgo
                                                ? 'border-amber-600 bg-amber-500/20'
                                                : 'border-sky-600 bg-sky-500/10',
                                        )}
                                        style={{
                                            left: porcentajeX(MARGEN),
                                            width: porcentajeX(anchoUtil),
                                            top: porcentajeY(yBloque),
                                            height: porcentajeY(altoBloque),
                                        }}
                                    />

                                    {hoja.renglones.map((renglon, i) =>
                                        renglon.impreso ? null : (
                                            <FilaDibujada
                                                key={renglon.id}
                                                renglon={renglon}
                                                y={Y_PRIMERA_FILA + i * ALTO_FILA}
                                                nueva
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </div>

                        {/* --------- Lectura --------- */}
                        <div className="bg-background mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 rounded border p-3">
                            <div className="flex items-baseline gap-2">
                                <span className="text-muted-foreground text-[11px] tracking-wider uppercase">
                                    Horizontal
                                </span>
                                <b className="font-mono text-base tabular-nums">{conSigno(x)} mm</b>
                            </div>
                            <div className="flex items-baseline gap-2">
                                <span className="text-muted-foreground text-[11px] tracking-wider uppercase">
                                    Vertical
                                </span>
                                <b className="font-mono text-base tabular-nums">{conSigno(y)} mm</b>
                            </div>

                            {pendientes.length === 0 ? (
                                <Badge variant="secondary">Nada pendiente en esta hoja</Badge>
                            ) : enRiesgo ? (
                                <Badge className="bg-amber-100 text-amber-900 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200">
                                    Riesgo: invadiría un renglón ya impreso
                                </Badge>
                            ) : (
                                <Badge className="bg-emerald-100 text-emerald-900 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-200">
                                    Calza en los espacios libres
                                </Badge>
                            )}

                            <Badge variant={guardado ? 'secondary' : 'default'} className="ml-auto">
                                {guardado ? 'Guardada' : 'Sin guardar'}
                            </Badge>
                        </div>
                    </section>

                    {/* ================= INSTRUMENTOS ================= */}
                    <div className="flex flex-col gap-4">
                        {/* --------- Hojas --------- */}
                        <section className="overflow-hidden rounded-lg border">
                            <header className="bg-muted/60 border-b px-3 py-2">
                                <h3 className="text-xs font-semibold">Hojas de esta tarjeta</h3>
                            </header>

                            {hojas.map((h) => (
                                <button
                                    key={h.numero}
                                    type="button"
                                    onClick={() => cambiarHoja(h.numero)}
                                    aria-current={h.numero === numeroHoja}
                                    className={cn(
                                        'hover:bg-muted/60 w-full border-b px-3 py-2.5 text-left last:border-b-0',
                                        h.numero === numeroHoja &&
                                            'bg-sky-50 shadow-[inset_3px_0_0] shadow-sky-600 dark:bg-sky-950/40',
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-semibold">
                                            Hoja {h.numero} · {h.cara}
                                        </span>
                                        <Badge variant={h.cerrada ? 'secondary' : 'outline'}>
                                            {h.cerrada ? 'Cerrada' : h.impresa ? 'Abierta' : 'Sin usar'}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        {h.renglones.filter((r) => r.impreso).length} impresos ·{' '}
                                        {h.renglones.filter((r) => !r.impreso).length} por imprimir ·{' '}
                                        {h.libres} libres
                                        {h.desfase_x_mm !== 0 || h.desfase_y_mm !== 0
                                            ? ` · calce ${h.desfase_x_mm}/${h.desfase_y_mm} mm`
                                            : ''}
                                    </p>
                                </button>
                            ))}

                            <div className="bg-muted/60 border-t px-3 py-2.5 text-xs">
                                {hoja.cerrada ? (
                                    <p>
                                        <b>Hoja cerrada.</b> Lleva su línea de{' '}
                                        <span className="font-mono">VAN</span> al pie y ya no admite
                                        bienes.
                                    </p>
                                ) : (
                                    <p>
                                        <b>Sin VAN.</b> La hoja {hoja.numero} sigue abierta, así que no
                                        se imprime la línea de{' '}
                                        <span className="font-mono">VAN</span>. Sale cuando usted la
                                        cierre.
                                    </p>
                                )}

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-2 w-full"
                                    disabled={!tarjeta.vigente}
                                    onClick={() => cambiarEstadoHoja(!hoja.cerrada)}
                                >
                                    {hoja.cerrada ? 'Reabrir hoja' : 'Cerrar hoja'}
                                </Button>
                            </div>
                        </section>

                        {/* --------- Calibre --------- */}
                        <section className="overflow-hidden rounded-lg border">
                            <header className="bg-muted/60 flex items-center justify-between border-b px-3 py-2">
                                <h3 className="text-xs font-semibold">Calce en milímetros</h3>
                                <span className="text-muted-foreground text-[11px]">± {paso} mm</span>
                            </header>

                            <div className="p-3">
                                <div className="mx-auto mb-3 grid w-[150px] grid-cols-[34px_1fr_34px] grid-rows-[34px_1fr_34px] gap-1.5">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="col-start-2 row-start-1 size-full"
                                        aria-label="Subir"
                                        onClick={() => setY((v) => limitar(v - paso))}
                                    >
                                        <ChevronUp className="size-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="col-start-1 row-start-2 size-full"
                                        aria-label="Izquierda"
                                        onClick={() => setX((v) => limitar(v - paso))}
                                    >
                                        <ChevronLeft className="size-4" />
                                    </Button>
                                    <div className="bg-muted col-start-2 row-start-2 grid place-items-center rounded border text-center text-[11px] leading-tight">
                                        <div>
                                            <b className="block font-mono text-sm tabular-nums">
                                                {conSigno(y)}
                                            </b>
                                            <span className="text-muted-foreground">vertical</span>
                                        </div>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="col-start-3 row-start-2 size-full"
                                        aria-label="Derecha"
                                        onClick={() => setX((v) => limitar(v + paso))}
                                    >
                                        <ChevronRight className="size-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="col-start-2 row-start-3 size-full"
                                        aria-label="Bajar"
                                        onClick={() => setY((v) => limitar(v + paso))}
                                    >
                                        <ChevronDown className="size-4" />
                                    </Button>
                                </div>

                                <div className="mb-3 flex gap-1.5">
                                    {[0.1, 0.5, 1].map((valor) => (
                                        <Button
                                            key={valor}
                                            variant={paso === valor ? 'default' : 'outline'}
                                            size="sm"
                                            className="flex-1 text-xs"
                                            onClick={() => setPaso(valor)}
                                        >
                                            {valor} mm
                                        </Button>
                                    ))}
                                </div>

                                <div className="flex gap-2">
                                    <Button
                                        className="flex-1"
                                        size="sm"
                                        disabled={guardando || guardado}
                                        onClick={guardarCalce}
                                    >
                                        <Save className="size-4" />
                                        Guardar calce
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        aria-label="Volver a cero"
                                        onClick={() => {
                                            setX(0);
                                            setY(0);
                                        }}
                                    >
                                        <RotateCcw className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </section>

                        {/* --------- Renglones --------- */}
                        <section className="overflow-hidden rounded-lg border">
                            <header className="bg-muted/60 flex items-center justify-between border-b px-3 py-2">
                                <h3 className="text-xs font-semibold">
                                    Renglones en la hoja {hoja.numero}
                                </h3>
                                <span className="text-muted-foreground text-[11px]">
                                    {hoja.renglones.length} de {hoja.capacidad}
                                </span>
                            </header>

                            <div className="max-h-[420px] overflow-y-auto">
                                {hoja.renglones.length === 0 && (
                                    <p className="text-muted-foreground p-3 text-xs">
                                        Esta hoja todavía no tiene renglones asignados.
                                    </p>
                                )}

                                {hoja.renglones.map((renglon, i) => (
                                    <div
                                        key={renglon.id}
                                        className="grid grid-cols-[1fr_auto] items-center gap-x-2 gap-y-1 border-b px-3 py-2 last:border-b-0"
                                    >
                                        <p
                                            className={cn(
                                                'text-xs leading-snug',
                                                renglon.impreso && 'text-muted-foreground',
                                            )}
                                        >
                                            <span className="text-muted-foreground mr-1.5 tabular-nums">
                                                {i + 1}.
                                            </span>
                                            {renglon.descripcion}
                                        </p>

                                        {renglon.impreso ? (
                                            <span
                                                className="flex items-center gap-1 text-[10px] tracking-wide text-amber-700 uppercase dark:text-amber-500"
                                                title="Ya salió en tinta: su posición en el papel es definitiva."
                                            >
                                                <Lock className="size-3" />
                                                Impreso
                                            </span>
                                        ) : (
                                            <div className="flex gap-1">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7"
                                                    aria-label="Subir a la hoja anterior"
                                                    title="Subir a la hoja anterior"
                                                    disabled={hoja.numero <= 1 || !tarjeta.vigente}
                                                    onClick={() =>
                                                        moverRenglon(renglon, hoja.numero - 1)
                                                    }
                                                >
                                                    <ArrowUp className="size-3.5" />
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7"
                                                    aria-label="Bajar a la hoja siguiente"
                                                    title="Bajar a la hoja siguiente"
                                                    disabled={!tarjeta.vigente}
                                                    onClick={() =>
                                                        moverRenglon(renglon, hoja.numero + 1)
                                                    }
                                                >
                                                    <ArrowDown className="size-3.5" />
                                                </Button>
                                            </div>
                                        )}

                                        <p className="text-muted-foreground col-span-2 font-mono text-[10px] tracking-wide">
                                            {renglon.codigo} · Q {quetzales(renglon.debe)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

/** Las nueve celdas de un renglón, dibujadas sobre el papel. */
function FilaDibujada({ renglon, y, nueva }: { renglon: Renglon; y: number; nueva: boolean }) {
    const anotaciones = [...renglon.lineas_cuenta, renglon.observaciones ?? '']
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Celda texto={renglon.fecha} columna={0} y={y} nueva={nueva} />
            <Celda texto={renglon.codigo} columna={1} y={y} nueva={nueva} />
            <Celda texto={String(renglon.cantidad)} columna={2} y={y} nueva={nueva} />
            <Celda texto={renglon.descripcion} columna={3} y={y} nueva={nueva} />
            <Celda
                texto={renglon.debe > 0 ? quetzales(renglon.debe) : ''}
                columna={4}
                y={y}
                nueva={nueva}
            />
            <Celda
                texto={renglon.haber > 0 ? quetzales(renglon.haber) : ''}
                columna={5}
                y={y}
                nueva={nueva}
            />
            <Celda texto={quetzales(renglon.saldo)} columna={6} y={y} nueva={nueva} />
            <Celda texto={anotaciones} columna={8} y={y} nueva={nueva} />
        </>
    );
}
