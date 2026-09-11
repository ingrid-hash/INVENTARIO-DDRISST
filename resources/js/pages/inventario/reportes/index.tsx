import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileSpreadsheet, Filter, Printer, Search, X } from 'lucide-react';
import { useState } from 'react';

/** Marca del Select para «sin filtrar»: no puede ser cadena vacía. */
const TODOS = 'todos';

interface Fila {
    id: number;
    codigo: string;
    codigo_provisional: boolean;
    descripcion: string;
    cantidad: number;
    precio_unitario: number;
    total: number;
    unidad: string | null;
    cuenta: string | null;
    tipo_movimiento: string;
    forma_adquisicion: string | null;
    programa: string | null;
    fecha_ingreso: string | null;
    estado: string;
}

interface Grupo {
    rotulo: string;
    cantidad: number;
    valor: number;
    bienes: Fila[];
}

interface Props {
    filtros: Record<string, string | number | null>;
    reporte: {
        grupos: Grupo[];
        total_bienes: number;
        total_cantidad: number;
        total_valor: number;
    };
    catalogos: {
        unidades: { id: number; codigo: string; nombre: string }[];
        cuentas: { id: number; codigo: string; nombre: string }[];
        agrupaciones: Record<string, string>;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventario', href: '/inventario/bienes' },
    { title: 'Reportes', href: '/inventario/reportes' },
];

const quetzales = (valor: number) =>
    `Q ${valor.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function Reportes({ filtros, reporte, catalogos }: Props) {
    const { puede } = usePermisos();

    const [form, setForm] = useState({
        unidad_servicio_id: filtros.unidad_servicio_id ? String(filtros.unidad_servicio_id) : TODOS,
        renglon_id: filtros.renglon_id ? String(filtros.renglon_id) : TODOS,
        tipo_movimiento: (filtros.tipo_movimiento as string) ?? TODOS,
        forma_adquisicion: (filtros.forma_adquisicion as string) ?? TODOS,
        estado: (filtros.estado as string) ?? TODOS,
        agrupar_por: (filtros.agrupar_por as string) ?? 'unidad',
        programa: (filtros.programa as string) ?? '',
        buscar: (filtros.buscar as string) ?? '',
        desde: (filtros.desde as string) ?? '',
        hasta: (filtros.hasta as string) ?? '',
    });

    /** Deja fuera los «todos» y los campos vacíos antes de ir al servidor. */
    const parametros = () =>
        Object.fromEntries(
            Object.entries(form).filter(([, valor]) => valor !== '' && valor !== TODOS),
        );

    const consultar = () =>
        router.get('/inventario/reportes', parametros(), { preserveState: true, replace: true });

    const limpiar = () => router.get('/inventario/reportes', {}, { preserveState: false });

    const enlace = (ruta: string) => {
        const query = new URLSearchParams(parametros() as Record<string, string>).toString();
        return query ? `${ruta}?${query}` : ruta;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reportes del inventario" />

            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Reportes del inventario</h1>
                        <p className="text-muted-foreground text-sm">
                            Un reporte general de los bienes.
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a href={enlace('/inventario/reportes/imprimir')} target="_blank" rel="noopener">
                                <Printer className="size-4" />
                                Imprimir o PDF
                            </a>
                        </Button>

                        {puede('reportes.exportar') && (
                            <Button asChild size="sm">
                                <a href={enlace('/inventario/reportes/excel')}>
                                    <FileSpreadsheet className="size-4" />
                                    Descargar Excel
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                {/* --- Filtros --- */}
                <div className="bg-card rounded-lg border p-4">
                    <div className="text-muted-foreground mb-3 flex items-center gap-2 text-sm font-medium">
                        <Filter className="size-4" />
                        Filtros
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Campo id="unidad" etiqueta="Unidad de servicio">
                            <Select
                                value={form.unidad_servicio_id}
                                onValueChange={(v) => setForm({ ...form, unidad_servicio_id: v })}
                            >
                                <SelectTrigger id="unidad">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS}>Todas las unidades</SelectItem>
                                    {catalogos.unidades.map((u) => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {u.codigo} · {u.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Campo>

                        <Campo id="cuenta" etiqueta="Cuenta presupuestaria">
                            <Select
                                value={form.renglon_id}
                                onValueChange={(v) => setForm({ ...form, renglon_id: v })}
                            >
                                <SelectTrigger id="cuenta">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS}>Todas las cuentas</SelectItem>
                                    {catalogos.cuentas.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.codigo} · {c.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Campo>

                        <Campo id="movimiento" etiqueta="Tipo de movimiento">
                            <Select
                                value={form.tipo_movimiento}
                                onValueChange={(v) => setForm({ ...form, tipo_movimiento: v })}
                            >
                                <SelectTrigger id="movimiento">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS}>Todos</SelectItem>
                                    <SelectItem value="apertura">Apertura de inventario</SelectItem>
                                    <SelectItem value="adicion">Adición</SelectItem>
                                </SelectContent>
                            </Select>
                        </Campo>

                        <Campo id="adquisicion" etiqueta="Forma de adquisición">
                            <Select
                                value={form.forma_adquisicion}
                                onValueChange={(v) => setForm({ ...form, forma_adquisicion: v })}
                            >
                                <SelectTrigger id="adquisicion">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS}>Todas</SelectItem>
                                    <SelectItem value="compra">Compra</SelectItem>
                                    <SelectItem value="donacion">Donación</SelectItem>
                                </SelectContent>
                            </Select>
                        </Campo>

                        <Campo id="buscar" etiqueta="Contiene (marca, modelo, código)">
                            <div className="relative">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
                                <Input
                                    id="buscar"
                                    className="pl-8"
                                    placeholder="SKY, HP, refrigeradora…"
                                    value={form.buscar}
                                    onChange={(e) => setForm({ ...form, buscar: e.target.value })}
                                    onKeyDown={(e) => e.key === 'Enter' && consultar()}
                                />
                            </div>
                        </Campo>

                        <Campo id="programa" etiqueta="Programa donante">
                            <Input
                                id="programa"
                                placeholder="CRECER SANO, VIH…"
                                value={form.programa}
                                onChange={(e) => setForm({ ...form, programa: e.target.value })}
                                onKeyDown={(e) => e.key === 'Enter' && consultar()}
                            />
                        </Campo>

                        <Campo id="desde" etiqueta="Ingreso desde">
                            <Input
                                id="desde"
                                type="date"
                                value={form.desde}
                                onChange={(e) => setForm({ ...form, desde: e.target.value })}
                            />
                        </Campo>

                        <Campo id="hasta" etiqueta="Ingreso hasta">
                            <Input
                                id="hasta"
                                type="date"
                                value={form.hasta}
                                onChange={(e) => setForm({ ...form, hasta: e.target.value })}
                            />
                        </Campo>

                        <Campo id="estado" etiqueta="Estado">
                            <Select value={form.estado} onValueChange={(v) => setForm({ ...form, estado: v })}>
                                <SelectTrigger id="estado">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={TODOS}>Todos</SelectItem>
                                    <SelectItem value="activo">Activos</SelectItem>
                                    <SelectItem value="baja">Dados de baja</SelectItem>
                                </SelectContent>
                            </Select>
                        </Campo>

                        <Campo id="agrupar" etiqueta="Agrupar por">
                            <Select
                                value={form.agrupar_por}
                                onValueChange={(v) => setForm({ ...form, agrupar_por: v })}
                            >
                                <SelectTrigger id="agrupar">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(catalogos.agrupaciones).map(([clave, rotulo]) => (
                                        <SelectItem key={clave} value={clave}>
                                            {rotulo}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Campo>
                    </div>

                    <div className="mt-4 flex gap-2">
                        <Button onClick={consultar} size="sm">
                            <Search className="size-4" />
                            Consultar
                        </Button>
                        <Button onClick={limpiar} variant="ghost" size="sm">
                            <X className="size-4" />
                            Limpiar
                        </Button>
                    </div>
                </div>

                {/* --- Resumen --- */}
                <div className="grid gap-3 sm:grid-cols-3">
                    <Resumen rotulo="Bienes" valor={String(reporte.total_bienes)} />
                    <Resumen rotulo="Unidades contadas" valor={String(reporte.total_cantidad)} />
                    <Resumen rotulo="Valor total" valor={quetzales(reporte.total_valor)} destacado />
                </div>

                {/* --- Resultado --- */}
                <div className="bg-card overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-xs uppercase">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">Código</th>
                                <th className="px-3 py-2 text-left font-medium">Descripción</th>
                                <th className="px-3 py-2 text-right font-medium">Cant.</th>
                                <th className="px-3 py-2 text-right font-medium">Valor unitario</th>
                                <th className="px-3 py-2 text-right font-medium">Valor total</th>
                                <th className="px-3 py-2 text-left font-medium">Cuenta</th>
                                <th className="px-3 py-2 text-left font-medium">Adquisición</th>
                                <th className="px-3 py-2 text-left font-medium">Ingreso</th>
                            </tr>
                        </thead>
                        <tbody>
                            {reporte.grupos.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-muted-foreground px-3 py-10 text-center">
                                        Ningún bien coincide con los filtros indicados.
                                    </td>
                                </tr>
                            )}

                            {reporte.grupos.map((grupo) => (
                                <>
                                    <tr key={`g-${grupo.rotulo}`} className="bg-muted/40">
                                        <td colSpan={8} className="px-3 py-2 text-xs font-semibold uppercase">
                                            {grupo.rotulo}
                                        </td>
                                    </tr>

                                    {grupo.bienes.map((b) => (
                                        <tr key={b.id} className="border-t">
                                            <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                                {b.codigo}
                                            </td>
                                            <td className="max-w-md px-3 py-2">{b.descripcion}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{b.cantidad}</td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {quetzales(b.precio_unitario)}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {quetzales(b.total)}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">{b.cuenta ?? '—'}</td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {b.forma_adquisicion === 'donacion'
                                                    ? `Donación${b.programa ? ` · ${b.programa}` : ''}`
                                                    : b.forma_adquisicion === 'compra'
                                                      ? 'Compra'
                                                      : '—'}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {b.fecha_ingreso ?? '—'}
                                            </td>
                                        </tr>
                                    ))}

                                    <tr key={`s-${grupo.rotulo}`} className="border-t-2 font-medium">
                                        <td className="px-3 py-2" />
                                        <td className="px-3 py-2 text-right">Subtotal</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{grupo.cantidad}</td>
                                        <td className="px-3 py-2" />
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {quetzales(grupo.valor)}
                                        </td>
                                        <td className="px-3 py-2" colSpan={3} />
                                    </tr>
                                </>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function Campo({
    id,
    etiqueta,
    children,
}: {
    id: string;
    etiqueta: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id} className="text-xs">
                {etiqueta}
            </Label>
            {children}
        </div>
    );
}

function Resumen({
    rotulo,
    valor,
    destacado = false,
}: {
    rotulo: string;
    valor: string;
    destacado?: boolean;
}) {
    return (
        <div className="bg-card rounded-lg border p-4">
            <p className="text-muted-foreground text-xs uppercase">{rotulo}</p>
            <p className={`mt-1 tabular-nums ${destacado ? 'text-2xl font-semibold' : 'text-xl'}`}>
                {valor}
            </p>
        </div>
    );
}
