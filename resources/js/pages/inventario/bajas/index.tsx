import { AlertaEstado } from '@/components/alerta-estado';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, FileMinus2, Plus, Search, X } from 'lucide-react';
import { useState } from 'react';

interface BienDeBaja {
    id: number | null;
    codigo: string | null;
    descripcion: string | null;
    total: number;
    unidad: string | null;
    responsable: string | null;
}

interface Baja {
    id: number;
    estado: string;
    estado_legible: string;
    numero_acta: string | null;
    motivo: string;
    observaciones: string | null;
    fecha_solicitud: string | null;
    fecha_resolucion: string | null;
    solicitada_por: string | null;
    resuelta_por: string | null;
    bien: BienDeBaja;
}

interface Candidato {
    id: number;
    codigo: string;
    descripcion: string;
    total: number;
    unidad: string | null;
    responsable: string | null;
}

interface Props {
    bajas: { data: Baja[]; links: { url: string | null; label: string; active: boolean }[] };
    filtros: { estado: string; buscar: string };
    estados: Record<string, string>;
    resumen: { en_tramite: number; autorizadas: number; valor_dado_de_baja: number };
    candidatos: Candidato[];
    buscar_bien: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventario', href: '/inventario/bienes' },
    { title: 'Bajas', href: '/inventario/bajas' },
];

const quetzales = (valor: number) => `Q ${valor.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const COLOR_ESTADO: Record<string, string> = {
    solicitada: 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
    autorizada: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
    rechazada: 'bg-muted text-muted-foreground',
};

export default function Bajas({ bajas, filtros, estados, resumen, candidatos, buscar_bien }: Props) {
    const { puede } = usePermisos();

    const [buscar, setBuscar] = useState(filtros.buscar);
    const [termino, setTermino] = useState(buscar_bien);
    const [solicitando, setSolicitando] = useState(false);
    const [resolviendo, setResolviendo] = useState<{ baja: Baja; accion: 'autorizar' | 'rechazar' } | null>(null);

    const filtrar = (cambios: Record<string, string>) =>
        router.get('/inventario/bajas', { ...filtros, ...cambios }, { preserveState: true, replace: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bajas de bienes" />

            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-semibold">
                            <FileMinus2 className="size-5" />
                            Bajas de bienes
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Mientras la baja está en trámite el bien sigue en la tarjeta y sigue sumando al saldo.
                        </p>
                    </div>

                    {puede('bajas.solicitar') && (
                        <Button onClick={() => setSolicitando(true)}>
                            <Plus className="size-4" />
                            Solicitar baja
                        </Button>
                    )}
                </div>

                <AlertaEstado />

                {/* --- Resumen --- */}
                <div className="grid gap-3 sm:grid-cols-3">
                    <Tarjeta rotulo="En trámite" valor={String(resumen.en_tramite)} />
                    <Tarjeta rotulo="Autorizadas" valor={String(resumen.autorizadas)} />
                    <Tarjeta rotulo="Valor dado de baja" valor={quetzales(resumen.valor_dado_de_baja)} />
                </div>

                {/* --- Filtros --- */}
                <div className="flex flex-wrap items-end gap-3 rounded-lg border p-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="estado" className="text-xs">
                            Estado
                        </Label>
                        <select
                            id="estado"
                            value={filtros.estado}
                            onChange={(e) => filtrar({ estado: e.target.value })}
                            className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="todas">Todas</option>
                            {Object.entries(estados).map(([clave, rotulo]) => (
                                <option key={clave} value={clave}>
                                    {rotulo}
                                </option>
                            ))}
                        </select>
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            filtrar({ buscar });
                        }}
                        className="flex flex-1 items-end gap-2"
                    >
                        <div className="grid flex-1 gap-1.5">
                            <Label htmlFor="buscar" className="text-xs">
                                Buscar
                            </Label>
                            <Input
                                id="buscar"
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Código, descripción, acta o motivo"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            <Search className="size-4" />
                            Buscar
                        </Button>
                    </form>
                </div>

                {/* --- Listado --- */}
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Bien</th>
                                <th className="px-3 py-2 font-medium">A cargo de</th>
                                <th className="px-3 py-2 font-medium">Motivo</th>
                                <th className="px-3 py-2 font-medium">Acta</th>
                                <th className="px-3 py-2 font-medium">Solicitud</th>
                                <th className="px-3 py-2 font-medium">Estado</th>
                                <th className="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {bajas.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-3 py-6 text-center">
                                        No hay bajas que mostrar.
                                    </td>
                                </tr>
                            )}

                            {bajas.data.map((baja) => (
                                <tr key={baja.id} className="border-t align-top">
                                    <td className="px-3 py-2">
                                        <span className="font-mono text-xs">{baja.bien.codigo}</span>
                                        <span className="text-muted-foreground block max-w-sm text-xs">{baja.bien.descripcion}</span>
                                        <span className="block text-xs">{quetzales(baja.bien.total)}</span>
                                    </td>
                                    <td className="text-muted-foreground px-3 py-2 text-xs">
                                        {baja.bien.responsable ?? '—'}
                                        <span className="block">{baja.bien.unidad}</span>
                                    </td>
                                    <td className="max-w-xs px-3 py-2">
                                        {baja.motivo}
                                        {baja.observaciones && <span className="text-muted-foreground block text-xs">{baja.observaciones}</span>}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">{baja.numero_acta ?? '—'}</td>
                                    <td className="px-3 py-2 text-xs whitespace-nowrap">
                                        {baja.fecha_solicitud}
                                        <span className="text-muted-foreground block">{baja.solicitada_por}</span>
                                    </td>
                                    <td className="px-3 py-2">
                                        <span className={`rounded px-2 py-0.5 text-xs font-medium ${COLOR_ESTADO[baja.estado] ?? ''}`}>
                                            {baja.estado_legible}
                                        </span>
                                        {baja.fecha_resolucion && (
                                            <span className="text-muted-foreground block text-xs">
                                                {baja.fecha_resolucion} · {baja.resuelta_por}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                        {baja.estado === 'solicitada' && puede('bajas.autorizar') && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    className="mr-2"
                                                    onClick={() => setResolviendo({ baja, accion: 'autorizar' })}
                                                >
                                                    <Check className="size-4" />
                                                    Autorizar
                                                </Button>
                                                <Button size="sm" variant="ghost" onClick={() => setResolviendo({ baja, accion: 'rechazar' })}>
                                                    <X className="size-4" />
                                                    Rechazar
                                                </Button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {bajas.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {bajas.links.map((enlace, i) => (
                            <Button
                                key={i}
                                size="sm"
                                variant={enlace.active ? 'default' : 'outline'}
                                disabled={!enlace.url}
                                onClick={() => enlace.url && router.get(enlace.url)}
                            >
                                <span dangerouslySetInnerHTML={{ __html: enlace.label }} />
                            </Button>
                        ))}
                    </div>
                )}
            </div>

            <DialogSolicitar
                abierto={solicitando}
                candidatos={candidatos}
                termino={termino}
                onTermino={setTermino}
                onCerrar={() => setSolicitando(false)}
                filtros={filtros}
            />

            <DialogResolver resolviendo={resolviendo} onCerrar={() => setResolviendo(null)} />
        </AppLayout>
    );
}

function Tarjeta({ rotulo, valor }: { rotulo: string; valor: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-muted-foreground text-xs tracking-wider uppercase">{rotulo}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{valor}</p>
        </div>
    );
}

/** Abre el expediente: se busca el bien y se dice por qué se da de baja. */
function DialogSolicitar({
    abierto,
    candidatos,
    termino,
    onTermino,
    onCerrar,
    filtros,
}: {
    abierto: boolean;
    candidatos: Candidato[];
    termino: string;
    onTermino: (v: string) => void;
    onCerrar: () => void;
    filtros: { estado: string; buscar: string };
}) {
    const [elegido, setElegido] = useState<Candidato | null>(null);
    const [motivo, setMotivo] = useState('');
    const [acta, setActa] = useState('');
    const [enviando, setEnviando] = useState(false);

    const buscar = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/inventario/bajas', { ...filtros, bien: termino }, { preserveState: true, replace: true, only: ['candidatos', 'buscar_bien'] });
    };

    const solicitar = () => {
        if (!elegido) return;
        setEnviando(true);

        router.post(
            '/inventario/bajas',
            { bien_id: elegido.id, motivo, numero_acta: acta || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setElegido(null);
                    setMotivo('');
                    setActa('');
                    onCerrar();
                },
                onFinish: () => setEnviando(false),
            },
        );
    };

    return (
        <Dialog open={abierto} onOpenChange={(v) => !v && onCerrar()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Solicitar la baja de un bien</DialogTitle>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <form onSubmit={buscar} className="flex gap-2">
                        <Input value={termino} onChange={(e) => onTermino(e.target.value)} placeholder="Código o descripción del bien" />
                        <Button type="submit" variant="secondary">
                            <Search className="size-4" />
                            Buscar
                        </Button>
                    </form>

                    {candidatos.length > 0 && (
                        <div className="max-h-56 overflow-y-auto rounded-md border">
                            {candidatos.map((bien) => (
                                <button
                                    key={bien.id}
                                    onClick={() => setElegido(bien)}
                                    className={`hover:bg-muted/60 block w-full border-b px-3 py-2 text-left text-sm last:border-b-0 ${
                                        elegido?.id === bien.id ? 'bg-primary/5' : ''
                                    }`}
                                >
                                    <span className="font-mono text-xs">{bien.codigo}</span>
                                    <span className="block text-xs">{bien.descripcion}</span>
                                    <span className="text-muted-foreground block text-xs">
                                        {bien.responsable ?? 'Sin responsable'} · {quetzales(bien.total)}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}

                    {termino !== '' && candidatos.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            No se encontró ningún bien activo con «{termino}». Un bien que ya está dado de baja, o que ya tiene un trámite abierto, no
                            aparece aquí.
                        </p>
                    )}

                    {elegido && (
                        <>
                            <div className="bg-muted/50 rounded-md border p-3 text-sm">
                                <span className="font-mono text-xs">{elegido.codigo}</span>
                                <span className="block">{elegido.descripcion}</span>
                                <span className="text-muted-foreground block text-xs">
                                    {elegido.responsable ? `En la tarjeta de ${elegido.responsable}` : 'Sin responsable asignado'}
                                </span>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="motivo">Motivo</Label>
                                    <Input
                                        id="motivo"
                                        value={motivo}
                                        onChange={(e) => setMotivo(e.target.value)}
                                        placeholder="Por ejemplo: equipo en mal estado, sin reparación posible"
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="acta">Acta</Label>
                                    <Input id="acta" className="w-36" value={acta} onChange={(e) => setActa(e.target.value)} placeholder="77-2025" />
                                </div>
                            </div>

                            <p className="text-muted-foreground text-xs">
                                El bien se queda en la tarjeta y sigue sumando al saldo hasta que alguien autorice la baja.
                            </p>
                        </>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                        <Button onClick={solicitar} disabled={!elegido || motivo.trim().length < 5 || enviando}>
                            Solicitar baja
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Resuelve el expediente: autoriza la baja o la rechaza. */
function DialogResolver({ resolviendo, onCerrar }: { resolviendo: { baja: Baja; accion: 'autorizar' | 'rechazar' } | null; onCerrar: () => void }) {
    const [acta, setActa] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [enviando, setEnviando] = useState(false);

    if (!resolviendo) return null;

    const { baja, accion } = resolviendo;
    const autorizando = accion === 'autorizar';

    const resolver = () => {
        setEnviando(true);

        router.post(
            `/inventario/bajas/${baja.id}/${accion}`,
            autorizando ? { numero_acta: acta || baja.numero_acta, observaciones: observaciones || null } : { observaciones },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setActa('');
                    setObservaciones('');
                    onCerrar();
                },
                onFinish: () => setEnviando(false),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(v) => !v && onCerrar()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {autorizando ? 'Autorizar la baja' : 'Rechazar la baja'} del bien {baja.bien.codigo}
                    </DialogTitle>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <div className="bg-muted/50 rounded-md border p-3 text-sm">
                        <span className="block">{baja.bien.descripcion}</span>
                        <span className="text-muted-foreground block text-xs">
                            {baja.bien.responsable ?? 'Sin responsable'} · {quetzales(baja.bien.total)}
                        </span>
                        <span className="mt-1 block text-xs">Motivo: {baja.motivo}</span>
                    </div>

                    {autorizando ? (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="acta-resolucion">Acta</Label>
                                <Input
                                    id="acta-resolucion"
                                    value={acta || (baja.numero_acta ?? '')}
                                    onChange={(e) => setActa(e.target.value)}
                                    placeholder="77-2025"
                                />
                            </div>

                            <div className="rounded-md border border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                El bien sale de la tarjeta de {baja.bien.responsable ?? 'su responsable'} y deja de sumar al saldo. Se emite una
                                versión nueva de la tarjeta; la anterior queda guardada tal como se firmó.
                            </div>
                        </>
                    ) : (
                        <div className="grid gap-1.5">
                            <Label htmlFor="razon">Razón del rechazo</Label>
                            <textarea
                                id="razon"
                                rows={3}
                                value={observaciones}
                                onChange={(e) => setObservaciones(e.target.value)}
                                className="border-input focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                                placeholder="Queda guardada en el expediente y en la bitácora."
                            />
                            <p className="text-muted-foreground text-xs">El bien vuelve a estar activo en su tarjeta.</p>
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                        <Button onClick={resolver} disabled={enviando || (!autorizando && observaciones.trim().length < 5)}>
                            {autorizando ? 'Autorizar' : 'Rechazar'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
