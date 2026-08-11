import { AlertaEstado } from '@/components/alerta-estado';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Info, LoaderCircle, Pencil, Plus, Trash2, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Cuenta {
    id: number;
    codigo: string;
    nombre: string;
    orden: number;
    activo: boolean;
    bienes: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Cuentas', href: '/inventario/cuentas' },
];

export default function CuentasIndex({ renglones }: { renglones: Cuenta[] }) {
    const { puede } = usePermisos();
    const [editando, setEditando] = useState<number | null>(null);

    const alta = useForm({ codigo: '', nombre: '', orden: '' });

    const crear: FormEventHandler = (e) => {
        e.preventDefault();
        alta.post('/inventario/cuentas', {
            preserveScroll: true,
            onSuccess: () => alta.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuentas" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-foreground text-xl font-semibold">Cuentas del inventario</h1>
                    <p className="text-muted-foreground text-sm">
                        Renglones presupuestarios con los que se clasifican los bienes.
                    </p>
                </div>

                <AlertaEstado />

                <div className="border-border text-muted-foreground flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    En la tarjeta de responsabilidad se imprime únicamente el número de cuenta.
                </div>

                {puede('renglones.crear') && (
                    <form onSubmit={crear} className="border-border bg-card grid gap-4 rounded-xl border p-6">
                        <p className="text-foreground font-medium">Agregar cuenta</p>

                        <div className="grid gap-4 sm:grid-cols-[10rem_1fr_7rem_auto] sm:items-start">
                            <div className="grid gap-2">
                                <Label htmlFor="codigo">Código</Label>
                                <Input
                                    id="codigo"
                                    className="font-mono"
                                    placeholder="1232.03"
                                    value={alta.data.codigo}
                                    onChange={(e) => alta.setData('codigo', e.target.value)}
                                    required
                                />
                                <InputError message={alta.errors.codigo} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="nombre">Nombre</Label>
                                <Input
                                    id="nombre"
                                    placeholder="MOBILIARIO Y EQUIPO DE OFICINA"
                                    value={alta.data.nombre}
                                    onChange={(e) => alta.setData('nombre', e.target.value.toUpperCase())}
                                    required
                                />
                                <InputError message={alta.errors.nombre} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="orden">Orden</Label>
                                <Input
                                    id="orden"
                                    type="number"
                                    min={0}
                                    placeholder="80"
                                    value={alta.data.orden}
                                    onChange={(e) => alta.setData('orden', e.target.value)}
                                />
                                <InputError message={alta.errors.orden} />
                            </div>

                            <Button type="submit" className="sm:mt-8" disabled={alta.processing}>
                                {alta.processing ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <Plus className="size-4" />
                                )}
                                Agregar
                            </Button>
                        </div>
                    </form>
                )}

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Código</th>
                                <th className="px-4 py-3 font-medium">Nombre</th>
                                <th className="px-4 py-3 text-right font-medium">Bienes</th>
                                <th className="px-4 py-3 font-medium">Estado</th>
                                <th className="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {renglones.map((cuenta) =>
                                editando === cuenta.id ? (
                                    <FilaEdicion key={cuenta.id} cuenta={cuenta} onCerrar={() => setEditando(null)} />
                                ) : (
                                    <tr key={cuenta.id} className="hover:bg-muted/30">
                                        <td className="text-foreground px-4 py-3 font-mono font-medium">
                                            {cuenta.codigo}
                                        </td>
                                        <td className="px-4 py-3">{cuenta.nombre}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">{cuenta.bienes}</td>
                                        <td className="px-4 py-3">
                                            {cuenta.activo ? (
                                                <Badge className="bg-mspas-teal text-white">Activa</Badge>
                                            ) : (
                                                <Badge variant="secondary">Inactiva</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                {puede('renglones.editar') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Editar"
                                                        onClick={() => setEditando(cuenta.id)}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                )}

                                                {puede('renglones.eliminar') && cuenta.bienes === 0 && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Eliminar"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => {
                                                            if (window.confirm(`¿Eliminar la cuenta ${cuenta.codigo}?`)) {
                                                                router.delete(`/inventario/cuentas/${cuenta.id}`, {
                                                                    preserveScroll: true,
                                                                });
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ),
                            )}

                            {renglones.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-4 py-10 text-center">
                                        No hay cuentas registradas todavía.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function FilaEdicion({ cuenta, onCerrar }: { cuenta: Cuenta; onCerrar: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        codigo: cuenta.codigo,
        nombre: cuenta.nombre,
        orden: String(cuenta.orden),
        activo: cuenta.activo,
    });

    const guardar = () => {
        put(`/inventario/cuentas/${cuenta.id}`, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <tr className="bg-accent/40">
            <td className="px-4 py-3">
                <Input
                    className="font-mono"
                    value={data.codigo}
                    onChange={(e) => setData('codigo', e.target.value)}
                />
                <InputError message={errors.codigo} />
            </td>
            <td className="px-4 py-3">
                <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value.toUpperCase())} />
                <InputError message={errors.nombre} />
            </td>
            <td className="px-4 py-3">
                <Input
                    type="number"
                    min={0}
                    className="w-20 text-right"
                    value={data.orden}
                    onChange={(e) => setData('orden', e.target.value)}
                />
            </td>
            <td className="px-4 py-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setData('activo', !data.activo)}
                >
                    {data.activo ? 'Activa' : 'Inactiva'}
                </Button>
            </td>
            <td className="px-4 py-3">
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" title="Guardar" onClick={guardar} disabled={processing}>
                        {processing ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Check className="text-mspas-teal size-4" />
                        )}
                    </Button>
                    <Button variant="ghost" size="icon" title="Cancelar" onClick={onCerrar}>
                        <X className="size-4" />
                    </Button>
                </div>
            </td>
        </tr>
    );
}
