import { AlertaEstado } from '@/components/alerta-estado';
import { Paginacion } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginado } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileSpreadsheet, Info, Undo2 } from 'lucide-react';

interface Props {
    importacion: {
        id: number;
        archivo: string;
        hoja: string | null;
        tipo: string;
        unidad: string | null;
        usuario: string | null;
        leidas: number;
        importadas: number;
        rechazadas: number;
        estado: string;
        fecha: string | null;
        resumen: {
            encabezado?: Record<string, string | null>;
            renglones_detectados?: Record<string, string>;
            filas_descartadas?: number;
            empleado_id?: number | null;
            tarjeta_id?: number | null;
        } | null;
    };
    errores: Paginado<{
        fila: number;
        codigo: string | null;
        motivo_clave: string;
        motivo: string;
        descripcion: string | null;
    }>;
    resumenErrores: Record<string, number>;
}

const ETIQUETAS_MOTIVO: Record<string, string> = {
    sin_codigo: 'Sin código de inventario',
    sin_codigo_ya_cargado: 'Sin código, ya estaba cargado',
    codigo_duplicado: 'Código repetido',
    codigo_invalido: 'Código con formato inválido',
    bien_ya_asignado: 'El bien ya tiene responsable',
    error_base_datos: 'Error al guardar',
};

export default function DetalleImportacion({ importacion, errores, resumenErrores }: Props) {
    const { puede } = usePermisos();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Importar', href: '/inventario/importacion' },
        { title: importacion.archivo, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Importación · ${importacion.archivo}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <Button asChild variant="ghost" size="icon">
                            <Link href="/inventario/importacion">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-foreground flex flex-wrap items-center gap-2 text-xl font-semibold">
                                <FileSpreadsheet className="size-5" />
                                {importacion.archivo}
                                {importacion.estado === 'revertida' && (
                                    <Badge variant="destructive">Revertida</Badge>
                                )}
                            </h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Hoja «{importacion.hoja}» ·{' '}
                                {importacion.tipo === 'tarjeta' ? 'tarjeta de responsabilidad' : 'listado general'} ·{' '}
                                {importacion.unidad} · por {importacion.usuario} el {importacion.fecha}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {importacion.resumen?.tarjeta_id && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={`/inventario/tarjetas/${importacion.resumen.tarjeta_id}`}>
                                    Ver la tarjeta
                                </Link>
                            </Button>
                        )}

                        {puede('importaciones.revertir') && importacion.estado === 'confirmada' && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Se eliminarán los ${importacion.importadas} bien(es) que entraron con esta carga. ¿Continuar?`,
                                        )
                                    ) {
                                        router.post(`/inventario/importacion/${importacion.id}/revertir`);
                                    }
                                }}
                            >
                                <Undo2 className="size-4" />
                                Revertir la carga
                            </Button>
                        )}
                    </div>
                </div>

                <AlertaEstado />

                <div className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-4">
                    <Cifra etiqueta="Filas leídas del Excel" valor={importacion.leidas} />
                    <Cifra etiqueta="Bienes importados" valor={importacion.importadas} acento="teal" />
                    <Cifra
                        etiqueta="Filas rechazadas"
                        valor={importacion.rechazadas}
                        acento={importacion.rechazadas > 0 ? 'amber' : undefined}
                    />
                    <Cifra
                        etiqueta="Descartadas por formato"
                        valor={importacion.resumen?.filas_descartadas ?? 0}
                    />
                </div>

                {importacion.resumen?.encabezado?.nombre && (
                    <section className="border-border bg-card rounded-xl border p-4">
                        <p className="text-foreground text-sm font-medium">Encabezado leído del archivo</p>
                        <div className="mt-2 grid gap-1 text-sm sm:grid-cols-3">
                            {Object.entries(importacion.resumen.encabezado).map(
                                ([campo, valor]) =>
                                    valor && (
                                        <p key={campo}>
                                            <span className="text-muted-foreground text-xs">
                                                {campo.replace(/_/g, ' ')}:{' '}
                                            </span>
                                            {valor}
                                        </p>
                                    ),
                            )}
                        </div>
                    </section>
                )}

                {Object.keys(resumenErrores).length > 0 && (
                    <section className="border-border bg-card rounded-xl border p-4">
                        <p className="text-foreground text-sm font-medium">Por qué se rechazaron</p>
                        <div className="mt-3 grid gap-2">
                            {Object.entries(resumenErrores).map(([clave, total]) => (
                                <div key={clave} className="flex items-center gap-3 text-sm">
                                    <span className="w-56 shrink-0">{ETIQUETAS_MOTIVO[clave] ?? clave}</span>
                                    <div className="bg-muted h-4 flex-1 overflow-hidden rounded">
                                        <div
                                            className="bg-mspas-amber h-full"
                                            style={{
                                                width: `${Math.min(100, (total / Math.max(1, importacion.rechazadas)) * 100)}%`,
                                            }}
                                        />
                                    </div>
                                    <span className="w-12 shrink-0 text-right tabular-nums">{total}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {errores.data.length > 0 && (
                    <section className="border-border bg-card overflow-hidden rounded-xl border">
                        <div className="border-border border-b px-4 py-3">
                            <p className="text-foreground text-sm font-medium">Detalle de las filas rechazadas</p>
                            <p className="text-muted-foreground text-xs">
                                El número de fila corresponde al archivo de Excel, para poder corregirlo ahí.
                            </p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Fila</th>
                                        <th className="px-4 py-2 font-medium">Código</th>
                                        <th className="px-4 py-2 font-medium">Descripción en el archivo</th>
                                        <th className="px-4 py-2 font-medium">Motivo</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-border divide-y">
                                    {errores.data.map((e, i) => (
                                        <tr key={`${e.fila}-${i}`} className="hover:bg-muted/30">
                                            <td className="text-muted-foreground px-4 py-2 tabular-nums">{e.fila}</td>
                                            <td className="px-4 py-2 font-mono text-xs">{e.codigo ?? '—'}</td>
                                            <td className="text-muted-foreground max-w-md px-4 py-2">
                                                <span className="line-clamp-1">{e.descripcion ?? '—'}</span>
                                            </td>
                                            <td className="px-4 py-2">{e.motivo}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="border-border border-t p-3">
                            <Paginacion pagina={errores} />
                        </div>
                    </section>
                )}

                {importacion.rechazadas === 0 && (
                    <section className="border-mspas-teal bg-mspas-teal/10 flex items-center gap-2 rounded-lg border p-3 text-sm">
                        <Info className="text-mspas-teal size-4" />
                        La carga terminó sin filas rechazadas.
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function Cifra({ etiqueta, valor, acento }: { etiqueta: string; valor: number; acento?: 'teal' | 'amber' }) {
    const color =
        acento === 'teal' ? 'text-mspas-teal' : acento === 'amber' ? 'text-mspas-amber' : 'text-foreground';

    return (
        <div className="bg-card flex flex-col gap-1 p-4">
            <p className={`text-2xl font-semibold tabular-nums ${color}`}>{valor.toLocaleString('es-GT')}</p>
            <p className="text-muted-foreground text-xs">{etiqueta}</p>
        </div>
    );
}
