import { AlertaEstado } from '@/components/alerta-estado';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp, ChevronDown, ChevronUp, Lock, Printer, RotateCcw, Save, TriangleAlert } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

/* ------------------------------------------------------------------
   Las medidas del papel vienen del servidor, del mismo lugar del que
   las toma la impresión. Aquí no se declara ninguna.
   ------------------------------------------------------------------ */
interface Geometria {
    papel_ancho: number;
    papel_alto: number;
    margen_izquierdo: number;
    margen_superior_frente: number;
    margen_superior_reverso: number;
    alto_encabezado: number;
    alto_rotulos: number;
    alto_linea: number;
    alto_firmas: number;
    alto_pie: number;
    margen_inferior: number;
    espacio_firma: number;
    escala_minima: number;
    columnas: Record<string, number>;
}

const TOPE_MM = 18;

const ROTULOS: Record<string, { rotulo: string; alinear: 'left' | 'center' | 'right' }> = {
    fecha: { rotulo: 'FECHA', alinear: 'center' },
    codigo: { rotulo: 'CODIGO', alinear: 'center' },
    cant: { rotulo: 'CANT.', alinear: 'center' },
    desc: { rotulo: 'DESCRIPCION', alinear: 'left' },
    debe: { rotulo: 'DEBE', alinear: 'right' },
    haber: { rotulo: 'HABER', alinear: 'right' },
    saldo: { rotulo: 'SALDO', alinear: 'right' },
    firma: { rotulo: 'FIRMA', alinear: 'center' },
    obs: { rotulo: 'OBSEVACIONES', alinear: 'left' },
};

interface Renglon {
    id: number;
    orden: number;
    /** Lo que mide este renglón en el papel, según su descripción. */
    alto_mm: number;
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
    banda_mm: number;
    usado_mm: number;
    libres_mm: number;
    escala: number;
    escala_sugerida: number | null;
    cerrada: boolean;
    impresa: boolean;
    desfase_x_mm: number;
    desfase_y_mm: number;
    vienen: number;
    filas: Fila[];
    cierre: { rotulo: string; monto: number } | null;
    renglones: Renglon[];
}

/** Una fila del papel: un bien, o el TOTAL que cierra una adición. */
type Fila =
    | { tipo: 'renglon'; renglon_id: number; alto_mm: number; impreso: boolean }
    | { tipo: 'total'; monto: number; alto_mm: number; impreso: boolean };

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
    geometria: Geometria;
}

const quetzales = (v: number) => v.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const conSigno = (v: number) => `${v >= 0 ? '+' : ''}${v.toFixed(1)}`;

/** Las columnas del papel, con su ancho y su posición en milímetros. */
const columnasDe = (g: Geometria) => {
    let izquierda = g.margen_izquierdo;

    return Object.entries(g.columnas).map(([clave, ancho]) => {
        const columna = { clave, ancho, izquierda, ...ROTULOS[clave] };
        izquierda += ancho;

        return columna;
    });
};

/** Dónde empiezan los rótulos de columna: el frente deja el espacio del escudo. */
const yRotulosDe = (g: Geometria, cara: string) => (cara === 'frente' ? g.margen_superior_frente + g.alto_encabezado : g.margen_superior_reverso);

/** El alto de cada renglón manda: no todos ocupan lo mismo. */
const topesDeFila = (g: Geometria, cara: string, filas: Fila[]) => {
    let y = yRotulosDe(g, cara) + g.alto_rotulos;

    return filas.map((f) => {
        const tope = { y, alto: f.alto_mm };
        y += f.alto_mm;

        return tope;
    });
};

/** El TOTAL que cierra una adición: va en SALDO y cierra con doble raya. */
function FilaTotal({ monto, y, alto, nueva, g }: { monto: number; y: number; alto: number; nueva: boolean; g: Geometria }) {
    const columnas = columnasDe(g);
    const saldo = columnas[6];
    const porcentajeX = (mm: number) => `${(mm / g.papel_ancho) * 100}%`;
    const porcentajeY = (mm: number) => `${(mm / g.papel_alto) * 100}%`;

    return (
        <>
            <Celda texto="TOTAL" columna={3} y={y} alto={alto} nueva={nueva} g={g} alinear="right" />
            <Celda texto={quetzales(monto)} columna={6} y={y} alto={alto} nueva={nueva} g={g} />
            <div
                className={cn('pointer-events-none absolute', nueva ? 'bg-slate-900' : 'bg-neutral-500')}
                style={{
                    left: porcentajeX(saldo.izquierda + 1),
                    width: porcentajeX(saldo.ancho - 2),
                    top: porcentajeY(y + alto - 0.5),
                    height: 2,
                }}
            />
        </>
    );
}

/** Una celda dibujada sobre el papel. */
function Celda({
    texto,
    columna,
    y,
    alto,
    nueva,
    g,
    alinear,
}: {
    texto: string;
    columna: number;
    y: number;
    alto: number;
    nueva: boolean;
    g: Geometria;
    alinear?: 'left' | 'center' | 'right';
}) {
    const porcentajeX = (mm: number) => `${(mm / g.papel_ancho) * 100}%`;
    const porcentajeY = (mm: number) => `${(mm / g.papel_alto) * 100}%`;
    const col = columnasDe(g)[columna];

    return (
        <div
            className={cn(
                'pointer-events-none absolute overflow-hidden font-sans whitespace-nowrap',
                nueva ? 'font-semibold text-slate-900' : 'text-neutral-500',
            )}
            style={{
                left: porcentajeX(col.izquierda + 1),
                width: porcentajeX(col.ancho - 2),
                top: porcentajeY(y + alto / 2),
                transform: 'translateY(-50%)',
                textAlign: alinear ?? col.alinear,
                fontSize: '0.74cqw',
                lineHeight: 1,
                textOverflow: 'ellipsis',
            }}
        >
            {texto}
        </div>
    );
}

export default function CalceTarjeta({ tarjeta, encabezado, hojas, geometria }: Props) {
    /* Todas las medidas salen de la geometría que manda el servidor: es la
       misma con la que se imprime, así lo que se ve aquí es lo que sale. */
    const PAPEL_ANCHO = geometria.papel_ancho;
    const PAPEL_ALTO = geometria.papel_alto;
    const MARGEN = geometria.margen_izquierdo;
    const ALTO_FILA = geometria.alto_linea;
    const COLUMNAS = columnasDe(geometria);

    const porcentajeX = (mm: number) => `${(mm / PAPEL_ANCHO) * 100}%`;
    const porcentajeY = (mm: number) => `${(mm / PAPEL_ALTO) * 100}%`;

    /* Media línea: pasado eso la tinta ya invade el renglón vecino. */
    const RIESGO_VERTICAL = ALTO_FILA / 2;
    const RIESGO_HORIZONTAL = 6;
    const [numeroHoja, setNumeroHoja] = useState(hojas[0]?.numero ?? 1);
    const hoja = hojas.find((h) => h.numero === numeroHoja) ?? hojas[0];

    const [x, setX] = useState(hoja?.desfase_x_mm ?? 0);
    const [y, setY] = useState(hoja?.desfase_y_mm ?? 0);

    /* Cuánto se aprieta el texto para que la hoja cierre sin pasarse. */
    const [escala, setEscala] = useState(hoja?.escala ?? 1);
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

    const guardado = Math.abs(x - (hoja?.desfase_x_mm ?? 0)) < 0.05 && Math.abs(y - (hoja?.desfase_y_mm ?? 0)) < 0.05;

    const pendientes = useMemo(() => (hoja?.renglones ?? []).filter((r) => !r.impreso), [hoja]);

    const hayImpresos = (hoja?.renglones ?? []).some((r) => r.impreso);

    /* El riesgo solo existe si en este papel ya hay tinta. */
    const enRiesgo = hayImpresos && pendientes.length > 0 && (Math.abs(y) > RIESGO_VERTICAL || Math.abs(x) > RIESGO_HORIZONTAL);

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
        setY(limitar(inicio.y0 + (e.clientY - inicio.py) * mmPorPx));
    };

    const alSoltar = () => {
        arrastre.current = null;
    };

    const alTeclear = (e: React.KeyboardEvent<HTMLDivElement>) => {
        const mapa: Record<string, [0 | 1, number]> = {
            ArrowUp: [1, -1],
            ArrowDown: [1, 1],
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
            { hoja: numeroHoja, desfase_x_mm: x, desfase_y_mm: y, escala },
            {
                preserveScroll: true,
                onFinish: () => setGuardando(false),
            },
        );
    };

    const moverRenglon = (renglon: Renglon, destino: number) => {
        router.post(`/inventario/tarjetas/${tarjeta.id}/renglones/${renglon.id}/hoja`, { hoja: destino }, { preserveScroll: true });
    };

    const cambiarEstadoHoja = (cerrada: boolean) => {
        const aviso = cerrada
            ? `Al cerrar la hoja ${numeroHoja} se le imprimirá su línea de VAN y dejará de admitir bienes. ¿Continuar?`
            : `¿Reabrir la hoja ${numeroHoja}? Volverá a admitir bienes y no se le imprimirá el VAN.`;

        if (!window.confirm(aviso)) return;

        router.post(`/inventario/tarjetas/${tarjeta.id}/hoja`, { hoja: numeroHoja, cerrada }, { preserveScroll: true });
    };

    if (!hoja) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Calce de impresión" />
                <div className="p-6">
                    <p className="text-muted-foreground text-sm">Esta tarjeta todavía no tiene bienes, así que no hay nada que calzar.</p>
                </div>
            </AppLayout>
        );
    }

    const Y_ROTULOS = yRotulosDe(geometria, hoja.cara);
    const ALTO_ROTULOS = geometria.alto_rotulos;
    const Y_PRIMERA_FILA = Y_ROTULOS + ALTO_ROTULOS;

    const anchoUtil = COLUMNAS.reduce((suma, c) => suma + c.ancho, 0);

    /* Cada renglón ocupa lo que necesita su descripción, no todos lo mismo. */
    const topes = topesDeFila(geometria, hoja.cara, hoja.filas);
    const porId = new Map(hoja.renglones.map((r) => [r.id, r]));
    const yFinTabla = Y_PRIMERA_FILA + hoja.usado_mm;

    /* Posición del bloque de renglones pendientes dentro de la hoja. */
    const indicePrimerPendiente = hoja.renglones.findIndex((r) => !r.impreso);
    const yBloque = topes[Math.max(0, indicePrimerPendiente)]?.y ?? Y_PRIMERA_FILA;
    const altoBloque = pendientes.reduce((suma, r) => suma + r.alto_mm, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Calce · ${encabezado.nombre}`} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <AlertaEstado />

                {/* ---------- Encabezado ---------- */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Tarjeta de responsabilidad</p>
                        <h1 className="mt-1 text-xl font-semibold">Calce de impresión</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            · <span className="text-foreground font-medium">{encabezado.nombre}</span>
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
                            <a href={`/inventario/tarjetas/${tarjeta.id}/imprimir?pendientes=1`} target="_blank" rel="noopener">
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
                        <strong className="font-semibold">Imprima siempre al 100 % y con los mismos márgenes.</strong> No use «Ajustar a la página»:
                        la tinta caería encima de lo que ya está firmado.
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
                                    top: porcentajeY(geometria.margen_superior_frente + 1),
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
                                    top: porcentajeY(geometria.margen_superior_frente + geometria.alto_encabezado / 2 + 1),
                                    fontSize: '0.86cqw',
                                    lineHeight: 1,
                                }}
                            >
                                <b>NOMBRE:</b> {encabezado.nombre} &nbsp;
                                <b>DEPTO:</b> {encabezado.area_trabajo ?? <span className="opacity-50">(sin dato)</span>}
                            </div>

                            {/* Rejilla horizontal */}
                            {[Y_ROTULOS, Y_PRIMERA_FILA, ...topes.map((t) => t.y + t.alto)].map((y, i) => (
                                <div
                                    key={`h${i}`}
                                    className="pointer-events-none absolute bg-stone-300"
                                    style={{
                                        left: porcentajeX(MARGEN),
                                        width: porcentajeX(anchoUtil),
                                        top: porcentajeY(y),
                                        height: 1,
                                    }}
                                />
                            ))}

                            {/* Rejilla vertical */}
                            {COLUMNAS.map((columna, i) => (
                                <div
                                    key={`v${i}`}
                                    className="pointer-events-none absolute bg-stone-300"
                                    style={{
                                        left: porcentajeX(columna.izquierda),
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
                                        left: porcentajeX(columna.izquierda + 1),
                                        width: porcentajeX(columna.ancho - 2),
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

                            {/* Lo que el papel ya tiene: bienes impresos y los
                                TOTAL que cierran cada adición, con su doble raya */}
                            <div style={{ opacity: verImpreso ? 1 : 0.15 }}>
                                {hoja.filas.map((fila, i) => {
                                    if (!fila.impreso) return null;

                                    return fila.tipo === 'total' ? (
                                        <FilaTotal key={`t${i}`} monto={fila.monto} y={topes[i].y} alto={topes[i].alto} nueva={false} g={geometria} />
                                    ) : (
                                        <FilaDibujada
                                            key={fila.renglon_id}
                                            renglon={porId.get(fila.renglon_id)!}
                                            y={topes[i].y}
                                            alto={topes[i].alto}
                                            nueva={false}
                                            g={geometria}
                                        />
                                    );
                                })}
                            </div>

                            {/* El cierre de la hoja: el VAN que pasa a la
                                siguiente, o el TOTAL si es la última */}
                            {hoja.cierre !== null && (
                                <FilaTotal monto={hoja.cierre.monto} y={yFinTabla} alto={ALTO_FILA} nueva={false} g={geometria} />
                            )}

                            {/* Las firmas, clavadas al pie: su lugar en el papel
                                no cambia, y arriba de ellas tiene que quedar
                                espacio para firmar a mano */}
                            {['Responsable encargado de inventarios', 'Encargada de inventarios', 'Director'].map((rotulo, i) => {
                                const ancho = anchoUtil / 3;
                                const izquierda = MARGEN + i * ancho;
                                const yFirma = PAPEL_ALTO - geometria.margen_inferior - geometria.alto_pie - geometria.alto_firmas;

                                return (
                                    <div key={rotulo} className="pointer-events-none absolute">
                                        <div
                                            className="absolute bg-stone-400"
                                            style={{
                                                left: porcentajeX(izquierda + 5),
                                                width: porcentajeX(ancho - 10),
                                                top: porcentajeY(yFirma),
                                                height: 1,
                                            }}
                                        />
                                        <div
                                            className="absolute text-center font-sans font-semibold text-neutral-500 uppercase"
                                            style={{
                                                left: porcentajeX(izquierda),
                                                width: porcentajeX(ancho),
                                                top: porcentajeY(yFirma + 2),
                                                fontSize: '0.62cqw',
                                                lineHeight: 1,
                                            }}
                                        >
                                            {rotulo}
                                        </div>
                                    </div>
                                );
                            })}

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
                                            enRiesgo ? 'border-amber-600 bg-amber-500/20' : 'border-sky-600 bg-sky-500/10',
                                        )}
                                        style={{
                                            left: porcentajeX(MARGEN),
                                            width: porcentajeX(anchoUtil),
                                            top: porcentajeY(yBloque),
                                            height: porcentajeY(altoBloque),
                                        }}
                                    />

                                    {hoja.filas.map((fila, i) => {
                                        if (fila.impreso) return null;

                                        return fila.tipo === 'total' ? (
                                            <FilaTotal key={`t${i}`} monto={fila.monto} y={topes[i].y} alto={topes[i].alto} nueva g={geometria} />
                                        ) : (
                                            <FilaDibujada
                                                key={fila.renglon_id}
                                                renglon={porId.get(fila.renglon_id)!}
                                                y={topes[i].y}
                                                alto={topes[i].alto}
                                                nueva
                                                g={geometria}
                                            />
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* --------- Ajuste del texto --------- */}
                        {(hoja.libres_mm < 0 || escala < 1) && (
                            <div
                                className={cn(
                                    'mt-3 flex flex-wrap items-center justify-between gap-3 rounded border p-3 text-sm',
                                    hoja.libres_mm < 0
                                        ? 'border-amber-500/60 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                                        : 'bg-background',
                                )}
                            >
                                <span>
                                    {hoja.libres_mm < 0 ? (
                                        <>
                                            A esta hoja le faltan <b>{Math.abs(hoja.libres_mm).toFixed(1)} mm</b> de papel.
                                            {hoja.escala_sugerida !== null
                                                ? ' Apretando el texto cierra.'
                                                : ' Hay que pasar bienes a la hoja siguiente.'}
                                        </>
                                    ) : (
                                        <>
                                            Texto al <b>{Math.round(escala * 100)} %</b>. Quedan {hoja.libres_mm.toFixed(1)} mm libres.
                                        </>
                                    )}
                                </span>

                                <div className="flex items-center gap-2">
                                    {hoja.escala_sugerida !== null && (
                                        <Button size="sm" variant="secondary" onClick={() => setEscala(hoja.escala_sugerida!)}>
                                            Ajustar al {Math.round(hoja.escala_sugerida * 100)} %
                                        </Button>
                                    )}
                                    <input
                                        type="range"
                                        min={geometria.escala_minima}
                                        max={1}
                                        step={0.01}
                                        value={escala}
                                        onChange={(e) => setEscala(Number(e.target.value))}
                                        className="w-32"
                                        aria-label="Ajuste del texto"
                                    />
                                    <span className="w-10 font-mono text-xs tabular-nums">{Math.round(escala * 100)}%</span>
                                </div>
                            </div>
                        )}

                        {/* --------- Lectura --------- */}
                        <div className="bg-background mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 rounded border p-3">
                            <div className="flex items-baseline gap-2">
                                <span className="text-muted-foreground text-[11px] tracking-wider uppercase">Vertical</span>
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
                                        h.numero === numeroHoja && 'bg-sky-50 shadow-[inset_3px_0_0] shadow-sky-600 dark:bg-sky-950/40',
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
                                        {h.renglones.filter((r) => r.impreso).length} impresos · {h.renglones.filter((r) => !r.impreso).length} por
                                        imprimir · {h.libres_mm.toFixed(1)} mm libres
                                        {h.desfase_x_mm !== 0 || h.desfase_y_mm !== 0 ? ` · calce ${h.desfase_x_mm}/${h.desfase_y_mm} mm` : ''}
                                    </p>
                                </button>
                            ))}

                            <div className="bg-muted/60 border-t px-3 py-2.5 text-xs">
                                {hoja.cerrada ? (
                                    <p>
                                        <b>Hoja cerrada.</b> Lleva su línea de <span className="font-mono">VAN</span> al pie y ya no admite bienes.
                                    </p>
                                ) : (
                                    <p>
                                        <b>Sin VAN.</b> La hoja {hoja.numero} sigue abierta, así que no se imprime la línea de{' '}
                                        <span className="font-mono">VAN</span>. Sale cuando usted la cierre.
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
                                    <div className="bg-muted col-start-2 row-start-2 grid place-items-center rounded border text-center text-[11px] leading-tight">
                                        <div>
                                            <b className="block font-mono text-sm tabular-nums">{conSigno(y)}</b>
                                            <span className="text-muted-foreground">vertical</span>
                                        </div>
                                    </div>
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
                                    <Button className="flex-1" size="sm" disabled={guardando || guardado} onClick={guardarCalce}>
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
                                <h3 className="text-xs font-semibold">Renglones en la hoja {hoja.numero}</h3>
                                <span className="text-muted-foreground text-[11px]">
                                    {hoja.usado_mm.toFixed(1)} de {hoja.banda_mm.toFixed(1)} mm
                                </span>
                            </header>

                            <div className="max-h-[420px] overflow-y-auto">
                                {hoja.renglones.length === 0 && (
                                    <p className="text-muted-foreground p-3 text-xs">Esta hoja todavía no tiene renglones asignados.</p>
                                )}

                                {hoja.renglones.map((renglon, i) => (
                                    <div
                                        key={renglon.id}
                                        className="grid grid-cols-[1fr_auto] items-center gap-x-2 gap-y-1 border-b px-3 py-2 last:border-b-0"
                                    >
                                        <p className={cn('text-xs leading-snug', renglon.impreso && 'text-muted-foreground')}>
                                            <span className="text-muted-foreground mr-1.5 tabular-nums">{i + 1}.</span>
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
                                                    onClick={() => moverRenglon(renglon, hoja.numero - 1)}
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
                                                    onClick={() => moverRenglon(renglon, hoja.numero + 1)}
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
function FilaDibujada({ renglon, y, alto, nueva, g }: { renglon: Renglon; y: number; alto: number; nueva: boolean; g: Geometria }) {
    const anotaciones = [...renglon.lineas_cuenta, renglon.observaciones ?? ''].filter(Boolean).join(' · ');

    return (
        <>
            <Celda texto={renglon.fecha} columna={0} y={y} alto={alto} nueva={nueva} g={g} />
            <Celda texto={renglon.codigo} columna={1} y={y} alto={alto} nueva={nueva} g={g} />
            <Celda texto={String(renglon.cantidad)} columna={2} y={y} alto={alto} nueva={nueva} g={g} />
            <Celda texto={renglon.descripcion} columna={3} y={y} alto={alto} nueva={nueva} g={g} />
            <Celda texto={renglon.debe > 0 ? quetzales(renglon.debe) : ''} columna={4} y={y} alto={alto} nueva={nueva} g={g} />
            <Celda texto={renglon.haber > 0 ? quetzales(renglon.haber) : ''} columna={5} y={y} alto={alto} nueva={nueva} g={g} />
            <Celda texto={quetzales(renglon.saldo)} columna={6} y={y} alto={alto} nueva={nueva} g={g} />
            <Celda texto={anotaciones} columna={8} y={y} alto={alto} nueva={nueva} g={g} />
        </>
    );
}
