import { AlertaEstado } from '@/components/alerta-estado';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileCheck2, Plus, Printer, Search, Settings2, Trash2, X } from 'lucide-react';
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
}

interface Emitida {
    id: number;
    numero: string;
    fecha: string | null;
    firmante: string;
    bienes: number;
    emitida_por: string | null;
}

interface Props {
    buscar: string;
    resultados: Resultado[];
    formatos: Formato[];
    plantillas: { libro_auxiliar: string; hojas_movibles: string };
    cierre: string;
    emitidas: Emitida[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventario', href: '/inventario/bienes' },
    { title: 'Certificaciones', href: '/inventario/certificaciones' },
];

/** Un bien ya elegido, con el texto que va a salir impreso. */
interface Punto {
    bien_id: number;
    codigo: string;
    texto: string;
}

export default function Certificaciones({ buscar, resultados, formatos, plantillas, cierre, emitidas }: Props) {
    const { puede } = usePermisos();

    const [termino, setTermino] = useState(buscar);
    const [formatoId, setFormatoId] = useState(formatos[0]?.id ?? 0);
    const [puntos, setPuntos] = useState<Punto[]>([]);
    const [elegidos, setElegidos] = useState<Resultado[]>([]);

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
    const primero = elegidos[0] ?? null;
    const usaAuxiliar = primero?.libro_auxiliar ?? true;

    // Bienes de unidades distintas: el párrafo del libro solo puede hablar de una.
    const unidadesMezcladas = elegidos.some((b) => b.unidad_id !== primero?.unidad_id);

    const armarParrafo = () =>
        usaAuxiliar
            ? plantillas.libro_auxiliar
                  .replace(':unidad', primero?.unidad_nombre ?? '__________')
                  .replace(':registro', registro.trim().toUpperCase() || '__________')
                  .replace(':folio', folio.trim() || '____')
            : plantillas.hojas_movibles;

    useEffect(() => {
        if (!aperturaTocada && formato) {
            setApertura(formato.apertura);
        }
    }, [formatoId, formatos]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (!libroTocado) {
            setParrafoLibro(armarParrafo());
        }
    }, [elegidos, registro, folio, libroTocado]); // eslint-disable-line react-hooks/exhaustive-deps

    const buscarBienes = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/inventario/certificaciones',
            { buscar: termino },
            { preserveState: true, preserveScroll: true, replace: true, only: ['resultados', 'buscar'] },
        );
    };

    const agregar = (bien: Resultado) => {
        if (puntos.some((p) => p.bien_id === bien.id)) {
            return;
        }

        // El primer bien trae el libro y el folio que se usaron la última vez.
        if (elegidos.length === 0) {
            setRegistro(bien.libro_registro ?? '');
            setFolio(bien.libro_folio ?? '');
        }

        setElegidos([...elegidos, bien]);
        setPuntos([...puntos, { bien_id: bien.id, codigo: bien.codigo, texto: bien.texto }]);
    };

    const quitar = (bienId: number) => {
        setElegidos(elegidos.filter((b) => b.id !== bienId));
        setPuntos(puntos.filter((p) => p.bien_id !== bienId));
    };

    const emitir = () => {
        setEmitiendo(true);

        router.post(
            '/inventario/certificaciones',
            {
                certificacion_formato_id: formatoId,
                unidad_servicio_id: primero?.unidad_id ?? null,
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
                    const nueva = (pagina.props as unknown as Props).emitidas?.[0];

                    if (nueva) {
                        window.open(`/inventario/certificaciones/${nueva.id}/imprimir`, '_blank');
                    }

                    setPuntos([]);
                    setElegidos([]);
                    setLibroTocado(false);
                    setAperturaTocada(false);
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
                                        <tr key={bien.id} className="border-t">
                                            <td className="px-3 py-2 font-mono text-xs">{bien.codigo}</td>
                                            <td className="max-w-md px-3 py-2">
                                                <span className="line-clamp-2">{bien.descripcion}</span>
                                            </td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{bien.responsable ?? '—'}</td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{bien.unidad_nombre}</td>
                                            <td className="px-3 py-2 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={puntos.some((p) => p.bien_id === bien.id)}
                                                    onClick={() => agregar(bien)}
                                                >
                                                    <Plus className="size-4" />
                                                    Agregar
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* --- El documento --- */}
                <div className="rounded-lg border p-4">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <Label className="block">El documento</Label>
                        <span className="text-muted-foreground text-xs">Puede escribir encima de cualquier párrafo.</span>
                    </div>

                    {puntos.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Busque un bien y agréguelo para ver cómo queda la certificación.</p>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {unidadesMezcladas && (
                                <p className="rounded-md border border-amber-500/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                    Los bienes elegidos son de unidades distintas. El documento cita el libro de {primero?.unidad_nombre}.
                                </p>
                            )}

                            <Bloque
                                etiqueta="Encabezado"
                                valor={apertura}
                                filas={2}
                                onChange={(v) => {
                                    setApertura(v);
                                    setAperturaTocada(true);
                                }}
                            />

                            <p className="text-center text-base font-medium">CERTIFICA:</p>

                            {usaAuxiliar && (
                                <div className="grid max-w-lg gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="registro">Registro del libro</Label>
                                        <Input
                                            id="registro"
                                            value={registro}
                                            onChange={(e) => {
                                                setRegistro(e.target.value);
                                                setLibroTocado(false);
                                            }}
                                            placeholder="81-2025"
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="folio">Folio No.</Label>
                                        <Input
                                            id="folio"
                                            value={folio}
                                            onChange={(e) => {
                                                setFolio(e.target.value);
                                                setLibroTocado(false);
                                            }}
                                            placeholder="24"
                                        />
                                    </div>
                                </div>
                            )}

                            <Bloque
                                etiqueta="Párrafo del libro"
                                valor={parrafoLibro}
                                filas={3}
                                onChange={(v) => {
                                    setParrafoLibro(v);
                                    setLibroTocado(true);
                                }}
                            />

                            <div className="flex flex-col gap-3">
                                {puntos.map((punto, indice) => (
                                    <div key={punto.bien_id} className="flex gap-2">
                                        <span className="pt-2 text-sm font-medium">{indice + 1}.</span>
                                        <textarea
                                            rows={3}
                                            value={punto.texto}
                                            onChange={(e) =>
                                                setPuntos(puntos.map((p) => (p.bien_id === punto.bien_id ? { ...p, texto: e.target.value } : p)))
                                            }
                                            className="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                                        />
                                        <Button size="icon" variant="ghost" onClick={() => quitar(punto.bien_id)} title={`Quitar ${punto.codigo}`}>
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            <Bloque etiqueta="Cierre" valor={textoCierre} filas={3} onChange={setTextoCierre} />

                            <div className="text-muted-foreground grid gap-4 border-t pt-4 text-sm sm:grid-cols-2">
                                <div>
                                    <p className="text-foreground font-medium">{formato?.firmante_nombre}</p>
                                    <p>{formato?.firmante_cargo}</p>
                                </div>
                                <div className="sm:text-right">
                                    <p className="text-foreground font-medium">Vo.Bo. {formato?.vobo_nombre}</p>
                                    <p>{formato?.vobo_cargo}</p>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center justify-end gap-3">
                                {usaAuxiliar && !listo && (
                                    <span className="text-muted-foreground text-xs">Escriba el registro del libro y el folio.</span>
                                )}
                                <Button onClick={emitir} disabled={!listo || emitiendo || !puede('certificaciones.emitir')}>
                                    <Printer className="size-4" />
                                    Emitir e imprimir
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* --- Emitidas --- */}
                {emitidas.length > 0 && (
                    <div className="rounded-lg border p-4">
                        <Label className="mb-3 block">Últimas certificaciones</Label>

                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Número</th>
                                        <th className="px-3 py-2 font-medium">Fecha</th>
                                        <th className="px-3 py-2 font-medium">Firma</th>
                                        <th className="px-3 py-2 font-medium">Bienes</th>
                                        <th className="px-3 py-2 font-medium">Emitida por</th>
                                        <th className="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {emitidas.map((c) => (
                                        <tr key={c.id} className="border-t">
                                            <td className="px-3 py-2 font-mono text-xs">{c.numero}</td>
                                            <td className="px-3 py-2">{c.fecha}</td>
                                            <td className="px-3 py-2">{c.firmante}</td>
                                            <td className="px-3 py-2">{c.bienes}</td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{c.emitida_por ?? '—'}</td>
                                            <td className="px-3 py-2 text-right">
                                                <a
                                                    href={`/inventario/certificaciones/${c.id}/imprimir`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                                >
                                                    <Printer className="size-3.5" />
                                                    Imprimir
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            <DialogConfiguracion abierto={configurando} formatos={formatos} onCerrar={() => setConfigurando(false)} />
        </AppLayout>
    );
}

/** Un párrafo del documento, editable en la misma vista. */
function Bloque({ etiqueta, valor, filas, onChange }: { etiqueta: string; valor: string; filas: number; onChange: (valor: string) => void }) {
    return (
        <div className="grid gap-1.5">
            <span className="text-muted-foreground text-xs">{etiqueta}</span>
            <textarea
                rows={filas}
                value={valor}
                onChange={(e) => onChange(e.target.value)}
                className="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
            />
        </div>
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
