import { AlertaEstado } from '@/components/alerta-estado';
import InputError from '@/components/input-error';
import { Paginacion } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginado } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, LoaderCircle, Plus, Printer, Search, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface TarjetaFila {
    id: number;
    numero: string | null;
    version: number;
    estado: string;
    empleado: string | null;
    cargo: string | null;
    unidad: string | null;
    bienes: number;
    saldo: number;
    fecha_apertura: string | null;
    pendientes_impresion: number;
}

interface Props {
    tarjetas: Paginado<TarjetaFila>;
    filtros: { buscar: string; unidad: number | null; reemplazadas: boolean };
    unidades: { id: number; codigo: string; nombre: string }[];
    empleadosSinTarjeta: { id: number; nombre: string; cargo: string | null; unidad: string | null }[];
}

const TODAS = 'todas';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Tarjetas de responsabilidad', href: '/inventario/tarjetas' },
];

const quetzales = (v: number) =>
    'Q ' + v.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function TarjetasIndex({ tarjetas, filtros, unidades, empleadosSinTarjeta }: Props) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar);
    const [abriendo, setAbriendo] = useState(false);

    const filtrar = (cambios: Record<string, string | number>) => {
        router.get(
            '/inventario/tarjetas',
            {
                buscar,
                unidad: filtros.unidad ?? '',
                reemplazadas: filtros.reemplazadas ? 1 : '',
                ...cambios,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tarjetas de responsabilidad" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">Tarjetas de responsabilidad</h1>
                        <p className="text-muted-foreground text-sm">
                            Una por empleado, en el formato de la Contraloría General de Cuentas.
                        </p>
                    </div>

                    {puede('tarjetas.crear') && empleadosSinTarjeta.length > 0 && (
                        <Button onClick={() => setAbriendo(true)}>
                            <Plus className="size-4" />
                            Abrir tarjeta
                        </Button>
                    )}
                </div>

                <AlertaEstado />

                <div className="flex flex-wrap items-end gap-3">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            filtrar({});
                        }}
                        className="flex min-w-[16rem] flex-1 gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                className="pl-10"
                                placeholder="Buscar por nombre del empleado"
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Buscar
                        </Button>
                    </form>

                    <Select
                        value={filtros.unidad ? String(filtros.unidad) : TODAS}
                        onValueChange={(v) => filtrar({ unidad: v === TODAS ? '' : v })}
                    >
                        <SelectTrigger className="w-60">
                            <SelectValue placeholder="Todas las unidades" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODAS}>Todas las unidades</SelectItem>
                            {unidades.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <label className="border-border bg-card flex h-9 cursor-pointer items-center gap-2 rounded-md border px-3">
                        <Checkbox
                            checked={filtros.reemplazadas}
                            onClick={() => filtrar({ reemplazadas: filtros.reemplazadas ? '' : 1 })}
                        />
                        <span className="text-sm">Ver versiones anteriores</span>
                    </label>

                    {(filtros.buscar !== '' || filtros.unidad !== null || filtros.reemplazadas) && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setBuscar('');
                                router.get('/inventario/tarjetas', {}, { preserveState: false });
                            }}
                        >
                            <X className="size-4" />
                            Limpiar
                        </Button>
                    )}
                </div>

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Empleado</th>
                                <th className="px-4 py-3 font-medium">Unidad</th>
                                <th className="px-4 py-3 font-medium">Tarjeta</th>
                                <th className="px-4 py-3 text-right font-medium">Bienes</th>
                                <th className="px-4 py-3 text-right font-medium">Saldo</th>
                                <th className="px-4 py-3 font-medium">Impresión</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {tarjetas.data.map((t) => (
                                <tr
                                    key={t.id}
                                    className="hover:bg-muted/30 cursor-pointer"
                                    onClick={() => router.visit(`/inventario/tarjetas/${t.id}`)}
                                >
                                    <td className="px-4 py-3">
                                        <p className="text-foreground font-medium">{t.empleado}</p>
                                        <p className="text-muted-foreground text-xs">{t.cargo}</p>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{t.unidad}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="font-medium">{t.numero ?? '—'}</span>
                                            {t.version > 1 && (
                                                <Badge variant="secondary" className="text-xs">
                                                    v{t.version}
                                                </Badge>
                                            )}
                                            {t.estado === 'reemplazada' && (
                                                <Badge variant="outline" className="text-xs">
                                                    Reemplazada
                                                </Badge>
                                            )}
                                        </div>
                                        {t.fecha_apertura && (
                                            <p className="text-muted-foreground text-xs">
                                                Abierta el {t.fecha_apertura}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{t.bienes}</td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                        {quetzales(t.saldo)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {t.pendientes_impresion > 0 ? (
                                            <Badge className="bg-mspas-amber gap-1 text-neutral-900">
                                                <Printer className="size-3" />
                                                {t.pendientes_impresion} sin imprimir
                                            </Badge>
                                        ) : t.bienes > 0 ? (
                                            <Badge className="bg-mspas-teal text-white">Al día</Badge>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">Vacía</span>
                                        )}
                                    </td>
                                </tr>
                            ))}

                            {tarjetas.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center">
                                        <FileText className="mx-auto mb-3 size-8 opacity-40" />
                                        No hay tarjetas con ese criterio.
                                        {empleadosSinTarjeta.length > 0 && (
                                            <span className="mt-1 block text-xs">
                                                Hay {empleadosSinTarjeta.length} empleado(s) sin tarjeta abierta.
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Paginacion pagina={tarjetas} />
            </div>

            {abriendo && (
                <DialogAbrirTarjeta empleados={empleadosSinTarjeta} onCerrar={() => setAbriendo(false)} />
            )}
        </AppLayout>
    );
}

function DialogAbrirTarjeta({
    empleados,
    onCerrar,
}: {
    empleados: { id: number; nombre: string; cargo: string | null; unidad: string | null }[];
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        empleado_id: '',
        numero: '',
        fecha_apertura: new Date().toISOString().slice(0, 10),
    });

    const enviar: FormEventHandler = (e) => {
        e.preventDefault();
        post('/inventario/tarjetas');
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Abrir tarjeta de responsabilidad</DialogTitle>
                    <DialogDescription>
                        Se registran una sola vez los datos del encabezado. Después se van agregando los bienes
                        buscándolos por código o por nombre.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={enviar} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="empleado_id">Empleado</Label>
                        <Select value={data.empleado_id} onValueChange={(v) => setData('empleado_id', v)}>
                            <SelectTrigger id="empleado_id">
                                <SelectValue placeholder="Seleccione al empleado" />
                            </SelectTrigger>
                            <SelectContent>
                                {empleados.map((e) => (
                                    <SelectItem key={e.id} value={String(e.id)}>
                                        {e.nombre}
                                        {e.cargo ? ` · ${e.cargo}` : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.empleado_id} />
                        <p className="text-muted-foreground text-xs">
                            Solo aparecen los empleados que no tienen una tarjeta abierta.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="numero">Número de tarjeta</Label>
                            <Input
                                id="numero"
                                placeholder="No. 69"
                                value={data.numero}
                                onChange={(e) => setData('numero', e.target.value)}
                            />
                            <InputError message={errors.numero} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="fecha_apertura">Fecha de apertura</Label>
                            <Input
                                id="fecha_apertura"
                                type="date"
                                value={data.fecha_apertura}
                                onChange={(e) => setData('fecha_apertura', e.target.value)}
                            />
                            <InputError message={errors.fecha_apertura} />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing || !data.empleado_id}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            Abrir tarjeta
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
