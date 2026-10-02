import { AlertaEstado } from '@/components/alerta-estado';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, Download, FileCheck2, Plus, RefreshCw, Search, Settings2, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Formato {
    id: number;
    nombre: string;
    cargo_apertura: string;
    genero: string;
    firmante_nombre: string;
    firmante_cargo: string;
    vobo_nombre: string;
    vobo_cargo: string;
    institucion: string;
    predeterminado: boolean;
    apertura: string;
}

/** Un punto de una certificación ya emitida. */
interface PuntoHecho {
    bien_id: number;
    codigo: string;
    texto: string;
    texto_actual: string;
}

/** Una certificación ya emitida, con todo para volver a armarla. */
interface Hecha {
    id: number;
    numero: string;
    fecha: string | null;
    emitida_por: string | null;
    firmante: string;
    formato_id: number | null;
    unidad_servicio_id: number | null;
    unidad_nombre: string;
    libro_auxiliar: boolean;
    libro_registro: string | null;
    libro_folio: string | null;
    apertura: string;
    parrafo_libro: string;
    bienes: PuntoHecho[];
}

interface Resultado {
    id: number;
    codigo: string;
    descripcion: string;
    precio_unitario: number;
    responsable: string | null;
    unidad_id: number | null;
    unidad_nombre: string;
    libro_auxiliar: boolean;
    libro_registro: string | null;
    libro_folio: string | null;
    texto: string;
    certificacion_previa: Hecha | null;
}

interface Props {
    buscar: string;
    resultados: Resultado[];
    buscar_hechas: string;
    hechas: Hecha[];
    formatos: Formato[];
    plantillas: { libro_auxiliar: string; hojas_movibles: string };
    cierre: string;
    recien_emitida: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventario', href: '/inventario/bienes' },
    { title: 'Certificaciones', href: '/inventario/certificaciones' },
];

/** Un bien ya elegido, con el texto que va a salir impreso. */
interface Punto {
    bien_id: number;
    codigo: string;
    unidad_id: number | null;
    texto: string;
    /** El texto que saldría hoy con los datos del bien. */
    texto_actual: string;
}

/** La unidad sobre cuyo libro se certifica. */
interface Contexto {
    unidad_id: number | null;
    unidad_nombre: string;
    libro_auxiliar: boolean;
}

export default function Certificaciones({ buscar, resultados, buscar_hechas, hechas, formatos, plantillas, cierre }: Props) {
    const { puede } = usePermisos();

    const [termino, setTermino] = useState(buscar);
    const [terminoHechas, setTerminoHechas] = useState(buscar_hechas);

    const [formatoId, setFormatoId] = useState(formatos[0]?.id ?? 0);
    const [puntos, setPuntos] = useState<Punto[]>([]);
    const [contexto, setContexto] = useState<Contexto | null>(null);

    // De qué certificación se tomó lo que está en pantalla, si vino de una.
    const [origen, setOrigen] = useState<string | null>(null);

    const [registro, setRegistro] = useState('');
    const [folio, setFolio] = useState('');

    const [apertura, setApertura] = useState(formatos[0]?.apertura ?? '');
    const [parrafoLibro, setParrafoLibro] = useState('');
    const [textoCierre, setTextoCierre] = useState(cierre);

    // Mientras el usuario no escriba encima, los párrafos se rehacen solos.
    const [aperturaTocada, setAperturaTocada] = useState(false);
    const [libroTocado, setLibroTocado] = useState(false);

    const [configurando, setConfigurando] = useState(false);
    const [emitiendo, setEmitiendo] = useState(false);

    const formato = formatos.find((f) => f.id === formatoId);
    const usaAuxiliar = contexto?.libro_auxiliar ?? true;

    // Bienes de unidades distintas: el párrafo del libro solo puede hablar de una.
    const unidadesMezcladas = puntos.some((p) => p.unidad_id !== null && p.unidad_id !== contexto?.unidad_id);

    const armarParrafo = (reg: string, fol: string, ctx: Contexto | null) =>
        (ctx?.libro_auxiliar ?? true)
            ? plantillas.libro_auxiliar
                  .replace(':unidad', ctx?.unidad_nombre ?? '__________')
                  .replace(':registro', reg.trim().toUpperCase() || '__________')
                  .replace(':folio', fol.trim() || '____')
            : plantillas.hojas_movibles;

    useEffect(() => {
        if (!aperturaTocada && formato) {
            setApertura(formato.apertura);
        }
    }, [formatoId, formatos]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!libroTocado) {
            setParrafoLibro(armarParrafo(registro, folio, contexto));
        }
    }, [contexto, registro, folio, libroTocado]); // eslint-disable-line react-hooks/exhaustive-deps

    const buscarBienes = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/inventario/certificaciones',
            { buscar: termino, hechas: terminoHechas },
            { preserveState: true, preserveScroll: true, replace: true, only: ['resultados', 'buscar'] },
        );
    };

    const buscarHechas = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/inventario/certificaciones',
            { buscar: termino, hechas: terminoHechas },
            { preserveState: true, preserveScroll: true, replace: true, only: ['hechas', 'buscar_hechas'] },
        );
    };

    /**
     * Trae a pantalla una certificación ya emitida. Se reaprovecha todo menos
     * la fecha: el párrafo de cierre se vuelve a escribir con el mes de hoy.
     */
    const usarHecha = (c: Hecha) => {
        const ctx: Contexto = {
            unidad_id: c.unidad_servicio_id,
            unidad_nombre: c.unidad_nombre,
            libro_auxiliar: c.libro_auxiliar,
        };

        if (c.formato_id) {
            setFormatoId(c.formato_id);
        }

        setContexto(ctx);
        setRegistro(c.libro_registro ?? '');
        setFolio(c.libro_folio ?? '');
        setApertura(c.apertura);
        setAperturaTocada(true);
        setParrafoLibro(c.parrafo_libro);
        setLibroTocado(true);
        setTextoCierre(cierre);
        setOrigen(c.numero);

        setPuntos(
            c.bienes.map((p) => ({
                bien_id: p.bien_id,
                codigo: p.codigo,
                unidad_id: c.unidad_servicio_id,
                texto: p.texto,
                texto_actual: p.texto_actual,
            })),
        );
    };

    const agregar = (bien: Resultado) => {
        if (puntos.some((p) => p.bien_id === bien.id)) {
            return;
        }

        // Si el bien ya se certificó antes y todavía no hay nada armado, se
        // recupera aquel documento en lugar de escribirlo de nuevo.
        if (puntos.length === 0 && bien.certificacion_previa) {
            usarHecha(bien.certificacion_previa);
            return;
        }

        if (puntos.length === 0) {
            setContexto({
                unidad_id: bien.unidad_id,
                unidad_nombre: bien.unidad_nombre,
                libro_auxiliar: bien.libro_auxiliar,
            });
            setRegistro(bien.libro_registro ?? '');
            setFolio(bien.libro_folio ?? '');
        }

        setPuntos([
            ...puntos,
            {
                bien_id: bien.id,
                codigo: bien.codigo,
                unidad_id: bien.unidad_id,
                texto: bien.texto,
                texto_actual: bien.texto,
            },
        ]);
    };

    const quitar = (bienId: number) => {
        const quedan = puntos.filter((p) => p.bien_id !== bienId);
        setPuntos(quedan);

        if (quedan.length === 0) {
            limpiar();
        }
    };

    const limpiar = () => {
        setPuntos([]);
        setContexto(null);
        setOrigen(null);
        setRegistro('');
        setFolio('');
        setTextoCierre(cierre);
        setLibroTocado(false);
        setAperturaTocada(false);
    };

    /**
     * Vuelve a escribir los párrafos con los datos de hoy: la descripción y el
     * precio de cada bien, el libro y el folio que estén puestos, y el mes en
     * curso en el párrafo de cierre.
     */
    const actualizar = () => {
        setPuntos(puntos.map((p) => ({ ...p, texto: p.texto_actual })));
        setParrafoLibro(armarParrafo(registro, folio, contexto));
        setLibroTocado(false);
        setTextoCierre(cierre);

        if (formato) {
            setApertura(formato.apertura);
            setAperturaTocada(false);
        }
    };

    const emitir = () => {
        setEmitiendo(true);

        router.post(
            '/inventario/certificaciones',
            {
                certificacion_formato_id: formatoId,
                unidad_servicio_id: contexto?.unidad_id ?? null,
                libro_auxiliar: usaAuxiliar,
                libro_registro: registro.trim() || null,
                libro_folio: folio.trim() || null,
                apertura,
                parrafo_libro: parrafoLibro,
                cierre: textoCierre,
                bienes: puntos.map((p) => ({ bien_id: p.bien_id, texto: p.texto })),
            },
            {
                preserveScroll: true,
                onSuccess: (pagina) => {
                    const nueva = (pagina.props as unknown as Props).recien_emitida;

                    if (nueva) {
                        window.location.href = `/inventario/certificaciones/${nueva}/pdf`;
                    }

                    limpiar();
                },
                onFinish: () => setEmitiendo(false),
            },
        );
    };

    const listo = puntos.length > 0 && formatoId > 0 && (!usaAuxiliar || (registro.trim() !== '' && folio.trim() !== ''));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Certificaciones" />

            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-semibold">
                            <FileCheck2 className="size-5" />
                            Certificación de inventario
                        </h1>
                        <p className="text-muted-foreground text-sm">Hoja de papel bond membretado tamaño carta.</p>
                    </div>

                    {puede('certificaciones.configurar') && (
                        <Button variant="outline" onClick={() => setConfigurando(true)}>
                            <Settings2 className="size-4" />
                            Configuración
                        </Button>
                    )}
                </div>

                <AlertaEstado />

                {/* --- Quién firma --- */}
                <div className="rounded-lg border p-4">
                    <Label className="mb-3 block">Quién firma</Label>

                    <div className="grid gap-2 sm:grid-cols-2">
                        {formatos.map((f) => (
                            <label
                                key={f.id}
                                className={`flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm ${
                                    f.id === formatoId ? 'border-primary bg-primary/5' : ''
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="formato"
                                    className="mt-1"
                                    checked={f.id === formatoId}
                                    onChange={() => {
                                        setFormatoId(f.id);
                                        setAperturaTocada(false);
                                    }}
                                />
                                <span>
                                    <span className="font-medium">{f.firmante_nombre}</span>
                                    <span className="text-muted-foreground block text-xs">
                                        {f.firmante_cargo} · Vo.Bo. {f.vobo_nombre}, {f.vobo_cargo}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                </div>

                {/* --- Certificaciones hechas --- */}
                {(hechas.length > 0 || buscar_hechas !== '') && (
                    <div className="rounded-lg border p-4">
                        <Label htmlFor="hechas" className="mb-2 block">
                            Certificaciones ya hechas
                        </Label>

                        <form onSubmit={buscarHechas} className="flex max-w-2xl gap-2">
                            <Input
                                id="hechas"
                                value={terminoHechas}
                                onChange={(e) => setTerminoHechas(e.target.value)}
                                placeholder="Código, descripción, empleado o número de certificación"
                            />
                            <Button type="submit" variant="secondary">
                                <Search className="size-4" />
                                Buscar
                            </Button>
                        </form>

                        {hechas.length === 0 ? (
                            <p className="text-muted-foreground mt-3 text-sm">No se encontró ninguna certificación con «{buscar_hechas}».</p>
                        ) : (
                            <div className="mt-3 overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Número</th>
                                            <th className="px-3 py-2 font-medium">Fecha</th>
                                            <th className="px-3 py-2 font-medium">Bienes</th>
                                            <th className="px-3 py-2 font-medium">Firma</th>
                                            <th className="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {hechas.map((c) => (
                                            <tr key={c.id} className="border-t align-top">
                                                <td className="px-3 py-2 font-mono text-xs">{c.numero}</td>
                                                <td className="px-3 py-2 whitespace-nowrap">{c.fecha}</td>
                                                <td className="px-3 py-2">
                                                    <span className="font-mono text-xs">{c.bienes.map((b) => b.codigo).join(', ')}</span>
                                                    <span className="text-muted-foreground block text-xs">{c.unidad_nombre}</span>
                                                </td>
                                                <td className="text-muted-foreground px-3 py-2 text-xs">{c.firmante}</td>
                                                <td className="px-3 py-2 text-right whitespace-nowrap">
                                                    <Button size="sm" variant="secondary" className="mr-2" onClick={() => usarHecha(c)}>
                                                        <RefreshCw className="size-4" />
                                                        Volver a usar
                                                    </Button>
                                                    <a
                                                        href={`/inventario/certificaciones/${c.id}/pdf`}
                                                        className="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                                    >
                                                        <Download className="size-3.5" />
                                                        PDF
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* --- Buscar el bien --- */}
                <div className="rounded-lg border p-4">
                    <Label htmlFor="buscar" className="mb-2 block">
                        Buscar el bien
                    </Label>

                    <form onSubmit={buscarBienes} className="flex max-w-2xl gap-2">
                        <Input
                            id="buscar"
                            value={termino}
                            onChange={(e) => setTermino(e.target.value)}
                            placeholder="Código, descripción o nombre del empleado"
                        />
                        <Button type="submit" variant="secondary">
                            <Search className="size-4" />
                            Buscar
                        </Button>
                    </form>

                    {buscar !== '' && resultados.length === 0 && (
                        <p className="text-muted-foreground mt-3 text-sm">No se encontró ningún bien con «{buscar}».</p>
                    )}

                    {resultados.length > 0 && (
                        <div className="mt-3 max-h-72 overflow-y-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Código</th>
                                        <th className="px-3 py-2 font-medium">Descripción</th>
                                        <th className="px-3 py-2 font-medium">A cargo de</th>
                                        <th className="px-3 py-2 font-medium">Unidad</th>
                                        <th className="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {resultados.map((bien) => (
                                        <tr key={bien.id} className={`border-t ${puntos.some((p) => p.bien_id === bien.id) ? 'bg-primary/5' : ''}`}>
                                            <td className="px-3 py-2 font-mono text-xs">{bien.codigo}</td>
                                            <td className="max-w-md px-3 py-2">
                                                <span className="line-clamp-2">{bien.descripcion}</span>
                                            </td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{bien.responsable ?? '—'}</td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{bien.unidad_nombre}</td>
                                            <td className="px-3 py-2 text-right">
                                                {puntos.some((p) => p.bien_id === bien.id) ? (
                                                    <span className="text-primary inline-flex items-center gap-1 text-xs font-medium">
                                                        <Check className="size-3.5" />
                                                        En la certificación
                                                    </span>
                                                ) : (
                                                    <Button size="sm" variant="secondary" onClick={() => agregar(bien)}>
                                                        <Plus className="size-4" />
                                                        Agregar
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* --- El documento --- */}
                {puntos.length === 0 ? (
                    <p className="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-sm">
                        Busque un bien y agréguelo para ver cómo queda la certificación.
                    </p>
                ) : (
                    <div className="ring-primary/30 overflow-hidden rounded-xl ring-2">
                        <div className="bg-primary/10 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3">
                            <div className="flex items-center gap-2">
                                <FileCheck2 className="text-primary size-4" />
                                <span className="font-semibold">Certificación en preparación</span>
                            </div>
                            <span className="text-muted-foreground text-xs">
                                {contexto?.unidad_nombre} · {puntos.length} {puntos.length === 1 ? 'bien' : 'bienes'}
                            </span>
                        </div>

                        <div className="bg-muted/60 flex flex-col gap-4 p-4 sm:p-6">
                            {origen && (
                                <div className="bg-background flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm">
                                    <span>
                                        Tomada de la certificación <span className="font-mono">{origen}</span>. La fecha ya quedó en el mes actual.
                                    </span>
                                    <Button size="sm" variant="outline" onClick={actualizar}>
                                        <RefreshCw className="size-4" />
                                        Actualizar datos y fecha
                                    </Button>
                                </div>
                            )}

                            {unidadesMezcladas && (
                                <p className="rounded-md border border-amber-500/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                    Los bienes elegidos son de unidades distintas. El documento cita el libro de {contexto?.unidad_nombre}.
                                </p>
                            )}

                            {usaAuxiliar && (
                                <div className="bg-background flex flex-wrap items-end gap-3 rounded-lg border p-3">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="registro" className="text-xs">
                                            Registro del libro
                                        </Label>
                                        <Input
                                            id="registro"
                                            className="w-36"
                                            value={registro}
                                            onChange={(e) => {
                                                setRegistro(e.target.value);
                                                setLibroTocado(false);
                                            }}
                                            placeholder="81-2025"
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="folio" className="text-xs">
                                            Folio No.
                                        </Label>
                                        <Input
                                            id="folio"
                                            className="w-24"
                                            value={folio}
                                            onChange={(e) => {
                                                setFolio(e.target.value);
                                                setLibroTocado(false);
                                            }}
                                            placeholder="24"
                                        />
                                    </div>
                                    <p className="text-muted-foreground pb-2 text-xs">Se escriben solos en el párrafo de abajo.</p>
                                </div>
                            )}

                            {/* La hoja: se ve como va a salir impresa y se escribe encima. */}
                            <div className="bg-background mx-auto w-full max-w-4xl rounded-lg px-6 py-8 shadow-md sm:px-12 sm:py-10">
                                <Parrafo
                                    valor={apertura}
                                    filas={2}
                                    onChange={(v) => {
                                        setApertura(v);
                                        setAperturaTocada(true);
                                    }}
                                />

                                <p className="my-5 text-center text-base font-semibold tracking-wide">CERTIFICA:</p>

                                <Parrafo
                                    valor={parrafoLibro}
                                    filas={3}
                                    onChange={(v) => {
                                        setParrafoLibro(v);
                                        setLibroTocado(true);
                                    }}
                                />

                                <div className="my-5 flex flex-col gap-2">
                                    {puntos.map((punto, indice) => (
                                        <div key={punto.bien_id} className="group flex items-start gap-2">
                                            <span className="w-5 shrink-0 pt-1 text-sm font-semibold">{indice + 1}.</span>
                                            <Parrafo
                                                valor={punto.texto}
                                                filas={3}
                                                onChange={(v) => setPuntos(puntos.map((p) => (p.bien_id === punto.bien_id ? { ...p, texto: v } : p)))}
                                            />
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="shrink-0 opacity-0 transition group-hover:opacity-100"
                                                onClick={() => quitar(punto.bien_id)}
                                                title={`Quitar ${punto.codigo}`}
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>

                                <Parrafo valor={textoCierre} filas={3} onChange={setTextoCierre} />

                                <div className="mt-10 grid gap-8 text-center text-sm sm:grid-cols-2">
                                    <div>
                                        <p className="font-medium">{formato?.firmante_nombre}</p>
                                        <p className="text-muted-foreground">{formato?.firmante_cargo}</p>
                                        <p className="text-muted-foreground">{formato?.institucion}</p>
                                    </div>
                                    <div className="sm:mt-6">
                                        <p className="font-medium">Vo.Bo. {formato?.vobo_nombre}</p>
                                        <p className="text-muted-foreground">{formato?.vobo_cargo}</p>
                                        <p className="text-muted-foreground">{formato?.institucion}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <span className="text-muted-foreground text-xs">Puede escribir encima de cualquier párrafo.</span>

                                <div className="flex flex-wrap items-center gap-3">
                                    {usaAuxiliar && !listo && (
                                        <span className="text-muted-foreground text-xs">Escriba el registro del libro y el folio.</span>
                                    )}
                                    <Button onClick={emitir} disabled={!listo || emitiendo || !puede('certificaciones.emitir')}>
                                        <Download className="size-4" />
                                        Emitir y descargar PDF
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <DialogConfiguracion abierto={configurando} formatos={formatos} onCerrar={() => setConfigurando(false)} />
        </AppLayout>
    );
}

/** Un párrafo del documento, editable en la misma vista. */
/**
 * Un párrafo de la hoja. Se lee como el texto impreso y se escribe encima; el
 * recuadro solo aparece al pasar el puntero o al escribir.
 */
function Parrafo({ valor, filas, onChange }: { valor: string; filas: number; onChange: (valor: string) => void }) {
    return (
        <textarea
            rows={filas}
            value={valor}
            onChange={(e) => onChange(e.target.value)}
            className="hover:bg-muted/60 focus:bg-muted/40 focus-visible:ring-ring w-full resize-y rounded-md border border-transparent bg-transparent px-2 py-1 text-sm leading-relaxed transition focus-visible:ring-1 focus-visible:outline-none"
            style={{ textAlign: 'justify' }}
        />
    );
}

const FORMATO_VACIO = {
    nombre: '',
    cargo_apertura: '',
    genero: 'f',
    firmante_nombre: '',
    firmante_cargo: '',
    vobo_nombre: '',
    vobo_cargo: '',
    institucion: '',
};

/** Las combinaciones de firma: se editan las que hay y se agregan nuevas. */
function DialogConfiguracion({ abierto, formatos, onCerrar }: { abierto: boolean; formatos: Formato[]; onCerrar: () => void }) {
    const [editando, setEditando] = useState<number | null>(null);
    const [datos, setDatos] = useState(FORMATO_VACIO);

    const abrirNuevo = () => {
        setEditando(0);
        setDatos(FORMATO_VACIO);
    };

    const abrirExistente = (f: Formato) => {
        setEditando(f.id);
        setDatos({
            nombre: f.nombre,
            cargo_apertura: f.cargo_apertura,
            genero: f.genero,
            firmante_nombre: f.firmante_nombre,
            firmante_cargo: f.firmante_cargo,
            vobo_nombre: f.vobo_nombre,
            vobo_cargo: f.vobo_cargo,
            institucion: f.institucion,
        });
    };

    const guardar = () => {
        const opciones = { preserveScroll: true, onSuccess: () => setEditando(null) };

        if (editando === 0) {
            router.post('/inventario/certificacion-formatos', datos, opciones);
        } else if (editando !== null) {
            router.put(`/inventario/certificacion-formatos/${editando}`, datos, opciones);
        }
    };

    const eliminar = (f: Formato) => {
        if (confirm(`¿Quitar la combinación «${f.nombre}»?`)) {
            router.delete(`/inventario/certificacion-formatos/${f.id}`, { preserveScroll: true });
        }
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && onCerrar()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Combinaciones de firma</DialogTitle>
                </DialogHeader>

                {editando === null ? (
                    <div className="flex flex-col gap-3">
                        {formatos.map((f) => (
                            <div key={f.id} className="flex items-start justify-between gap-3 rounded-md border p-3">
                                <div className="text-sm">
                                    <p className="font-medium">{f.firmante_nombre}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {f.genero === 'f' ? 'LA INFRASCRITA' : 'EL INFRASCRITO'} {f.cargo_apertura}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        Vo.Bo. {f.vobo_nombre}, {f.vobo_cargo}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    <Button size="sm" variant="outline" onClick={() => abrirExistente(f)}>
                                        Editar
                                    </Button>
                                    <Button size="icon" variant="ghost" onClick={() => eliminar(f)}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}

                        <Button variant="secondary" onClick={abrirNuevo}>
                            <Plus className="size-4" />
                            Agregar combinación
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-3">
                        <Campo etiqueta="Nombre de la combinación" valor={datos.nombre} onChange={(v) => setDatos({ ...datos, nombre: v })} />

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Campo
                                etiqueta="Cargo en el encabezado"
                                ayuda="Va en mayúsculas: ENCARGADA DE INVENTARIOS"
                                valor={datos.cargo_apertura}
                                onChange={(v) => setDatos({ ...datos, cargo_apertura: v })}
                            />
                            <div className="grid gap-1.5">
                                <Label>Se escribe</Label>
                                <select
                                    value={datos.genero}
                                    onChange={(e) => setDatos({ ...datos, genero: e.target.value })}
                                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                >
                                    <option value="f">LA INFRASCRITA</option>
                                    <option value="m">EL INFRASCRITO</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Campo
                                etiqueta="Quien firma"
                                valor={datos.firmante_nombre}
                                onChange={(v) => setDatos({ ...datos, firmante_nombre: v })}
                            />
                            <Campo etiqueta="Su cargo" valor={datos.firmante_cargo} onChange={(v) => setDatos({ ...datos, firmante_cargo: v })} />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Campo etiqueta="Visto bueno de" valor={datos.vobo_nombre} onChange={(v) => setDatos({ ...datos, vobo_nombre: v })} />
                            <Campo etiqueta="Su cargo" valor={datos.vobo_cargo} onChange={(v) => setDatos({ ...datos, vobo_cargo: v })} />
                        </div>

                        <div className="flex justify-end gap-2 pt-2">
                            <Button variant="outline" onClick={() => setEditando(null)}>
                                Cancelar
                            </Button>
                            <Button onClick={guardar}>Guardar</Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function Campo({ etiqueta, ayuda, valor, onChange }: { etiqueta: string; ayuda?: string; valor: string; onChange: (valor: string) => void }) {
    return (
        <div className="grid gap-1.5">
            <Label>{etiqueta}</Label>
            <Input value={valor} onChange={(e) => onChange(e.target.value)} />
            {ayuda && <span className="text-muted-foreground text-xs">{ayuda}</span>}
        </div>
    );
}
