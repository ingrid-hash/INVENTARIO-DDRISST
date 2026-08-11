import { AlertaEstado } from '@/components/alerta-estado';
import InputError from '@/components/input-error';
import { Paginacion } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginado } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { IdCard, LoaderCircle, Pencil, Plus, Search, Trash2, UserPlus, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Empleado {
    id: number;
    nombre_completo: string;
    dpi: string | null;
    cargo: string | null;
    area_trabajo: string | null;
    unidad: string | null;
    unidad_servicio_id: number;
    activo: boolean;
    bienes_a_cargo: number;
    tarjeta: { id: number; numero: string | null; version: number; saldo: number } | null;
}

interface Props {
    empleados: Paginado<Empleado>;
    filtros: { buscar: string; unidad: number | null };
    unidades: { id: number; codigo: string; nombre: string }[];
}

const TODAS = 'todas';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Empleados', href: '/inventario/empleados' },
];

const quetzales = (v: number) =>
    'Q ' + v.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function EmpleadosIndex({ empleados, filtros, unidades }: Props) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar);
    const [crear, setCrear] = useState(false);
    const [editar, setEditar] = useState<Empleado | null>(null);

    const filtrar = (cambios: Record<string, string | number>) => {
        router.get(
            '/inventario/empleados',
            { buscar, unidad: filtros.unidad ?? '', ...cambios },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Empleados" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">Empleados</h1>
                        <p className="text-muted-foreground text-sm">
                            Personal que responde por los bienes. No son usuarios del sistema.
                        </p>
                    </div>

                    {puede('empleados.crear') && (
                        <Button onClick={() => setCrear(true)}>
                            <UserPlus className="size-4" />
                            Nuevo empleado
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
                                placeholder="Buscar por nombre, cargo o DPI"
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

                    {(filtros.buscar !== '' || filtros.unidad !== null) && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setBuscar('');
                                router.get('/inventario/empleados', {}, { preserveState: false });
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
                                <th className="px-4 py-3 text-right font-medium">Bienes</th>
                                <th className="px-4 py-3 font-medium">Tarjeta</th>
                                <th className="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {empleados.data.map((empleado) => (
                                <tr key={empleado.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <p className="text-foreground flex items-center gap-2 font-medium">
                                            {empleado.nombre_completo}
                                            {!empleado.activo && (
                                                <Badge variant="secondary" className="text-xs">
                                                    Inactivo
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {[empleado.cargo, empleado.area_trabajo].filter(Boolean).join(' · ') ||
                                                'Sin cargo registrado'}
                                        </p>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{empleado.unidad}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{empleado.bienes_a_cargo}</td>
                                    <td className="px-4 py-3">
                                        {empleado.tarjeta ? (
                                            <Link
                                                href={`/inventario/tarjetas/${empleado.tarjeta.id}`}
                                                className="text-mspas-cyan hover:underline"
                                            >
                                                <span className="font-medium">
                                                    {empleado.tarjeta.numero ?? 'Tarjeta'}
                                                </span>
                                                {empleado.tarjeta.version > 1 && (
                                                    <span className="text-muted-foreground ml-1 text-xs">
                                                        v{empleado.tarjeta.version}
                                                    </span>
                                                )}
                                                <span className="text-muted-foreground block text-xs tabular-nums">
                                                    {quetzales(empleado.tarjeta.saldo)}
                                                </span>
                                            </Link>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">Sin tarjeta</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            {puede('empleados.editar') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Editar"
                                                    onClick={() => setEditar(empleado)}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}

                                            {puede('empleados.eliminar') && empleado.bienes_a_cargo === 0 && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Eliminar"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        if (window.confirm(`¿Eliminar a ${empleado.nombre_completo}?`)) {
                                                            router.delete(`/inventario/empleados/${empleado.id}`, {
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

                            {empleados.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-4 py-12 text-center">
                                        <IdCard className="mx-auto mb-3 size-8 opacity-40" />
                                        No hay empleados registrados con ese criterio.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Paginacion pagina={empleados} />
            </div>

            {(crear || editar) && (
                <FormularioEmpleado
                    empleado={editar}
                    unidades={unidades}
                    onCerrar={() => {
                        setCrear(false);
                        setEditar(null);
                    }}
                />
            )}
        </AppLayout>
    );
}

function FormularioEmpleado({
    empleado,
    unidades,
    onCerrar,
}: {
    empleado: Empleado | null;
    unidades: { id: number; codigo: string; nombre: string }[];
    onCerrar: () => void;
}) {
    const editando = empleado !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        unidad_servicio_id: empleado ? String(empleado.unidad_servicio_id) : '',
        nombre_completo: empleado?.nombre_completo ?? '',
        dpi: empleado?.dpi ?? '',
        cargo: empleado?.cargo ?? '',
        area_trabajo: empleado?.area_trabajo ?? '',
        activo: empleado?.activo ?? true,
        confirmar_parecido: false as boolean,
    });

    // El backend avisa cuando ya existe alguien con un nombre muy parecido, para
    // no partir en dos la tarjeta de la misma persona.
    const avisoParecido = errors.nombre_completo?.includes('parecido') ?? false;

    const enviar: FormEventHandler = (e) => {
        e.preventDefault();

        const opciones = { preserveScroll: true, onSuccess: onCerrar };

        if (editando) {
            put(`/inventario/empleados/${empleado.id}`, opciones);
        } else {
            post('/inventario/empleados', opciones);
        }
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editando ? `Editar a ${empleado.nombre_completo}` : 'Nuevo empleado'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={enviar} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre_completo">Nombre completo</Label>
                        <Input
                            id="nombre_completo"
                            placeholder="BLANCA AZUCENA RACANCOJ MEJIA"
                            value={data.nombre_completo}
                            onChange={(e) => setData('nombre_completo', e.target.value.toUpperCase())}
                            required
                        />
                        <InputError message={errors.nombre_completo} />

                        {avisoParecido && (
                            <label className="border-mspas-amber/40 bg-mspas-amber/10 flex cursor-pointer items-start gap-2.5 rounded-lg border p-3">
                                <Checkbox
                                    checked={data.confirmar_parecido}
                                    onClick={() => setData('confirmar_parecido', !data.confirmar_parecido)}
                                    className="mt-0.5"
                                />
                                <span className="text-foreground/80 text-xs leading-relaxed">
                                    Confirmo que es una persona distinta y quiero registrarla igual.
                                </span>
                            </label>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="unidad_servicio_id">Unidad de servicio</Label>
                        <Select
                            value={data.unidad_servicio_id}
                            onValueChange={(v) => setData('unidad_servicio_id', v)}
                        >
                            <SelectTrigger id="unidad_servicio_id">
                                <SelectValue placeholder="Seleccione la unidad" />
                            </SelectTrigger>
                            <SelectContent>
                                {unidades.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.unidad_servicio_id} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="cargo">Cargo</Label>
                            <Input
                                id="cargo"
                                placeholder="PARAMEDICO I"
                                value={data.cargo}
                                onChange={(e) => setData('cargo', e.target.value.toUpperCase())}
                            />
                            <InputError message={errors.cargo} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="area_trabajo">Área de trabajo</Label>
                            <Input
                                id="area_trabajo"
                                placeholder="ENFERMERIA"
                                value={data.area_trabajo}
                                onChange={(e) => setData('area_trabajo', e.target.value.toUpperCase())}
                            />
                            <InputError message={errors.area_trabajo} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="dpi">DPI (opcional)</Label>
                        <Input
                            id="dpi"
                            className="font-mono"
                            maxLength={13}
                            placeholder="13 dígitos"
                            value={data.dpi}
                            onChange={(e) => setData('dpi', e.target.value.replace(/\D/g, ''))}
                        />
                        <InputError message={errors.dpi} />
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
                                    <SelectItem value="activo">Activo</SelectItem>
                                    <SelectItem value="inactivo">Inactivo</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Plus className="size-4" />
                            )}
                            {editando ? 'Guardar cambios' : 'Registrar empleado'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
