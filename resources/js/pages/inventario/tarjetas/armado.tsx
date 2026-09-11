import { AlertaEstado } from '@/components/alerta-estado';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Crosshair, History, Info, Plus, Printer, RefreshCw, Search, Trash2, Undo2 } from 'lucide-react';
import { useState } from 'react';

interface Renglon {
    id: number;
    orden: number;
    bien_id: number;
    codigo: string;
    descripcion: string;
    cantidad: number;
    debe: number;
    haber: number;
    saldo: number;
    lineas_cuenta: string[];
    hoja_fisica: number | null;
    impreso: boolean;
    hoja_estimada: number;
}

interface Disponible {
    id: number;
    codigo: string;
    descripcion: string;
    total: number;
    cuenta: string | null;
    unidad_propia: boolean;
}

interface Hoja {
    numero: number;
    cara: string;
    papel: number;
    ocupados: number;
    capacidad: number;
    libre: number;
    impresa: boolean;
    reutiliza_hoja?: boolean;
    espacio_disponible?: number;
}

interface Props {
    tarjeta: {
        id: number;
        numero: string | null;
        version: number;
        estado: string;
        vigente: boolean;
        fecha_apertura: string | null;
        saldo_total: number;
        renglones_por_hoja: number;
        version_anterior: number | null;
        reemplaza_a: number | null;
    };
    encabezado: {
        unidad_servicio: string | null;
        municipio: string | null;
        departamento: string | null;
        nombre: string;
        cargo: string | null;
        area_trabajo: string | null;
    };
    renglones: Renglon[];
    disponibles: Disponible[];
    busqueda: string;
    hojas: Hoja[];
}

const quetzales = (v: number) =>
    v.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function ArmadoTarjeta({ tarjeta, encabezado, renglones, disponibles, busqueda, hojas }: Props) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(busqueda);

    // Fecha de corte del manchote de ensayo. Vacía significa automático: sale la
    // tarjeta tal como está hoy en el papel, sin la adición nueva.
    const [hasta, setHasta] = useState('');

    // Renglón cuya marca de impresión se está retractando, si hay alguno.
    const [retractando, setRetractando] = useState<Renglon | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Tarjetas', href: '/inventario/tarjetas' },
        { title: encabezado.nombre, href: '#' },
    ];

    const buscarBien = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(`/inventario/tarjetas/${tarjeta.id}`, { bien: buscar }, { preserveState: true, replace: true });
    };

    const agregar = (bienId: number) => {
        router.post(
            `/inventario/tarjetas/${tarjeta.id}/bienes`,
            { bien_id: bienId },
            { preserveScroll: true, preserveState: true },
        );
    };

    const quitar = (renglon: Renglon) => {
        const aviso = renglon.impreso
            ? `El bien ${renglon.codigo} ya está impreso en la hoja ${renglon.hoja_fisica}. Al retirarlo se generará una versión nueva de la tarjeta. ¿Continuar?`
            : `¿Retirar el bien ${renglon.codigo} de esta tarjeta?`;

        if (window.confirm(aviso)) {
            router.delete(`/inventario/tarjetas/${tarjeta.id}/bienes`, {
                data: { bien_id: renglon.bien_id },
                preserveScroll: true,
            });
        }
    };

    /** La cuenta se muestra solo cuando cambia respecto al renglón anterior. */
    const mostrarCuenta = (indice: number): string[] => {
        const actual = renglones[indice].lineas_cuenta;

        if (actual.length === 0) {
            return [];
        }

        if (indice === 0) {
            return actual;
        }

        const anterior = renglones[indice - 1].lineas_cuenta;

        return JSON.stringify(actual) === JSON.stringify(anterior) ? [] : actual;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tarjeta de ${encabezado.nombre}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <Button asChild variant="ghost" size="icon">
                            <Link href="/inventario/tarjetas">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-foreground text-xl font-semibold">{encabezado.nombre}</h1>
                                {tarjeta.numero && <Badge variant="secondary">{tarjeta.numero}</Badge>}
                                {tarjeta.version > 1 && (
                                    <Badge variant="secondary">versión {tarjeta.version}</Badge>
                                )}
                                {!tarjeta.vigente && <Badge variant="outline">Reemplazada</Badge>}
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {[encabezado.cargo, encabezado.area_trabajo].filter(Boolean).join(' · ')}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {renglones.length > 0 && puede('tarjetas.imprimir') && (
                            <>
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={`/inventario/tarjetas/${tarjeta.id}/imprimir`}
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <Printer className="size-4" />
                                        Imprimir completa
                                    </a>
                                </Button>

                                {renglones.some((r) => !r.impreso) && (
                                    <>
                                        {/* Manchote de ensayo: la tarjeta como estaba antes de la
                                            adición, para probar el calce sin arriesgar el papel firmado. */}
                                        {renglones.some((r) => r.impreso) && (
                                            <div className="flex items-center gap-1.5 rounded-md border px-1.5 py-1">
                                                <Button asChild variant="ghost" size="sm">
                                                    <a
                                                        href={`/inventario/tarjetas/${tarjeta.id}/imprimir?ensayo=1${
                                                            hasta ? `&hasta=${hasta}` : ''
                                                        }`}
                                                        target="_blank"
                                                        rel="noopener"
                                                    >
                                                        <Printer className="size-4" />
                                                        Imprimir hasta la fecha
                                                    </a>
                                                </Button>
                                                <Input
                                                    type="date"
                                                    value={hasta}
                                                    onChange={(e) => setHasta(e.target.value)}
                                                    className="h-8 w-[9.5rem] text-xs"
                                                    title="Déjelo vacío para que salga la tarjeta tal como está hoy en el papel"
                                                    aria-label="Fecha de corte del ensayo"
                                                />
                                                {hasta && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setHasta('')}
                                                        title="Volver al automático"
                                                    >
                                                        Auto
                                                    </Button>
                                                )}
                                            </div>
                                        )}

                                        <Button asChild variant="outline" size="sm">
                                            <a
                                                href={`/inventario/tarjetas/${tarjeta.id}/imprimir?pendientes=1`}
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <Printer className="size-4" />
                                                Imprimir solo lo nuevo
                                            </a>
                                        </Button>
                                    </>
                                )}

                                {/* Calzar la impresión con el papel que ya salió impreso. */}
                                <Button asChild variant="outline" size="sm">
                                    <Link href={`/inventario/tarjetas/${tarjeta.id}/calce`}>
                                        <Crosshair className="size-4" />
                                        Calce de impresión
                                    </Link>
                                </Button>
                            </>
                        )}

                        {tarjeta.reemplaza_a && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={`/inventario/tarjetas/${tarjeta.reemplaza_a}`}>
                                    <History className="size-4" />
                                    Ver versión {tarjeta.version_anterior}
                                </Link>
                            </Button>
                        )}

                        {tarjeta.vigente && puede('tarjetas.regenerar') && renglones.length > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Se creará una versión nueva con los mismos bienes y la actual quedará como histórico. ¿Continuar?',
                                        )
                                    ) {
                                        router.post(`/inventario/tarjetas/${tarjeta.id}/regenerar`);
                                    }
                                }}
                            >
                                <RefreshCw className="size-4" />
                                Regenerar
                            </Button>
                        )}
                    </div>
                </div>

                <AlertaEstado />

                {!tarjeta.vigente && (
                    <div className="border-border text-muted-foreground flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        Esta es una versión histórica de la tarjeta y no se puede modificar. Se conserva porque es el
                        documento que el empleado firmó en su momento.
                    </div>
                )}

                {/* Encabezado tal como sale impreso en cada hoja */}
                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <div className="border-border grid gap-2 border-b p-4 text-xs sm:grid-cols-3">
                        <Campo etiqueta="Unidad de servicio" valor={encabezado.unidad_servicio} />
                        <Campo etiqueta="Municipio" valor={encabezado.municipio} />
                        <Campo etiqueta="Departamento" valor={encabezado.departamento} />
                        <Campo etiqueta="Nombre" valor={encabezado.nombre} />
                        <Campo etiqueta="Cargo" valor={encabezado.cargo} />
                        <Campo etiqueta="Área" valor={encabezado.area_trabajo} />
                    </div>

                    <table className="w-full min-w-[52rem] text-sm">
                        <thead className="bg-mspas-navy text-left text-[0.625rem] tracking-wider text-white uppercase">
                            <tr>
                                <th className="px-3 py-2 font-bold">Cuenta</th>
                                <th className="w-12 px-3 py-2 text-right font-bold">Cant.</th>
                                <th className="px-3 py-2 font-bold">Descripción</th>
                                <th className="px-3 py-2 text-right font-bold">Debe</th>
                                <th className="px-3 py-2 text-right font-bold">Haber</th>
                                <th className="px-3 py-2 text-right font-bold">Saldo</th>
                                <th className="px-3 py-2 font-bold">Código</th>
                                {tarjeta.vigente && puede('tarjetas.editar') && <th className="w-10 px-3 py-2" />}
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {renglones.map((renglon, indice) => {
                                const cuenta = mostrarCuenta(indice);
                                const cambioDeHoja =
                                    indice > 0 &&
                                    (renglon.hoja_fisica ?? renglon.hoja_estimada) !==
                                        (renglones[indice - 1].hoja_fisica ?? renglones[indice - 1].hoja_estimada);

                                return (
                                    <>
                                        {cambioDeHoja && (
                                            <tr key={`corte-${renglon.id}`} className="bg-muted/60">
                                                <td colSpan={8} className="px-3 py-1.5">
                                                    <div className="text-muted-foreground flex items-center justify-between text-[0.6875rem] font-medium tracking-wide uppercase">
                                                        <span>
                                                            Van Q {quetzales(renglones[indice - 1].saldo)}
                                                        </span>
                                                        <span>
                                                            ── hoja {renglon.hoja_fisica ?? renglon.hoja_estimada} ──
                                                        </span>
                                                        <span>
                                                            Vienen Q {quetzales(renglones[indice - 1].saldo)}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}

                                        <tr key={renglon.id} className="hover:bg-muted/30">
                                            <td className="px-3 py-2 align-top">
                                                {cuenta.map((linea, i) => (
                                                    <p key={i} className="font-mono text-xs whitespace-nowrap">
                                                        {linea}
                                                    </p>
                                                ))}
                                            </td>
                                            <td className="px-3 py-2 text-right align-top tabular-nums">
                                                {renglon.cantidad}
                                            </td>
                                            <td className="max-w-md px-3 py-2 align-top">
                                                <p className="text-foreground">{renglon.descripcion}</p>
                                                {renglon.impreso && (
                                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                                        Impreso en la hoja {renglon.hoja_fisica}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right align-top tabular-nums">
                                                {renglon.debe > 0 ? quetzales(renglon.debe) : ''}
                                            </td>
                                            <td className="px-3 py-2 text-right align-top tabular-nums">
                                                {renglon.haber > 0 ? quetzales(renglon.haber) : ''}
                                            </td>
                                            <td className="px-3 py-2 text-right align-top font-medium tabular-nums">
                                                {quetzales(renglon.saldo)}
                                            </td>
                                            <td className="px-3 py-2 align-top font-mono text-xs whitespace-nowrap">
                                                {renglon.codigo}
                                            </td>
                                            {tarjeta.vigente && puede('tarjetas.editar') && (
                                                <td className="px-3 py-2 align-top">
                                                    <div className="flex gap-0.5">
                                                        {/* Corrección de control interno: se marcó
                                                            como impreso algo que no salió en papel. */}
                                                        {renglon.impreso &&
                                                            puede('tarjetas.desmarcar_impresion') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    title="Se marcó como impreso por error: retractar"
                                                                    className="size-7"
                                                                    onClick={() => setRetractando(renglon)}
                                                                >
                                                                    <Undo2 className="size-3.5" />
                                                                </Button>
                                                            )}

                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            title="Retirar de la tarjeta"
                                                            className="text-destructive hover:text-destructive size-7"
                                                            onClick={() => quitar(renglon)}
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    </>
                                );
                            })}

                            {renglones.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-muted-foreground px-3 py-10 text-center">
                                        La tarjeta está vacía. Busque un bien abajo para agregarlo.
                                    </td>
                                </tr>
                            )}
                        </tbody>

                        {renglones.length > 0 && (
                            <tfoot>
                                <tr className="border-border bg-muted/50 border-t-2">
                                    <td colSpan={5} className="px-3 py-2.5 text-right text-xs font-bold uppercase">
                                        Total
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-bold tabular-nums">
                                        Q {quetzales(tarjeta.saldo_total)}
                                    </td>
                                    <td colSpan={2} />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>

                {hojas.length > 0 && <EstadoHojas hojas={hojas} tarjetaId={tarjeta.id} renglones={renglones} />}

                {tarjeta.vigente && puede('tarjetas.editar') && (
                    <section className="border-border bg-card grid gap-4 rounded-xl border p-5">
                        <div>
                            <p className="text-foreground font-medium">Agregar bienes</p>
                            <p className="text-muted-foreground text-sm">
                                Busque por código o por nombre. Solo aparecen los bienes que no están asignados a
                                nadie más.
                            </p>
                        </div>

                        <form onSubmit={buscarBien} className="flex max-w-xl gap-2">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    className="pl-10"
                                    placeholder="0033C31E  ·  balanza  ·  camilla"
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    autoFocus
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Buscar
                            </Button>
                        </form>

                        {busqueda !== '' && (
                            <div className="border-border overflow-hidden rounded-lg border">
                                {disponibles.length === 0 ? (
                                    <p className="text-muted-foreground px-4 py-6 text-center text-sm">
                                        Ningún bien libre coincide con «{busqueda}». Puede que ya esté asignado a otro
                                        empleado o dado de baja.
                                    </p>
                                ) : (
                                    <ul className="divide-border divide-y">
                                        {disponibles.map((bien) => (
                                            <li
                                                key={bien.id}
                                                className="hover:bg-muted/40 flex items-center justify-between gap-3 px-4 py-2.5"
                                            >
                                                <div className="min-w-0">
                                                    <p className="flex flex-wrap items-center gap-2">
                                                        <span className="text-foreground font-mono text-sm font-medium">
                                                            {bien.codigo}
                                                        </span>
                                                        {bien.cuenta && (
                                                            <span className="text-muted-foreground font-mono text-xs">
                                                                {bien.cuenta}
                                                            </span>
                                                        )}
                                                        {!bien.unidad_propia && (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-mspas-amber border-mspas-amber text-xs"
                                                            >
                                                                otra unidad
                                                            </Badge>
                                                        )}
                                                    </p>
                                                    <p className="text-muted-foreground truncate text-sm">
                                                        {bien.descripcion}
                                                    </p>
                                                </div>

                                                <div className="flex shrink-0 items-center gap-3">
                                                    <span className="text-sm tabular-nums">
                                                        Q {quetzales(bien.total)}
                                                    </span>
                                                    <Button size="sm" onClick={() => agregar(bien.id)}>
                                                        <Plus className="size-4" />
                                                        Agregar
                                                    </Button>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                    </section>
                )}
            </div>
            {retractando && (
                <DialogRetractar
                    tarjetaId={tarjeta.id}
                    renglon={retractando}
                    onCerrar={() => setRetractando(null)}
                />
            )}
        </AppLayout>
    );
}

/**
 * Retracta la marca de impresión de un bien que se marcó por error.
 *
 * Exige una justificación porque queda asentada en la bitácora: es una
 * corrección sobre un documento que puede estar firmado.
 */
function DialogRetractar({
    tarjetaId,
    renglon,
    onCerrar,
}: {
    tarjetaId: number;
    renglon: Renglon;
    onCerrar: () => void;
}) {
    const [motivo, setMotivo] = useState('');
    const [enviando, setEnviando] = useState(false);

    const suficiente = motivo.trim().length >= 10;

    const enviar = () => {
        if (!suficiente) return;

        setEnviando(true);
        router.post(
            `/inventario/tarjetas/${tarjetaId}/renglones/${renglon.id}/desmarcar`,
            { motivo: motivo.trim() },
            {
                preserveScroll: true,
                onSuccess: onCerrar,
                onFinish: () => setEnviando(false),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Retractar la impresión</DialogTitle>
                    <DialogDescription>
                        El bien <span className="font-mono">{renglon.codigo}</span> volverá a quedar
                        pendiente de imprimir en la hoja {renglon.hoja_fisica}.
                    </DialogDescription>
                </DialogHeader>

                <div className="border-amber-500/60 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200 rounded-md border px-3 py-2 text-sm">
                    Esto no borra la tinta del papel. Si la hoja ya salió de la impresora, tendrá que
                    descartarla o volver a imprimirla completa.
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="motivo">Justificación</Label>
                    <textarea
                        id="motivo"
                        rows={3}
                        value={motivo}
                        onChange={(e) => setMotivo(e.target.value)}
                        placeholder="Explique qué pasó: por ejemplo, se marcó por error y la hoja nunca se imprimió."
                        className="border-input placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                    />
                    <p className="text-muted-foreground text-xs">
                        Queda registrada en la bitácora con su usuario y la fecha. Mínimo 10 caracteres.
                    </p>
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={onCerrar}>
                        Cancelar
                    </Button>
                    <Button onClick={enviar} disabled={!suficiente || enviando}>
                        {enviando ? 'Retractando…' : 'Retractar impresión'}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function Campo({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
    return (
        <div>
            <span className="text-muted-foreground font-bold tracking-wide uppercase">{etiqueta}: </span>
            <span className="text-foreground">{valor ?? '—'}</span>
        </div>
    );
}

function EstadoHojas({
    hojas,
    tarjetaId,
    renglones,
}: {
    hojas: Hoja[];
    tarjetaId: number;
    renglones: Renglon[];
}) {
    const { puede } = usePermisos();

    const registrarImpresion = (hoja: Hoja) => {
        // Se marcan los renglones que aún no han salido en papel.
        const pendientes = renglones.filter((r) => !r.impreso).map((r) => r.id);

        if (pendientes.length === 0) return;

        const aviso = hoja.reutiliza_hoja
            ? `La hoja ${hoja.numero} ya está impresa y le quedan ${hoja.espacio_disponible} renglón(es) libres. Se registrará que los ${pendientes.length} renglón(es) nuevos se imprimieron ahí, dejando en blanco lo ya impreso. ¿Continuar?`
            : `¿Registrar que los ${pendientes.length} renglón(es) pendientes se imprimieron en la hoja ${hoja.numero} (${hoja.cara})?`;

        if (window.confirm(aviso)) {
            router.post(
                `/inventario/tarjetas/${tarjetaId}/impresion`,
                { hoja: hoja.numero, renglones: pendientes },
                { preserveScroll: true },
            );
        }
    };

    return (
        <section className="border-border bg-card grid gap-3 rounded-xl border p-5">
            <div>
                <p className="text-foreground font-medium">Hojas de papel</p>
                <p className="text-muted-foreground text-sm">
                    Las hojas impares van al frente y las pares al reverso: cada papel lleva dos.
                </p>
            </div>

            <div className="grid gap-2">
                {hojas.map((hoja) => (
                    <div key={`${hoja.numero}-${hoja.impresa}`} className="flex items-center gap-3 text-sm">
                        <span className="w-32 shrink-0">
                            <span className="font-medium">Hoja {hoja.numero}</span>
                            <span className="text-muted-foreground text-xs"> · {hoja.cara}</span>
                        </span>

                        <div className="border-border bg-muted flex h-5 flex-1 overflow-hidden rounded border">
                            <div
                                className={cn('h-full', hoja.impresa ? 'bg-mspas-navy' : 'bg-mspas-amber')}
                                style={{ width: `${Math.min(100, (hoja.ocupados / hoja.capacidad) * 100)}%` }}
                            />
                        </div>

                        <span className="text-muted-foreground w-28 shrink-0 text-right text-xs tabular-nums">
                            {hoja.ocupados} / {hoja.capacidad}
                        </span>

                        <span className="w-36 shrink-0 text-right">
                            {hoja.impresa ? (
                                <Badge variant="secondary" className="text-xs">
                                    Impresa
                                </Badge>
                            ) : puede('tarjetas.imprimir') ? (
                                <Button size="sm" variant="outline" onClick={() => registrarImpresion(hoja)}>
                                    <Printer className="size-3.5" />
                                    {hoja.reutiliza_hoja ? 'Continuar hoja' : 'Marcar impresa'}
                                </Button>
                            ) : (
                                <Badge className="bg-mspas-amber text-xs text-neutral-900">Pendiente</Badge>
                            )}
                        </span>
                    </div>
                ))}
            </div>

            {hojas.some((h) => h.reutiliza_hoja) && (
                <p className="text-muted-foreground border-border flex gap-2 border-t pt-3 text-xs leading-relaxed">
                    <Info className="mt-0.5 size-3.5 shrink-0" />
                    La última hoja ya impresa tiene espacio libre: los renglones nuevos continúan ahí, y lo ya
                    impreso se deja en blanco para no imprimir encima.
                </p>
            )}
        </section>
    );
}
