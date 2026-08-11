import { AlertaEstado } from '@/components/alerta-estado';
import InputError from '@/components/input-error';
import { Paginacion } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginado } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Info, LoaderCircle, Upload } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Fila {
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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Importar desde Excel', href: '/inventario/importacion' },
];

export default function ImportacionIndex({ importaciones }: { importaciones: Paginado<Fila> }) {
    const { puede } = usePermisos();

    const { data, setData, post, processing, errors, progress } = useForm<{ archivo: File | null }>({
        archivo: null,
    });

    const subir: FormEventHandler = (e) => {
        e.preventDefault();
        post('/inventario/importacion/subir', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Importar desde Excel" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-foreground text-xl font-semibold">Importar desde Excel</h1>
                    <p className="text-muted-foreground text-sm">
                        Carga de inventarios generales y de tarjetas de responsabilidad.
                    </p>
                </div>

                <AlertaEstado />

                {puede('importaciones.ejecutar') && (
                    <form
                        onSubmit={subir}
                        className="border-border bg-card grid gap-4 rounded-xl border border-dashed p-6"
                    >
                        <div className="flex items-start gap-3">
                            <div className="bg-mspas-cyan-light text-mspas-navy dark:bg-mspas-navy dark:text-mspas-cyan flex size-10 shrink-0 items-center justify-center rounded-lg">
                                <Upload className="size-5" />
                            </div>
                            <div>
                                <p className="text-foreground font-medium">Subir un archivo</p>
                                <p className="text-muted-foreground text-sm">
                                    Formatos .xlsx, .xls y .xlsm, hasta 20 MB. En el siguiente paso se elige la hoja y
                                    se revisa el mapeo de columnas antes de cargar nada.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-2 sm:max-w-lg">
                            <Label htmlFor="archivo">Archivo de Excel</Label>
                            <Input
                                id="archivo"
                                type="file"
                                accept=".xlsx,.xls,.xlsm"
                                onChange={(e) => setData('archivo', e.target.files?.[0] ?? null)}
                                required
                            />
                            <InputError message={errors.archivo} />
                        </div>

                        {progress && (
                            <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full sm:max-w-lg">
                                <div
                                    className="bg-mspas-cyan h-full transition-all"
                                    style={{ width: `${progress.percentage}%` }}
                                />
                            </div>
                        )}

                        <Button type="submit" className="justify-self-start" disabled={processing || !data.archivo}>
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <FileSpreadsheet className="size-4" />
                            )}
                            Leer el archivo
                        </Button>
                    </form>
                )}

                <div className="border-border text-muted-foreground flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    El sistema salta por su cuenta los encabezados repetidos por la paginación, los cortes VAN y
                    VIENEN, los bloques de firma y las filas de renglón presupuestario. Las filas que no pueda cargar
                    quedan en un reporte con su número de fila del Excel.
                </div>

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Archivo</th>
                                <th className="px-4 py-3 font-medium">Destino</th>
                                <th className="px-4 py-3 text-right font-medium">Importadas</th>
                                <th className="px-4 py-3 text-right font-medium">Rechazadas</th>
                                <th className="px-4 py-3 font-medium">Estado</th>
                                <th className="px-4 py-3 font-medium">Fecha</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {importaciones.data.map((i) => (
                                <tr key={i.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`/inventario/importacion/${i.id}`}
                                            className="text-mspas-cyan font-medium hover:underline"
                                        >
                                            {i.archivo}
                                        </Link>
                                        <p className="text-muted-foreground text-xs">
                                            {i.hoja} ·{' '}
                                            {i.tipo === 'tarjeta' ? 'tarjeta de responsabilidad' : 'listado general'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>{i.unidad}</p>
                                        <p className="text-muted-foreground text-xs">por {i.usuario}</p>
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">{i.importadas}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {i.rechazadas > 0 ? (
                                            <span className="text-mspas-amber font-medium">{i.rechazadas}</span>
                                        ) : (
                                            <span className="text-muted-foreground">0</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <EstadoImportacion estado={i.estado} />
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">{i.fecha}</td>
                                </tr>
                            ))}

                            {importaciones.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center">
                                        <FileSpreadsheet className="mx-auto mb-3 size-8 opacity-40" />
                                        Todavía no se ha importado ningún archivo.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Paginacion pagina={importaciones} />
            </div>
        </AppLayout>
    );
}

function EstadoImportacion({ estado }: { estado: string }) {
    if (estado === 'confirmada') {
        return <Badge className="bg-mspas-teal text-white">Confirmada</Badge>;
    }

    if (estado === 'revertida') {
        return <Badge variant="destructive">Revertida</Badge>;
    }

    return <Badge variant="secondary">Pendiente</Badge>;
}
