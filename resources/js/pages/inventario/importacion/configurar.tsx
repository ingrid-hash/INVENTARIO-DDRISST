import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CheckCircle2,
    FileSpreadsheet,
    Info,
    LoaderCircle,
    Upload,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Hoja {
    nombre: string;
    filas: number;
    columnas: number;
    legible: boolean;
    motivo: string | null;
}

interface Vista {
    encabezado: Record<string, string | null>;
    total_detectados: number;
    nuevos: number;
    sin_codigo: number;
    ya_existen: string[];
    filas_descartadas: number;
    errores: { fila: number; codigo: string | null; motivo_clave: string; motivo: string }[];
    renglones_detectados: Record<string, string>;
    cuentas_faltantes: string[];
    empleado_parecido: { id: number; nombre: string; similitud: number } | null;
    muestra: {
        fila: number;
        codigo: string;
        descripcion: string;
        cantidad: number;
        total: number;
        cuenta: string | null;
        cuenta_texto_original: string | null;
        tipo_movimiento: string;
        forma_adquisicion: string | null;
        programa: string | null;
        fecha_texto_original: string | null;
    }[];
    suma_total: number;
}

interface Props {
    archivo: { ruta: string; nombre: string };
    hojas: Hoja[];
    hojaElegida: string | null;
    deteccion: {
        fila_encabezado: number | null;
        tipo_sugerido: string;
        confianza: number;
        mapeo: Record<string, string>;
        columnas: { letra: string; ejemplo: string }[];
    } | null;
    vista: Vista | null;
    avisoHoja: string | null;
    unidades: { id: number; codigo: string; nombre: string }[];
    campos: Record<string, { etiqueta: string; obligatorio: boolean; ayuda: string }>;
    perfiles: {
        id: number;
        nombre: string;
        tipo: string;
        unidad: string | null;
        mapeo: Record<string, string>;
        fila_encabezado: number | null;
    }[];
}

const SIN_COLUMNA = 'ninguna';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Importar', href: '/inventario/importacion' },
    { title: 'Configurar la carga', href: '#' },
];

const quetzales = (v: number) =>
    'Q ' + v.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Configurar({
    archivo,
    hojas,
    hojaElegida,
    deteccion,
    vista,
    avisoHoja,
    unidades,
    campos,
    perfiles,
}: Props) {
    const [guardarPerfil, setGuardarPerfil] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        ruta: archivo.ruta,
        nombre: archivo.nombre,
        hoja: hojaElegida ?? '',
        tipo: deteccion?.tipo_sugerido ?? 'listado',
        fila_encabezado: deteccion?.fila_encabezado ? String(deteccion.fila_encabezado) : '',
        unidad_servicio_id: '',
        mapeo: deteccion?.mapeo ?? {},
        guardar_perfil: '',
    });

    const cambiarHoja = (hoja: string) => {
        // Al cambiar de hoja hay que volver a detectar: cada una puede tener las
        // columnas en otro lugar, incluso dentro del mismo archivo.
        router.get(
            '/inventario/importacion-configurar',
            { ruta: archivo.ruta, nombre: archivo.nombre, hoja },
            { preserveState: false },
        );
    };

    const aplicarPerfil = (id: string) => {
        const perfil = perfiles.find((p) => String(p.id) === id);

        if (!perfil) return;

        setData((actual) => ({
            ...actual,
            tipo: perfil.tipo,
            mapeo: perfil.mapeo,
            fila_encabezado: perfil.fila_encabezado ? String(perfil.fila_encabezado) : actual.fila_encabezado,
        }));
    };

    const enviar: FormEventHandler = (e) => {
        e.preventDefault();
        setData('guardar_perfil', guardarPerfil);
        post('/inventario/importacion-ejecutar');
    };

    const faltanObligatorios = ['codigo', 'descripcion'].filter((campo) => !data.mapeo[campo]);
    const esTarjeta = data.tipo === 'tarjeta';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configurar la carga" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-start gap-3">
                    <Button asChild variant="ghost" size="icon">
                        <Link href="/inventario/importacion">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-foreground flex items-center gap-2 text-xl font-semibold">
                            <FileSpreadsheet className="size-5" />
                            {archivo.nombre}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Revise la hoja, el mapeo de columnas y la previsualización antes de cargar.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
                    {/* --- Panel de configuración --- */}
                    <form onSubmit={enviar} className="grid content-start gap-4">
                        <section className="border-border bg-card grid gap-3 rounded-xl border p-4">
                            <p className="text-foreground text-sm font-medium">Hoja del libro</p>

                            <div className="grid max-h-64 gap-1 overflow-y-auto">
                                {hojas.map((hoja) => (
                                    <button
                                        key={hoja.nombre}
                                        type="button"
                                        onClick={() => cambiarHoja(hoja.nombre)}
                                        disabled={!hoja.legible}
                                        title={hoja.motivo ?? undefined}
                                        className={cn(
                                            'flex items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm transition-colors',
                                            hoja.nombre === data.hoja && 'bg-mspas-navy text-white',
                                            hoja.nombre !== data.hoja && hoja.legible && 'hover:bg-accent',
                                            !hoja.legible && 'text-muted-foreground cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        <span className="flex min-w-0 items-center gap-1.5">
                                            {!hoja.legible && <Ban className="size-3.5 shrink-0" />}
                                            <span className="truncate">{hoja.nombre}</span>
                                        </span>
                                        <span
                                            className={cn(
                                                'shrink-0 text-xs tabular-nums',
                                                hoja.nombre === data.hoja
                                                    ? 'text-mspas-cyan-light'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {hoja.legible ? `${hoja.filas} filas` : 'no legible'}
                                        </span>
                                    </button>
                                ))}
                            </div>

                            {hojas.some((h) => !h.legible) && (
                                <p className="text-muted-foreground text-xs leading-relaxed">
                                    Las hojas marcadas son de resumen: contienen fórmulas que suman las demás hojas y
                                    no traen bienes que importar.
                                </p>
                            )}
                        </section>

                        <section className="border-border bg-card grid gap-4 rounded-xl border p-4">
                            <div className="grid gap-2">
                                <Label htmlFor="tipo">¿Qué contiene esta hoja?</Label>
                                <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)}>
                                    <SelectTrigger id="tipo">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="listado">Listado general de una unidad</SelectItem>
                                        <SelectItem value="tarjeta">Tarjeta de responsabilidad</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    {esTarjeta
                                        ? 'Se creará el empleado del encabezado con su tarjeta y la custodia de cada bien.'
                                        : 'Los bienes entrarán sin responsable asignado.'}
                                </p>
                                <InputError message={errors.tipo} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="unidad_servicio_id">Unidad de servicio destino</Label>
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

                            <div className="grid gap-2">
                                <Label htmlFor="fila_encabezado">Fila de los rótulos</Label>
                                <Input
                                    id="fila_encabezado"
                                    type="number"
                                    min={1}
                                    value={data.fila_encabezado}
                                    onChange={(e) => setData('fila_encabezado', e.target.value)}
                                    placeholder="detectada automáticamente"
                                />
                                <p className="text-muted-foreground text-xs">
                                    Los datos empiezan después de esta fila.
                                </p>
                            </div>

                            {perfiles.length > 0 && (
                                <div className="border-border grid gap-2 border-t pt-3">
                                    <Label>Aplicar un mapeo guardado</Label>
                                    <Select onValueChange={aplicarPerfil}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Elegir perfil" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {perfiles.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            <div className="border-border grid gap-2 border-t pt-3">
                                <Label htmlFor="guardar_perfil">Guardar este mapeo como perfil</Label>
                                <Input
                                    id="guardar_perfil"
                                    placeholder="Listado Momostenango"
                                    value={guardarPerfil}
                                    onChange={(e) => setGuardarPerfil(e.target.value)}
                                />
                                <p className="text-muted-foreground text-xs">
                                    Opcional. Así el próximo archivo con este formato se mapea solo.
                                </p>
                            </div>
                        </section>

                        <section className="border-border bg-card grid gap-3 rounded-xl border p-4">
                            <div className="flex items-center justify-between">
                                <p className="text-foreground text-sm font-medium">Mapeo de columnas</p>
                                {deteccion && (
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'text-xs',
                                            deteccion.confianza >= 80
                                                ? 'border-mspas-teal text-mspas-teal'
                                                : 'border-mspas-amber text-mspas-amber',
                                        )}
                                    >
                                        {deteccion.confianza}% detectado
                                    </Badge>
                                )}
                            </div>

                            {Object.entries(campos).map(([campo, info]) => (
                                <div key={campo} className="grid gap-1.5">
                                    <Label htmlFor={`mapeo-${campo}`} className="text-xs">
                                        {info.etiqueta}
                                        {info.obligatorio && <span className="text-destructive ml-1">*</span>}
                                    </Label>
                                    <Select
                                        value={data.mapeo[campo] ?? SIN_COLUMNA}
                                        onValueChange={(v) =>
                                            setData('mapeo', {
                                                ...data.mapeo,
                                                [campo]: v === SIN_COLUMNA ? '' : v,
                                            })
                                        }
                                    >
                                        <SelectTrigger id={`mapeo-${campo}`} className="h-8">
                                            <SelectValue placeholder="—" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={SIN_COLUMNA}>No está en el archivo</SelectItem>
                                            {deteccion?.columnas.map((col) => (
                                                <SelectItem key={col.letra} value={col.letra}>
                                                    <span className="font-mono">{col.letra}</span>
                                                    <span className="text-muted-foreground ml-2 text-xs">
                                                        {col.ejemplo}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {info.ayuda && <p className="text-muted-foreground text-xs">{info.ayuda}</p>}
                                </div>
                            ))}

                            <InputError message={errors.mapeo} />
                        </section>

                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || faltanObligatorios.length > 0 || !data.unidad_servicio_id}
                        >
                            {processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Upload className="size-4" />
                            )}
                            Importar {vista ? `${vista.nuevos} bien(es)` : ''}
                        </Button>

                        {faltanObligatorios.length > 0 && (
                            <p className="text-destructive text-xs">
                                Falta indicar la columna de: {faltanObligatorios.join(' y ')}.
                            </p>
                        )}
                    </form>

                    {/* --- Previsualización --- */}
                    <div className="grid content-start gap-4">
                        {avisoHoja && (
                            <section className="border-mspas-amber bg-mspas-amber/10 flex gap-3 rounded-xl border p-4 text-sm leading-relaxed">
                                <Ban className="text-mspas-amber mt-0.5 size-5 shrink-0" />
                                <div>
                                    <p className="text-foreground font-medium">Esta hoja no se puede leer</p>
                                    <p className="text-foreground/80 mt-1">{avisoHoja}</p>
                                    <p className="text-foreground/80 mt-1">Elija otra hoja de la lista.</p>
                                </div>
                            </section>
                        )}

                        {vista && (
                            <>
                                <div className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-4">
                                    <Cifra etiqueta="Bienes detectados" valor={String(vista.total_detectados)} />
                                    <Cifra
                                        etiqueta="Entrarán al sistema"
                                        valor={String(vista.nuevos)}
                                        acento="teal"
                                    />
                                    <Cifra
                                        etiqueta="Sin código en el Excel"
                                        valor={String(vista.sin_codigo)}
                                        acento={vista.sin_codigo > 0 ? 'amber' : undefined}
                                    />
                                    <Cifra etiqueta="Valor total" valor={quetzales(vista.suma_total)} />
                                </div>

                                {vista.sin_codigo > 0 && (
                                    <section className="border-border bg-card flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                                        <Info className="text-mspas-cyan mt-0.5 size-4 shrink-0" />
                                        <span className="text-foreground/80">
                                            <strong>{vista.sin_codigo} bien(es) no traen código en el archivo.</strong>{' '}
                                            Se van a cargar igual, con un código provisional que genera el sistema
                                            (SC-…), porque de ellos sí se sabe la unidad y el responsable. Después se
                                            filtran en el listado de bienes para irles poniendo el código oficial.
                                        </span>
                                    </section>
                                )}

                                {esTarjeta && vista.encabezado.nombre && (
                                    <section className="border-mspas-cyan bg-mspas-cyan/10 grid gap-2 rounded-xl border p-4">
                                        <p className="text-foreground text-sm font-medium">
                                            Empleado leído del encabezado
                                        </p>
                                        <div className="grid gap-1 text-sm sm:grid-cols-2">
                                            <Dato etiqueta="Nombre" valor={vista.encabezado.nombre} />
                                            <Dato etiqueta="Cargo" valor={vista.encabezado.cargo} />
                                            <Dato etiqueta="Área" valor={vista.encabezado.area_trabajo} />
                                            <Dato etiqueta="Unidad en el archivo" valor={vista.encabezado.unidad_servicio} />
                                        </div>

                                        {vista.empleado_parecido && (
                                            <p className="text-foreground/80 mt-1 text-xs leading-relaxed">
                                                <AlertTriangle className="text-mspas-amber mr-1 inline size-3.5" />
                                                Ya existe <strong>{vista.empleado_parecido.nombre}</strong> con un{' '}
                                                {vista.empleado_parecido.similitud}% de parecido. Se usará esa misma
                                                persona en lugar de crear una nueva.
                                            </p>
                                        )}
                                    </section>
                                )}

                                {esTarjeta && !vista.encabezado.nombre && (
                                    <section className="border-destructive bg-destructive/10 rounded-xl border p-4 text-sm">
                                        <AlertTriangle className="text-destructive mr-1 inline size-4" />
                                        Esta hoja no trae el nombre del empleado en el encabezado, así que no se puede
                                        importar como tarjeta. Elija otra hoja o cámbiela a listado general.
                                    </section>
                                )}

                                {vista.cuentas_faltantes.length > 0 && (
                                    <section className="border-mspas-amber bg-mspas-amber/10 rounded-xl border p-4 text-sm leading-relaxed">
                                        <p className="text-foreground font-medium">
                                            Cuentas del archivo que no están en el catálogo
                                        </p>
                                        <p className="text-foreground/80 mt-1">
                                            <span className="font-mono">{vista.cuentas_faltantes.join(', ')}</span>. Los
                                            bienes entrarán sin cuenta asignada. Puede agregarlas en{' '}
                                            <Link href="/inventario/cuentas" className="underline">
                                                Cuentas
                                            </Link>{' '}
                                            y volver a importar, o completarlas después en lote.
                                        </p>
                                    </section>
                                )}

                                <section className="border-border bg-card overflow-hidden rounded-xl border">
                                    <div className="border-border flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                                        <p className="text-foreground text-sm font-medium">
                                            Así se van a leer las primeras filas
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {vista.filas_descartadas} fila(s) descartadas por ser encabezado, corte
                                            VAN/VIENEN, firma o renglón
                                        </p>
                                    </div>

                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                                <tr>
                                                    <th className="px-3 py-2 font-medium">Fila</th>
                                                    <th className="px-3 py-2 font-medium">Código</th>
                                                    <th className="px-3 py-2 font-medium">Descripción</th>
                                                    <th className="px-3 py-2 font-medium">Cuenta</th>
                                                    <th className="px-3 py-2 text-right font-medium">Monto</th>
                                                    <th className="px-3 py-2 font-medium">Procedencia</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-border divide-y">
                                                {vista.muestra.map((b) => (
                                                    <tr key={b.fila}>
                                                        <td className="text-muted-foreground px-3 py-2 tabular-nums">
                                                            {b.fila}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                                            {b.codigo}
                                                        </td>
                                                        <td className="max-w-sm px-3 py-2">
                                                            <p className="truncate">{b.descripcion}</p>
                                                            {b.fecha_texto_original && (
                                                                <p className="text-muted-foreground text-xs">
                                                                    {b.fecha_texto_original}
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {b.cuenta ? (
                                                                <span className="font-mono text-xs">{b.cuenta}</span>
                                                            ) : (
                                                                <span className="text-muted-foreground text-xs">—</span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums">
                                                            {b.total.toLocaleString('es-GT', {
                                                                minimumFractionDigits: 2,
                                                            })}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="flex flex-wrap gap-1">
                                                                {b.tipo_movimiento === 'adicion' && (
                                                                    <Badge className="bg-mspas-cyan text-xs text-white">
                                                                        adición
                                                                    </Badge>
                                                                )}
                                                                {b.forma_adquisicion && (
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        {b.forma_adquisicion}
                                                                    </Badge>
                                                                )}
                                                                {b.programa && (
                                                                    <Badge variant="outline" className="text-xs">
                                                                        {b.programa}
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                {Object.keys(vista.renglones_detectados).length > 0 && (
                                    <section className="border-border bg-card rounded-xl border p-4">
                                        <p className="text-foreground text-sm font-medium">
                                            Secciones de cuenta encontradas en la hoja
                                        </p>
                                        <ul className="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                                            {Object.entries(vista.renglones_detectados).map(([codigo, nombre]) => (
                                                <li key={codigo} className="flex gap-2">
                                                    <span className="font-mono text-xs">{codigo}</span>
                                                    <span className="text-muted-foreground truncate text-xs">
                                                        {nombre}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                )}

                                {vista.errores.length > 0 && (
                                    <section className="border-border bg-card overflow-hidden rounded-xl border">
                                        <div className="border-border border-b px-4 py-3">
                                            <p className="text-foreground text-sm font-medium">
                                                {vista.errores.length} fila(s) no se podrán importar
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                Se guardan en un reporte con el número de fila para poder corregirlas
                                                en el Excel.
                                            </p>
                                        </div>

                                        <div className="max-h-64 overflow-y-auto">
                                            <table className="w-full text-sm">
                                                <tbody className="divide-border divide-y">
                                                    {vista.errores.slice(0, 40).map((e, i) => (
                                                        <tr key={`${e.fila}-${i}`}>
                                                            <td className="text-muted-foreground w-16 px-3 py-2 tabular-nums">
                                                                f{e.fila}
                                                            </td>
                                                            <td className="w-40 px-3 py-2 font-mono text-xs">
                                                                {e.codigo ?? '—'}
                                                            </td>
                                                            <td className="text-muted-foreground px-3 py-2 text-xs">
                                                                {e.motivo}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                )}

                                {vista.ya_existen.length > 0 && (
                                    <section className="border-border text-muted-foreground flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                                        <Info className="mt-0.5 size-4 shrink-0" />
                                        <span>
                                            {vista.ya_existen.length} código(s) ya están en el sistema y se van a
                                            rechazar para no duplicar bienes:{' '}
                                            <span className="font-mono">
                                                {vista.ya_existen.slice(0, 8).join(', ')}
                                                {vista.ya_existen.length > 8 && ` y ${vista.ya_existen.length - 8} más`}
                                            </span>
                                        </span>
                                    </section>
                                )}

                                {vista.errores.length === 0 && vista.ya_existen.length === 0 && (
                                    <section className="border-mspas-teal bg-mspas-teal/10 flex items-center gap-2 rounded-lg border p-3 text-sm">
                                        <CheckCircle2 className="text-mspas-teal size-4" />
                                        Todas las filas se pueden importar sin conflictos.
                                    </section>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Cifra({ etiqueta, valor, acento }: { etiqueta: string; valor: string; acento?: 'teal' | 'amber' }) {
    return (
        <div className="bg-card flex flex-col gap-1 p-4">
            <p
                className={cn(
                    'text-2xl font-semibold tabular-nums',
                    acento === 'teal' && 'text-mspas-teal',
                    acento === 'amber' && 'text-mspas-amber',
                    !acento && 'text-foreground',
                )}
            >
                {valor}
            </p>
            <p className="text-muted-foreground text-xs">{etiqueta}</p>
        </div>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
    return (
        <p>
            <span className="text-muted-foreground text-xs">{etiqueta}: </span>
            <span className="text-foreground">{valor ?? '—'}</span>
        </p>
    );
}
