import { AlertaEstado } from '@/components/alerta-estado';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Building2, Info, LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Unidad {
    id: number;
    padre_id: number | null;
    padre: string | null;
    codigo: string;
    nombre: string;
    tipo: string;
    tipo_legible: string;
    municipio: string | null;
    departamento: string | null;
    activo: boolean;
    bienes: number;
    empleados: number;
}

interface Props {
    unidades: Unidad[];
    tipos: Record<string, string>;
}

const SIN_PADRE = 'ninguna';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Unidades de servicio', href: '/inventario/unidades' },
];

export default function UnidadesIndex({ unidades, tipos }: Props) {
    const { puede } = usePermisos();
    const [editar, setEditar] = useState<Unidad | null>(null);
    const [crear, setCrear] = useState(false);

    /** Nivel de anidamiento, para mostrar la jerarquía con sangría. */
    const nivelDe = (unidad: Unidad): number => {
        let nivel = 0;
        let actual = unidad;

        while (actual.padre_id) {
            const padre = unidades.find((u) => u.id === actual.padre_id);
            if (!padre) break;
            nivel++;
            actual = padre;
        }

        return nivel;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Unidades de servicio" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">Unidades de servicio</h1>
                        <p className="text-muted-foreground text-sm">
                            Direcciones de área, distritos, centros y puestos de salud.
                        </p>
                    </div>

                    {puede('unidades.crear') && (
                        <Button onClick={() => setCrear(true)}>
                            <Plus className="size-4" />
                            Nueva unidad
                        </Button>
                    )}
                </div>

                <AlertaEstado />

                <div className="border-border text-muted-foreground flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    La jerarquía permite pedir reportes por nivel: al consultar un distrito se incluyen también los
                    bienes de sus puestos de salud.
                </div>

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Unidad</th>
                                <th className="px-4 py-3 font-medium">Tipo</th>
                                <th className="px-4 py-3 font-medium">Ubicación</th>
                                <th className="px-4 py-3 text-right font-medium">Bienes</th>
                                <th className="px-4 py-3 text-right font-medium">Empleados</th>
                                <th className="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {unidades.map((unidad) => (
                                <tr key={unidad.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <div style={{ paddingLeft: `${nivelDe(unidad) * 1.5}rem` }}>
                                            <p className="text-foreground flex items-center gap-2 font-medium">
                                                {nivelDe(unidad) > 0 && (
                                                    <span className="text-muted-foreground">└</span>
                                                )}
                                                {unidad.nombre}
                                                {!unidad.activo && (
                                                    <Badge variant="secondary" className="text-xs">
                                                        Inactiva
                                                    </Badge>
                                                )}
                                            </p>
                                            <p className="text-muted-foreground font-mono text-xs">{unidad.codigo}</p>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">{unidad.tipo_legible}</Badge>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {[unidad.municipio, unidad.departamento].filter(Boolean).join(', ') || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{unidad.bienes}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{unidad.empleados}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            {puede('unidades.editar') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Editar"
                                                    onClick={() => setEditar(unidad)}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}

                                            {puede('unidades.eliminar') &&
                                                unidad.bienes === 0 &&
                                                unidad.empleados === 0 && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Eliminar"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => {
                                                            if (window.confirm(`¿Eliminar ${unidad.nombre}?`)) {
                                                                router.delete(`/inventario/unidades/${unidad.id}`, {
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
                            ))}

                            {unidades.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-10 text-center">
                                        No hay unidades registradas todavía.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {(crear || editar) && (
                <FormularioUnidad
                    unidad={editar}
                    unidades={unidades}
                    tipos={tipos}
                    onCerrar={() => {
                        setCrear(false);
                        setEditar(null);
                    }}
                />
            )}
        </AppLayout>
    );
}

function FormularioUnidad({
    unidad,
    unidades,
    tipos,
    onCerrar,
}: {
    unidad: Unidad | null;
    unidades: Unidad[];
    tipos: Record<string, string>;
    onCerrar: () => void;
}) {
    const editando = unidad !== null;

    const { data, setData, post, put, transform, processing, errors } = useForm({
        codigo: unidad?.codigo ?? '',
        nombre: unidad?.nombre ?? '',
        tipo: unidad?.tipo ?? 'puesto_salud',
        padre_id: unidad?.padre_id ? String(unidad.padre_id) : SIN_PADRE,
        municipio: unidad?.municipio ?? '',
        departamento: unidad?.departamento ?? '',
        activo: unidad?.activo ?? true,
    });

    const enviar: FormEventHandler = (e) => {
        e.preventDefault();

        // El selector usa un valor centinela porque Radix no admite "".
        transform((datos) => ({
            ...datos,
            padre_id: datos.padre_id === SIN_PADRE ? null : datos.padre_id,
        }));

        const opciones = { preserveScroll: true, onSuccess: onCerrar };

        if (editando) {
            put(`/inventario/unidades/${unidad.id}`, opciones);
        } else {
            post('/inventario/unidades', opciones);
        }
    };

    // Al editar, una unidad no puede ser su propia superior.
    const posiblesPadres = unidades.filter((u) => u.id !== unidad?.id);

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Building2 className="size-5" />
                        {editando ? `Editar ${unidad.nombre}` : 'Nueva unidad de servicio'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={enviar} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
                        <div className="grid gap-2">
                            <Label htmlFor="codigo">Código</Label>
                            <Input
                                id="codigo"
                                className="font-mono"
                                placeholder="211-CHI"
                                value={data.codigo}
                                onChange={(e) => setData('codigo', e.target.value.toUpperCase())}
                                required
                            />
                            <InputError message={errors.codigo} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input
                                id="nombre"
                                placeholder="Puesto de Salud de Chinimabe"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                required
                            />
                            <InputError message={errors.nombre} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="tipo">Tipo de unidad</Label>
                        <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)}>
                            <SelectTrigger id="tipo">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(tipos).map(([clave, etiqueta]) => (
                                    <SelectItem key={clave} value={clave}>
                                        {etiqueta}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.tipo} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="padre_id">Depende de</Label>
                        <Select value={data.padre_id} onValueChange={(v) => setData('padre_id', v)}>
                            <SelectTrigger id="padre_id">
                                <SelectValue placeholder="Ninguna (es el nivel más alto)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={SIN_PADRE}>Ninguna (es el nivel más alto)</SelectItem>
                                {posiblesPadres.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.codigo} · {u.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.padre_id} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="municipio">Municipio</Label>
                            <Input
                                id="municipio"
                                value={data.municipio}
                                onChange={(e) => setData('municipio', e.target.value)}
                            />
                            <InputError message={errors.municipio} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="departamento">Departamento</Label>
                            <Input
                                id="departamento"
                                value={data.departamento}
                                onChange={(e) => setData('departamento', e.target.value)}
                            />
                            <InputError message={errors.departamento} />
                        </div>
                    </div>

                    {editando && (
                        <div className="grid gap-2">
                            <Label htmlFor="activo">Estado</Label>
                            <Select
                                value={data.activo ? 'activo' : 'inactivo'}
                                onValueChange={(v) => setData('activo', v === 'activo')}
                            >
                                <SelectTrigger id="activo">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="activo">Activa</SelectItem>
                                    <SelectItem value="inactivo">Inactiva</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {editando ? 'Guardar cambios' : 'Crear unidad'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
