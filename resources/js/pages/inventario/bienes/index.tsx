import { AlertaEstado } from '@/components/alerta-estado';
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
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginado } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Filter, LoaderCircle, Pencil, Plus, Search, Tags, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface BienFila {
    id: number;
    codigo: string;
    codigo_provisional: boolean;
    descripcion: string;
    cantidad: number;
    precio_unitario: number;
    total: number;
    cuenta: string | null;
    unidad: string | null;
    estado: string;
    estado_legible: string;
    es_adicion: boolean;
    responsable: string | null;
    anio: number | null;
}

interface Filtros {
    buscar: string;
    unidad: number | null;
    cuenta: number | null;
    estado: string;
    movimiento: string;
    sin_cuenta: boolean;
    sin_asignar: boolean;
    provisionales: boolean;
}

interface Catalogos {
    unidades: { id: number; codigo: string; nombre: string }[];
    cuentas: { id: number; codigo: string; nombre: string }[];
    estados: Record<string, string>;
    movimientos: Record<string, string>;
}

interface Props {
    bienes: Paginado<BienFila>;
    filtros: Filtros;
    catalogos: Catalogos;
    resumen: {
        bienes: number;
        valor: number;
        sin_cuenta: number;
        sin_asignar: number;
        provisionales: number;
    };
}

const TODOS = 'todos';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Bienes', href: '/inventario/bienes' },
];

const quetzales = (valor: number) =>
    'Q ' + valor.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function BienesIndex({ bienes, filtros, catalogos, resumen }: Props) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar);
    const [seleccion, setSeleccion] = useState<number[]>([]);
    const [asignandoCuenta, setAsignandoCuenta] = useState(false);

    const filtrar = (cambios: Partial<Record<keyof Filtros, string | number | boolean | null>>) => {
        router.get(
            '/inventario/bienes',
            {
                buscar,
                unidad: filtros.unidad ?? '',
                cuenta: filtros.cuenta ?? '',
                estado: filtros.estado,
                movimiento: filtros.movimiento,
                sin_cuenta: filtros.sin_cuenta ? 1 : '',
                sin_asignar: filtros.sin_asignar ? 1 : '',
                provisionales: filtros.provisionales ? 1 : '',
                ...cambios,
            },
            { preserveState: true, replace: true },
        );
    };

    const hayFiltros =
        filtros.buscar !== '' ||
        filtros.unidad !== null ||
        filtros.cuenta !== null ||
        filtros.estado !== '' ||
        filtros.movimiento !== '' ||
        filtros.sin_cuenta ||
        filtros.sin_asignar ||
        filtros.provisionales;

    const alternar = (id: number) =>
        setSeleccion((actual) => (actual.includes(id) ? actual.filter((x) => x !== id) : [...actual, id]));

    const todosVisiblesSeleccionados =
        bienes.data.length > 0 && bienes.data.every((b) => seleccion.includes(b.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bienes" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">Bienes de inventario</h1>
                        <p className="text-muted-foreground text-sm">
                            Mobiliario, equipo médico y demás bienes no fungibles.
                        </p>
                    </div>

                    {puede('bienes.crear') && (
                        <Button asChild>
                            <Link href="/inventario/bienes-nuevo">
                                <Plus className="size-4" />
                                Nuevo bien
                            </Link>
                        </Button>
                    )}
                </div>

                <AlertaEstado />

                <div className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-5">
                    <Cifra etiqueta="Bienes en el filtro" valor={resumen.bienes.toLocaleString('es-GT')} />
                    <Cifra etiqueta="Valor del filtro" valor={quetzales(resumen.valor)} />
                    <Cifra
                        etiqueta="Sin cuenta asignada"
                        valor={resumen.sin_cuenta.toLocaleString('es-GT')}
                        acento={resumen.sin_cuenta > 0 ? 'amber' : undefined}
                        onClick={() => filtrar({ sin_cuenta: filtros.sin_cuenta ? '' : 1 })}
                        activo={filtros.sin_cuenta}
                    />
                    <Cifra
                        etiqueta="Con código provisional"
                        valor={resumen.provisionales.toLocaleString('es-GT')}
                        acento={resumen.provisionales > 0 ? 'amber' : undefined}
                        onClick={() => filtrar({ provisionales: filtros.provisionales ? '' : 1 })}
                        activo={filtros.provisionales}
                    />
                    <Cifra
                        etiqueta="Activos sin responsable"
                        valor={resumen.sin_asignar.toLocaleString('es-GT')}
                        acento={resumen.sin_asignar > 0 ? 'cyan' : undefined}
                        onClick={() => filtrar({ sin_asignar: filtros.sin_asignar ? '' : 1 })}
                        activo={filtros.sin_asignar}
                    />
                </div>

                <div className="border-border bg-card flex flex-wrap items-end gap-3 rounded-xl border p-4">
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
                                placeholder="Buscar por código o descripción"
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Buscar
                        </Button>
                    </form>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">Unidad de servicio</Label>
                        <Select
                            value={filtros.unidad ? String(filtros.unidad) : TODOS}
                            onValueChange={(v) => filtrar({ unidad: v === TODOS ? '' : v })}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Todas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todas las unidades</SelectItem>
                                {catalogos.unidades.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">Cuenta</Label>
                        <Select
                            value={filtros.cuenta ? String(filtros.cuenta) : TODOS}
                            onValueChange={(v) => filtrar({ cuenta: v === TODOS ? '' : v })}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Todas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todas</SelectItem>
                                {catalogos.cuentas.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.codigo}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">Estado</Label>
                        <Select
                            value={filtros.estado || TODOS}
                            onValueChange={(v) => filtrar({ estado: v === TODOS ? '' : v })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Todos" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todos</SelectItem>
                                {Object.entries(catalogos.estados).map(([clave, etiqueta]) => (
                                    <SelectItem key={clave} value={clave}>
                                        {etiqueta}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">Movimiento</Label>
                        <Select
                            value={filtros.movimiento || TODOS}
                            onValueChange={(v) => filtrar({ movimiento: v === TODOS ? '' : v })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Todos" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todos</SelectItem>
                                {Object.entries(catalogos.movimientos).map(([clave, etiqueta]) => (
                                    <SelectItem key={clave} value={clave}>
                                        {etiqueta}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {hayFiltros && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setBuscar('');
                                router.get('/inventario/bienes', {}, { preserveState: false });
                            }}
                        >
                            <X className="size-4" />
                            Limpiar
                        </Button>
                    )}
                </div>

                {seleccion.length > 0 && puede('bienes.asignar_cuenta') && (
                    <div className="border-mspas-cyan bg-mspas-cyan/10 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                        <p className="text-foreground text-sm">
                            <strong>{seleccion.length}</strong> bien(es) seleccionado(s)
                        </p>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => setSeleccion([])}>
                                Quitar selección
                            </Button>
                            <Button size="sm" onClick={() => setAsignandoCuenta(true)}>
                                <Tags className="size-4" />
                                Asignar cuenta
                            </Button>
                        </div>
                    </div>
                )}

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                {puede('bienes.asignar_cuenta') && (
                                    <th className="w-10 px-4 py-3">
                                        <Checkbox
                                            checked={todosVisiblesSeleccionados}
                                            onClick={() =>
                                                setSeleccion(
                                                    todosVisiblesSeleccionados
                                                        ? []
                                                        : bienes.data.map((b) => b.id),
                                                )
                                            }
                                            aria-label="Seleccionar todos los visibles"
                                        />
                                    </th>
                                )}
                                <th className="px-4 py-3 font-medium">Código</th>
                                <th className="px-4 py-3 font-medium">Descripción</th>
                                <th className="px-4 py-3 font-medium">Cuenta</th>
                                <th className="px-4 py-3 text-right font-medium">Valor</th>
                                <th className="px-4 py-3 font-medium">Responsable</th>
                                <th className="px-4 py-3 font-medium">Estado</th>
                                <th className="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {bienes.data.map((bien) => (
                                <tr key={bien.id} className="hover:bg-muted/30">
                                    {puede('bienes.asignar_cuenta') && (
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                checked={seleccion.includes(bien.id)}
                                                onClick={() => alternar(bien.id)}
                                                aria-label={`Seleccionar ${bien.codigo}`}
                                            />
                                        </td>
                                    )}
                                    <td className="px-4 py-3">
                                        <p
                                            className={cn(
                                                'font-mono font-medium',
                                                bien.codigo_provisional ? 'text-mspas-amber' : 'text-foreground',
                                            )}
                                            title={
                                                bien.codigo_provisional
                                                    ? 'Código provisional: el archivo no traía código. Edite el bien para poner el oficial.'
                                                    : undefined
                                            }
                                        >
                                            {bien.codigo}
                                        </p>
                                        {bien.codigo_provisional ? (
                                            <p className="text-mspas-amber text-xs">provisional</p>
                                        ) : (
                                            bien.anio && <p className="text-muted-foreground text-xs">{bien.anio}</p>
                                        )}
                                    </td>
                                    <td className="max-w-md px-4 py-3">
                                        <p className="text-foreground line-clamp-2">{bien.descripcion}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {bien.unidad}
                                            {bien.es_adicion && (
                                                <span className="text-mspas-cyan ml-1 font-medium">· Adición</span>
                                            )}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {bien.cuenta ? (
                                            <span className="font-mono text-xs">{bien.cuenta}</span>
                                        ) : (
                                            <Badge className="bg-mspas-amber text-xs text-neutral-900">
                                                Sin cuenta
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{quetzales(bien.total)}</td>
                                    <td className="px-4 py-3">
                                        {bien.responsable ?? (
                                            <span className="text-muted-foreground text-xs">Sin asignar</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <EstadoBien estado={bien.estado} etiqueta={bien.estado_legible} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon" title="Ver detalle">
                                                <Link href={`/inventario/bienes/${bien.id}`}>
                                                    <Eye className="size-4" />
                                                </Link>
                                            </Button>

                                            {puede('bienes.editar') && (
                                                <Button asChild variant="ghost" size="icon" title="Editar">
                                                    <Link href={`/inventario/bienes/${bien.id}/editar`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                            )}

                                            {puede('bienes.eliminar') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Eliminar"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        if (window.confirm(`¿Eliminar el bien ${bien.codigo}?`)) {
                                                            router.delete(`/inventario/bienes/${bien.id}`);
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

                            {bienes.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={puede('bienes.asignar_cuenta') ? 8 : 7}
                                        className="text-muted-foreground px-4 py-12 text-center"
                                    >
                                        <Filter className="mx-auto mb-3 size-8 opacity-40" />
                                        {hayFiltros
                                            ? 'Ningún bien coincide con el filtro.'
                                            : 'Todavía no hay bienes registrados. Puede agregarlos uno por uno o importarlos desde un archivo de Excel.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Paginacion pagina={bienes} />
            </div>

            {asignandoCuenta && (
                <DialogAsignarCuenta
                    cantidad={seleccion.length}
                    bienes={seleccion}
                    cuentas={catalogos.cuentas}
                    onCerrar={(limpiar) => {
                        setAsignandoCuenta(false);
                        if (limpiar) setSeleccion([]);
                    }}
                />
            )}
        </AppLayout>
    );
}

function Cifra({
    etiqueta,
    valor,
    acento,
    onClick,
    activo,
}: {
    etiqueta: string;
    valor: string;
    acento?: 'amber' | 'cyan';
    onClick?: () => void;
    activo?: boolean;
}) {
    const contenido = (
        <>
            <p
                className={cn(
                    'text-2xl font-semibold tabular-nums',
                    acento === 'amber' && 'text-mspas-amber',
                    acento === 'cyan' && 'text-mspas-cyan',
                    !acento && 'text-foreground',
                )}
            >
                {valor}
            </p>
            <p className="text-muted-foreground text-xs">{etiqueta}</p>
        </>
    );

    if (!onClick) {
        return <div className="bg-card flex flex-col gap-1 p-4">{contenido}</div>;
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'bg-card hover:bg-accent flex flex-col gap-1 p-4 text-left transition-colors',
                activo && 'ring-mspas-cyan ring-2 ring-inset',
            )}
        >
            {contenido}
        </button>
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

function DialogAsignarCuenta({
    cantidad,
    bienes,
    cuentas,
    onCerrar,
}: {
    cantidad: number;
    bienes: number[];
    cuentas: { id: number; codigo: string; nombre: string }[];
    onCerrar: (limpiar: boolean) => void;
}) {
    const [cuenta, setCuenta] = useState('');
    const [enviando, setEnviando] = useState(false);

    const asignar = () => {
        if (!cuenta) return;

        setEnviando(true);
        router.patch(
            '/inventario/bienes-cuenta',
            { bienes, renglon_id: Number(cuenta) },
            {
                preserveScroll: true,
                onSuccess: () => onCerrar(true),
                onFinish: () => setEnviando(false),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar(false)}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Asignar cuenta a {cantidad} bien(es)</DialogTitle>
                    <DialogDescription>
                        Completa el dato que los archivos de Excel no traen. No modifica la tarjeta de
                        responsabilidad: sirve para los reportes por cuenta.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="cuenta-lote">Cuenta</Label>
                    <Select value={cuenta} onValueChange={setCuenta}>
                        <SelectTrigger id="cuenta-lote">
                            <SelectValue placeholder="Seleccione la cuenta" />
                        </SelectTrigger>
                        <SelectContent>
                            {cuentas.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.codigo} · {c.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={() => onCerrar(false)}>
                        Cancelar
                    </Button>
                    <Button onClick={asignar} disabled={!cuenta || enviando}>
                        {enviando && <LoaderCircle className="size-4 animate-spin" />}
                        Asignar cuenta
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
