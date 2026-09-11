import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info, LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface BienEdicion {
    id: number;
    codigo: string;
    descripcion: string;
    cantidad: number;
    precio_unitario: number;
    unidad_servicio_id: number;
    renglon_id: number | null;
    tipo_movimiento: string;
    forma_adquisicion: string | null;
    programa: string | null;
    documento_respaldo: string | null;
    fecha_ingreso: string | null;
    anio_ingreso: number | null;
    observaciones: string | null;
    cuenta_texto_original: string | null;
    fecha_texto_original: string | null;
}

interface Props {
    bien: BienEdicion | null;
    catalogos: {
        unidades: { id: number; codigo: string; nombre: string }[];
        cuentas: { id: number; codigo: string; nombre: string }[];
        movimientos: Record<string, string>;
        formas: Record<string, string>;
    };
    programasUsados: string[];
}

const SIN_CUENTA = 'ninguna';

export default function BienFormulario({ bien, catalogos, programasUsados }: Props) {
    const editando = bien !== null;

    const { data, setData, post, put, transform, processing, errors } = useForm({
        codigo: bien?.codigo ?? '',
        descripcion: bien?.descripcion ?? '',
        cantidad: String(bien?.cantidad ?? 1),
        precio_unitario: String(bien?.precio_unitario ?? ''),
        unidad_servicio_id: bien?.unidad_servicio_id ? String(bien.unidad_servicio_id) : '',
        renglon_id: bien?.renglon_id ? String(bien.renglon_id) : SIN_CUENTA,
        tipo_movimiento: bien?.tipo_movimiento ?? 'adicion',
        forma_adquisicion: bien?.forma_adquisicion ?? '',
        programa: bien?.programa ?? '',
        documento_respaldo: bien?.documento_respaldo ?? '',
        fecha_ingreso: bien?.fecha_ingreso ?? '',
        anio_ingreso: bien?.anio_ingreso ? String(bien.anio_ingreso) : '',
        observaciones: bien?.observaciones ?? '',
    });

    const esAdicion = data.tipo_movimiento === 'adicion';
    const esDonacion = data.forma_adquisicion === 'donacion';

    const total = (Number(data.cantidad) || 0) * (Number(data.precio_unitario) || 0);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Bienes', href: '/inventario/bienes' },
        { title: editando ? 'Editar bien' : 'Nuevo bien', href: '#' },
    ];

    const enviar: FormEventHandler = (e) => {
        e.preventDefault();

        // Los selectores usan valores centinela porque Radix no admite "".
        transform((datos) => ({
            ...datos,
            renglon_id: datos.renglon_id === SIN_CUENTA ? null : datos.renglon_id,
            forma_adquisicion: datos.forma_adquisicion || null,
        }));

        if (editando) {
            put(`/inventario/bienes/${bien.id}`);
        } else {
            post('/inventario/bienes');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editando ? 'Editar bien' : 'Nuevo bien'} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="icon">
                        <Link href="/inventario/bienes">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">
                            {editando ? `Editar bien ${bien.codigo}` : 'Nuevo bien'}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {editando
                                ? 'Los cambios quedan registrados en la bitácora.'
                                : 'Toda adición debe indicar su cuenta y cómo se adquirió.'}
                        </p>
                    </div>
                </div>

                <form onSubmit={enviar} className="grid max-w-3xl gap-4">
                    <section className="border-border bg-card grid gap-5 rounded-xl border p-6">
                        <p className="text-foreground font-medium">Identificación del bien</p>

                        <div className="grid gap-5 sm:grid-cols-[1fr_1fr]">
                            <Campo id="codigo" etiqueta="Código de inventario" error={errors.codigo}>
                                <Input
                                    id="codigo"
                                    className="font-mono"
                                    placeholder="0033C31E  ·  2026-211-CHI-0013"
                                    value={data.codigo}
                                    onChange={(e) => setData('codigo', e.target.value.toUpperCase())}
                                    required
                                />
                            </Campo>

                            <Campo
                                id="unidad_servicio_id"
                                etiqueta="Unidad de servicio"
                                error={errors.unidad_servicio_id}
                            >
                                <Select
                                    value={data.unidad_servicio_id}
                                    onValueChange={(v) => setData('unidad_servicio_id', v)}
                                >
                                    <SelectTrigger id="unidad_servicio_id">
                                        <SelectValue placeholder="Seleccione la unidad" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {catalogos.unidades.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Campo>
                        </div>

                        <Campo id="descripcion" etiqueta="Descripción" error={errors.descripcion}>
                            <textarea
                                id="descripcion"
                                rows={3}
                                className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                placeholder="Escritorio secretarial de metal con 3 gavetas y melamina color negro"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                required
                            />
                        </Campo>

                        <div className="grid gap-5 sm:grid-cols-3">
                            <Campo id="cantidad" etiqueta="Cantidad" error={errors.cantidad}>
                                <Input
                                    id="cantidad"
                                    type="number"
                                    min={1}
                                    value={data.cantidad}
                                    onChange={(e) => setData('cantidad', e.target.value)}
                                    required
                                />
                            </Campo>

                            <Campo id="precio_unitario" etiqueta="Precio unitario (Q)" error={errors.precio_unitario}>
                                <Input
                                    id="precio_unitario"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.precio_unitario}
                                    onChange={(e) => setData('precio_unitario', e.target.value)}
                                    required
                                />
                            </Campo>

                            <div className="grid gap-2">
                                <Label>Total</Label>
                                <div className="border-border bg-muted flex h-9 items-center rounded-md border px-3 text-sm font-medium tabular-nums">
                                    Q{' '}
                                    {total.toLocaleString('es-GT', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })}
                                </div>
                                <p className="text-muted-foreground text-xs">Se calcula solo</p>
                            </div>
                        </div>
                    </section>

                    <section className="border-border bg-card grid gap-5 rounded-xl border p-6">
                        <p className="text-foreground font-medium">Cuenta y procedencia</p>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Campo id="tipo_movimiento" etiqueta="Tipo de movimiento" error={errors.tipo_movimiento}>
                                <Select
                                    value={data.tipo_movimiento}
                                    onValueChange={(v) => setData('tipo_movimiento', v)}
                                >
                                    <SelectTrigger id="tipo_movimiento">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(catalogos.movimientos).map(([clave, etiqueta]) => (
                                            <SelectItem key={clave} value={clave}>
                                                {etiqueta}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Campo>

                            <Campo
                                id="renglon_id"
                                etiqueta={esAdicion ? 'Cuenta (obligatoria)' : 'Cuenta'}
                                error={errors.renglon_id}
                            >
                                <Select value={data.renglon_id} onValueChange={(v) => setData('renglon_id', v)}>
                                    <SelectTrigger id="renglon_id">
                                        <SelectValue placeholder="Seleccione la cuenta" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {!esAdicion && <SelectItem value={SIN_CUENTA}>Sin cuenta asignada</SelectItem>}
                                        {catalogos.cuentas.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.codigo} · {c.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Campo>
                        </div>

                        {esAdicion && (
                            <div className="border-border grid gap-5 rounded-lg border border-dashed p-4">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Campo
                                        id="forma_adquisicion"
                                        etiqueta="¿Cómo se adquirió?"
                                        error={errors.forma_adquisicion}
                                    >
                                        <Select
                                            value={data.forma_adquisicion}
                                            onValueChange={(v) => setData('forma_adquisicion', v)}
                                        >
                                            <SelectTrigger id="forma_adquisicion">
                                                <SelectValue placeholder="Compra o donación" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(catalogos.formas).map(([clave, etiqueta]) => (
                                                    <SelectItem key={clave} value={clave}>
                                                        {etiqueta}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Campo>

                                    <Campo
                                        id="documento_respaldo"
                                        etiqueta="Documento de respaldo"
                                        error={errors.documento_respaldo}
                                    >
                                        <Input
                                            id="documento_respaldo"
                                            placeholder="S/OF.020-2025"
                                            value={data.documento_respaldo}
                                            onChange={(e) => setData('documento_respaldo', e.target.value)}
                                        />
                                    </Campo>
                                </div>

                                {esDonacion && (
                                    <Campo id="programa" etiqueta="Programa que financió la donación" error={errors.programa}>
                                        <Input
                                            id="programa"
                                            list="programas-usados"
                                            placeholder="CRECER SANO"
                                            value={data.programa}
                                            onChange={(e) => setData('programa', e.target.value.toUpperCase())}
                                        />
                                        <datalist id="programas-usados">
                                            {programasUsados.map((p) => (
                                                <option key={p} value={p} />
                                            ))}
                                        </datalist>
                                        <p className="text-muted-foreground text-xs">
                                            Escriba o elija uno de los ya usados, para que el mismo programa no quede
                                            registrado con nombres distintos.
                                        </p>
                                    </Campo>
                                )}
                            </div>
                        )}

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Campo id="fecha_ingreso" etiqueta="Fecha de ingreso" error={errors.fecha_ingreso}>
                                <Input
                                    id="fecha_ingreso"
                                    type="date"
                                    value={data.fecha_ingreso}
                                    onChange={(e) => setData('fecha_ingreso', e.target.value)}
                                />
                            </Campo>

                            {/* Solo se captura a mano cuando el bien viene de un
                                archivo antiguo que trae el año pero no el día.
                                Si hay fecha completa, el año sale de ahí. */}
                            <Campo
                                id="anio_ingreso"
                                etiqueta="Año de ingreso"
                                error={errors.anio_ingreso}
                            >
                                <Input
                                    id="anio_ingreso"
                                    type="number"
                                    min={1950}
                                    max={new Date().getFullYear() + 1}
                                    placeholder="2018"
                                    value={
                                        data.fecha_ingreso !== ''
                                            ? data.fecha_ingreso.slice(0, 4)
                                            : data.anio_ingreso
                                    }
                                    onChange={(e) => setData('anio_ingreso', e.target.value)}
                                    disabled={data.fecha_ingreso !== ''}
                                />
                                <p className="text-muted-foreground text-xs">
                                    {data.fecha_ingreso !== ''
                                        ? 'Se toma de la fecha de ingreso.'
                                        : 'Escríbalo solo si del bien se conoce el año pero no el día exacto.'}
                                </p>
                            </Campo>
                        </div>

                        <Campo id="observaciones" etiqueta="Observaciones" error={errors.observaciones}>
                            <textarea
                                id="observaciones"
                                rows={2}
                                className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                value={data.observaciones}
                                onChange={(e) => setData('observaciones', e.target.value)}
                            />
                        </Campo>
                    </section>

                    {editando && (bien.cuenta_texto_original || bien.fecha_texto_original) && (
                        <div className="border-border text-muted-foreground flex gap-2 rounded-lg border border-dashed p-4 text-xs leading-relaxed">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p className="text-foreground font-medium">Datos originales del archivo de Excel</p>
                                {bien.cuenta_texto_original && (
                                    <p className="mt-1">
                                        Columna CUENTA:{' '}
                                        <code className="bg-muted rounded px-1 font-mono">
                                            {bien.cuenta_texto_original.replace(/\n/g, ' + ')}
                                        </code>
                                    </p>
                                )}
                                {bien.fecha_texto_original && (
                                    <p className="mt-0.5">
                                        Columna FECHA:{' '}
                                        <code className="bg-muted rounded px-1 font-mono">
                                            {bien.fecha_texto_original}
                                        </code>
                                    </p>
                                )}
                                <p className="mt-1.5">
                                    Se conservan tal cual para que la tarjeta se reimprima idéntica al documento
                                    firmado.
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button asChild variant="outline" type="button">
                            <Link href="/inventario/bienes">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {editando ? 'Guardar cambios' : 'Registrar bien'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Campo({
    id,
    etiqueta,
    error,
    children,
}: {
    id: string;
    etiqueta: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{etiqueta}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
