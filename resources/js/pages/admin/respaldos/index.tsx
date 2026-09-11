import { AlertaEstado } from '@/components/alerta-estado';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, DatabaseBackup, Download, LoaderCircle, Trash2 } from 'lucide-react';

interface Respaldo {
    nombre: string;
    bytes: number;
    tamano: string;
    generado_en: string;
}

interface Props {
    respaldos: Respaldo[];
    disponible: boolean;
    base_datos: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '/admin/usuarios' },
    { title: 'Respaldos', href: '/admin/respaldos' },
];

export default function Respaldos({ respaldos, disponible, base_datos }: Props) {
    const { puede } = usePermisos();
    const { data, setData, post, processing, errors, reset } = useForm({ nota: '' });

    const generar = () =>
        post('/admin/respaldos', {
            preserveScroll: true,
            onSuccess: () => reset('nota'),
        });

    const eliminar = (respaldo: Respaldo) => {
        if (
            !confirm(
                `¿Eliminar el respaldo ${respaldo.nombre}? Si es la única copia que tiene, ` +
                    'esa información no se puede recuperar.',
            )
        ) {
            return;
        }

        router.delete(`/admin/respaldos/${respaldo.nombre}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Respaldos de la base de datos" />

            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-semibold">
                        <DatabaseBackup className="size-5" />
                        Respaldo de la información
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Copia completa de la base <span className="font-mono">{base_datos}</span> de este
                        equipo.
                    </p>
                </div>

                <AlertaEstado />

                {!disponible && (
                    <div className="flex gap-3 rounded-lg border border-amber-500/60 bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                        <AlertTriangle className="size-5 shrink-0" />
                        <div>
                            <p className="font-medium">No se puede generar el respaldo en este equipo.</p>
                            <p className="mt-1">
                                Comuníquese con el encargado de informática: falta configurar la ruta de
                                <span className="font-mono"> pg_dump</span>.
                            </p>
                        </div>
                    </div>
                )}

                <div className="flex gap-3 rounded-lg border p-4 text-sm">
                    <AlertTriangle className="text-muted-foreground size-5 shrink-0" />
                    <p className="text-muted-foreground">
                        <strong className="text-foreground">Descargue el respaldo y guárdelo en una memoria
                        o en otra computadora.</strong>{' '}
                        Contiene todos los datos del sistema, incluidas las cuentas de usuario.
                    </p>
                </div>

                {puede('respaldos.crear') && disponible && (
                    <div className="bg-card rounded-lg border p-4">
                        <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                            <div className="grid gap-2">
                                <Label htmlFor="nota">Nota (opcional)</Label>
                                <Input
                                    id="nota"
                                    placeholder="Antes de la importación de Momostenango"
                                    value={data.nota}
                                    onChange={(e) => setData('nota', e.target.value)}
                                />
                                <InputError message={errors.nota} />
                                <InputError message={(errors as Record<string, string>).respaldo} />
                            </div>

                            <Button onClick={generar} disabled={processing}>
                                {processing ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <DatabaseBackup className="size-4" />
                                )}
                                {processing ? 'Generando…' : 'Generar respaldo'}
                            </Button>
                        </div>
                    </div>
                )}

                <div className="bg-card overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-xs uppercase">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">Archivo</th>
                                <th className="px-3 py-2 text-left font-medium">Generado</th>
                                <th className="px-3 py-2 text-right font-medium">Tamaño</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {respaldos.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground px-3 py-10 text-center">
                                        Todavía no hay ningún respaldo guardado.
                                    </td>
                                </tr>
                            )}

                            {respaldos.map((r) => (
                                <tr key={r.nombre} className="border-t">
                                    <td className="px-3 py-2 font-mono text-xs">{r.nombre}</td>
                                    <td className="px-3 py-2 whitespace-nowrap">{r.generado_en}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{r.tamano}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="sm">
                                                <a href={`/admin/respaldos/${r.nombre}`}>
                                                    <Download className="size-4" />
                                                    Descargar
                                                </a>
                                            </Button>

                                            {puede('respaldos.crear') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:text-destructive size-8"
                                                    title="Eliminar"
                                                    onClick={() => eliminar(r)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="text-muted-foreground rounded-lg border p-4 text-sm">
                    <p className="text-foreground font-medium">Restaurar un respaldo</p>
                    <p className="mt-1">
                        Comuníquese con el encargado de informática. La restauración reemplaza toda la
                        información que tenga el sistema en este momento.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
