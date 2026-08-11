import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileSpreadsheet, Pencil } from 'lucide-react';

interface Props {
    bien: {
        id: number;
        codigo: string;
        descripcion: string;
        cantidad: number;
        precio_unitario: number;
        total: number;
        cuenta: string | null;
        cuenta_texto_original: string | null;
        lineas_columna_cuenta: string[];
        unidad: string | null;
        tipo_movimiento: string;
        forma_adquisicion: string | null;
        programa: string | null;
        documento_respaldo: string | null;
        fecha_texto_original: string | null;
        fecha_ingreso: string | null;
        anio_ingreso: number | null;
        estado: string;
        estado_legible: string;
        observaciones: string | null;
        origen_importacion: string | null;
    };
    asignaciones: {
        id: number;
        empleado: string | null;
        cargo: string | null;
        desde: string | null;
        hasta: string | null;
        activa: boolean;
        motivo_cierre: string | null;
    }[];
    bajas: {
        id: number;
        numero_acta: string | null;
        motivo: string;
        estado: string;
        fecha_solicitud: string | null;
        fecha_resolucion: string | null;
        solicitada_por: string | null;
        resuelta_por: string | null;
    }[];
}

const quetzales = (valor: number) =>
    'Q ' + valor.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function BienDetalle({ bien, asignaciones, bajas }: Props) {
    const { puede } = usePermisos();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Bienes', href: '/inventario/bienes' },
        { title: bien.codigo, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Bien ${bien.codigo}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <Button asChild variant="ghost" size="icon">
                            <Link href="/inventario/bienes">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-foreground font-mono text-xl font-semibold">{bien.codigo}</h1>
                                <EstadoBien estado={bien.estado} etiqueta={bien.estado_legible} />
                            </div>
                            <p className="text-muted-foreground mt-1 max-w-2xl text-sm">{bien.descripcion}</p>
                        </div>
                    </div>

                    {puede('bienes.editar') && (
                        <Button asChild variant="outline">
                            <Link href={`/inventario/bienes/${bien.id}/editar`}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <section className="border-border bg-card grid gap-4 rounded-xl border p-5 lg:col-span-2">
                        <p className="text-foreground font-medium">Datos del bien</p>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Dato etiqueta="Unidad de servicio" valor={bien.unidad} />
                            <Dato etiqueta="Cuenta" valor={bien.cuenta} />
                            <Dato etiqueta="Movimiento" valor={bien.tipo_movimiento} />
                            <Dato etiqueta="Cantidad" valor={String(bien.cantidad)} />
                            <Dato etiqueta="Precio unitario" valor={quetzales(bien.precio_unitario)} />
                            <Dato etiqueta="Total" valor={quetzales(bien.total)} destacado />
                            <Dato etiqueta="Forma de adquisición" valor={bien.forma_adquisicion} />
                            <Dato etiqueta="Programa" valor={bien.programa} />
                            <Dato etiqueta="Documento de respaldo" valor={bien.documento_respaldo} />
                            <Dato
                                etiqueta="Fecha de ingreso"
                                valor={bien.fecha_ingreso ?? (bien.anio_ingreso ? String(bien.anio_ingreso) : null)}
                            />
                        </div>

                        {bien.observaciones && (
                            <div>
                                <p className="text-muted-foreground text-xs">Observaciones</p>
                                <p className="text-foreground mt-1 text-sm">{bien.observaciones}</p>
                            </div>
                        )}
                    </section>

                    <section className="border-border bg-card grid content-start gap-4 rounded-xl border p-5">
                        <p className="text-foreground font-medium">Cómo se imprime en la tarjeta</p>

                        <div className="border-border overflow-hidden rounded-lg border">
                            <div className="bg-mspas-navy px-3 py-1.5 text-center text-[0.625rem] font-bold tracking-wider text-white uppercase">
                                Cuenta
                            </div>
                            <div className="bg-background grid gap-0.5 p-3">
                                {bien.lineas_columna_cuenta.length > 0 ? (
                                    bien.lineas_columna_cuenta.map((linea, i) => (
                                        <p key={i} className="text-foreground font-mono text-xs">
                                            {linea}
                                        </p>
                                    ))
                                ) : (
                                    <p className="text-muted-foreground text-xs italic">
                                        Celda vacía, como en el documento original
                                    </p>
                                )}
                            </div>
                        </div>

                        {bien.cuenta_texto_original !== null && (
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                Se conserva el texto original del Excel para que la reimpresión salga idéntica al papel
                                firmado.
                            </p>
                        )}

                        {bien.origen_importacion && (
                            <div className="border-border flex items-start gap-2 border-t pt-3">
                                <FileSpreadsheet className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                <div className="min-w-0">
                                    <p className="text-muted-foreground text-xs">Importado de</p>
                                    <p className="text-foreground truncate text-xs">{bien.origen_importacion}</p>
                                </div>
                            </div>
                        )}
                    </section>
                </div>

                <section className="border-border bg-card overflow-hidden rounded-xl border">
                    <div className="border-border border-b px-5 py-3">
                        <p className="text-foreground font-medium">Historial de custodia</p>
                        <p className="text-muted-foreground text-xs">Quién ha respondido por este bien y desde cuándo</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th className="px-5 py-2.5 font-medium">Responsable</th>
                                    <th className="px-5 py-2.5 font-medium">Desde</th>
                                    <th className="px-5 py-2.5 font-medium">Hasta</th>
                                    <th className="px-5 py-2.5 font-medium">Situación</th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {asignaciones.map((a) => (
                                    <tr key={a.id} className="hover:bg-muted/30">
                                        <td className="px-5 py-2.5">
                                            <p className="text-foreground font-medium">{a.empleado}</p>
                                            {a.cargo && <p className="text-muted-foreground text-xs">{a.cargo}</p>}
                                        </td>
                                        <td className="px-5 py-2.5 tabular-nums">{a.desde ?? '—'}</td>
                                        <td className="px-5 py-2.5 tabular-nums">{a.hasta ?? '—'}</td>
                                        <td className="px-5 py-2.5">
                                            {a.activa ? (
                                                <Badge className="bg-mspas-teal text-white">Vigente</Badge>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">
                                                    {a.motivo_cierre ?? 'Cerrada'}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}

                                {asignaciones.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground px-5 py-8 text-center">
                                            Este bien no está asignado a ningún empleado.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {bajas.length > 0 && (
                    <section className="border-border bg-card overflow-hidden rounded-xl border">
                        <div className="border-border border-b px-5 py-3">
                            <p className="text-foreground font-medium">Expedientes de baja</p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-5 py-2.5 font-medium">Acta</th>
                                        <th className="px-5 py-2.5 font-medium">Motivo</th>
                                        <th className="px-5 py-2.5 font-medium">Solicitud</th>
                                        <th className="px-5 py-2.5 font-medium">Resolución</th>
                                        <th className="px-5 py-2.5 font-medium">Estado</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {bajas.map((b) => (
                                        <tr key={b.id} className="hover:bg-muted/30">
                                            <td className="px-5 py-2.5 font-mono text-xs">{b.numero_acta ?? '—'}</td>
                                            <td className="px-5 py-2.5">{b.motivo}</td>
                                            <td className="px-5 py-2.5">
                                                <p className="tabular-nums">{b.fecha_solicitud ?? '—'}</p>
                                                {b.solicitada_por && (
                                                    <p className="text-muted-foreground text-xs">{b.solicitada_por}</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-2.5">
                                                <p className="tabular-nums">{b.fecha_resolucion ?? '—'}</p>
                                                {b.resuelta_por && (
                                                    <p className="text-muted-foreground text-xs">{b.resuelta_por}</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-2.5">
                                                <Badge variant="secondary">{b.estado}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function Dato({ etiqueta, valor, destacado }: { etiqueta: string; valor: string | null; destacado?: boolean }) {
    return (
        <div>
            <p className="text-muted-foreground text-xs">{etiqueta}</p>
            <p
                className={
                    destacado
                        ? 'text-foreground mt-1 text-sm font-semibold tabular-nums'
                        : 'text-foreground mt-1 text-sm'
                }
            >
                {valor ?? '—'}
            </p>
        </div>
    );
}

function EstadoBien({ estado, etiqueta }: { estado: string; etiqueta: string }) {
    if (estado === 'activo') {
        return <Badge className="bg-mspas-teal text-white">{etiqueta}</Badge>;
    }

    if (estado === 'baja_solicitada') {
        return <Badge className="bg-mspas-amber text-neutral-900">{etiqueta}</Badge>;
    }

    return <Badge variant="destructive">{etiqueta}</Badge>;
}
