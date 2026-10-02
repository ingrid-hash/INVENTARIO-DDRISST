import { AlertaEstado } from '@/components/alerta-estado';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowRightLeft, Search } from 'lucide-react';
import { useState } from 'react';

interface Candidato {
    id: number;
    codigo: string;
    descripcion: string;
    total: number;
    unidad: string | null;
    responsable: string | null;
}

interface Empleado {
    id: number;
    nombre_completo: string;
    cargo: string | null;
}

interface Reciente {
    id: number;
    codigo: string | null;
    descripcion: string | null;
    de: string | null;
    a: string | null;
    fecha: string | null;
    observaciones: string | null;
}

interface Props {
    buscar: string;
    candidatos: Candidato[];
    empleados: Empleado[];
    recientes: Reciente[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventario', href: '/inventario/bienes' },
    { title: 'Traslados', href: '/inventario/traslados' },
];

const quetzales = (valor: number) => `Q ${valor.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function Traslados({ buscar, candidatos, empleados, recientes }: Props) {
    const { puede } = usePermisos();

    const [termino, setTermino] = useState(buscar);
    const [elegido, setElegido] = useState<Candidato | null>(null);
    const [destino, setDestino] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [enviando, setEnviando] = useState(false);

    const buscarBienes = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/inventario/traslados', { bien: termino }, { preserveState: true, replace: true, only: ['candidatos', 'buscar'] });
    };

    const trasladar = () => {
        if (!elegido || destino === '') return;
        setEnviando(true);

        router.post(
            '/inventario/traslados',
            { bien_id: elegido.id, empleado_id: Number(destino), observaciones: observaciones || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setElegido(null);
                    setDestino('');
                    setObservaciones('');
                },
                onFinish: () => setEnviando(false),
            },
        );
    };

    const empleadoDestino = empleados.find((e) => String(e.id) === destino);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Traslados de bienes" />

            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-semibold">
                        <ArrowRightLeft className="size-5" />
                        Traslado de bienes
                    </h1>
                    <p className="text-muted-foreground text-sm">El bien pasa a la tarjeta de otra persona, como una adición más.</p>
                </div>

                <AlertaEstado />

                {/* --- Elegir el bien --- */}
                <div className="rounded-lg border p-4">
                    <Label htmlFor="bien" className="mb-2 block">
                        Qué bien se traslada
                    </Label>

                    <form onSubmit={buscarBienes} className="flex max-w-2xl gap-2">
                        <Input id="bien" value={termino} onChange={(e) => setTermino(e.target.value)} placeholder="Código o descripción" />
                        <Button type="submit" variant="secondary">
                            <Search className="size-4" />
                            Buscar
                        </Button>
                    </form>

                    {buscar !== '' && candidatos.length === 0 && (
                        <p className="text-muted-foreground mt-3 text-sm">
                            No se encontró ningún bien activo con «{buscar}». Un bien dado de baja no se puede trasladar.
                        </p>
                    )}

                    {candidatos.length > 0 && (
                        <div className="mt-3 max-h-64 overflow-y-auto rounded-md border">
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
                </div>

                {/* --- A quién --- */}
                {elegido && (
                    <div className="ring-primary/30 flex flex-col gap-4 rounded-lg p-4 ring-2">
                        <div className="grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                            <div className="rounded-md border p-3 text-sm">
                                <span className="text-muted-foreground text-xs">Lo tiene</span>
                                <span className="block font-medium">{elegido.responsable ?? 'Nadie'}</span>
                                <span className="text-muted-foreground block text-xs">
                                    {elegido.codigo} · {quetzales(elegido.total)}
                                </span>
                            </div>

                            <ArrowRightLeft className="text-muted-foreground mx-auto size-5" />

                            <div className="grid gap-1.5">
                                <Label htmlFor="destino">Pasa a</Label>
                                <select
                                    id="destino"
                                    value={destino}
                                    onChange={(e) => setDestino(e.target.value)}
                                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                                >
                                    <option value="">Elija el empleado</option>
                                    {empleados
                                        .filter((e) => e.nombre_completo !== elegido.responsable)
                                        .map((e) => (
                                            <option key={e.id} value={e.id}>
                                                {e.nombre_completo}
                                                {e.cargo ? ` · ${e.cargo}` : ''}
                                            </option>
                                        ))}
                                </select>
                                <span className="text-muted-foreground text-xs">Solo aparecen los empleados con tarjeta vigente.</span>
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="observaciones">Motivo</Label>
                            <Input
                                id="observaciones"
                                value={observaciones}
                                onChange={(e) => setObservaciones(e.target.value)}
                                placeholder="Por ejemplo: cambio de servicio"
                            />
                        </div>

                        {empleadoDestino && (
                            <div className="rounded-md border border-amber-500/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                El bien entra a la tarjeta de {empleadoDestino.nombre_completo} como una adición: hay que imprimir lo nuevo en esa
                                tarjeta.
                                {elegido.responsable && (
                                    <>
                                        {' '}
                                        En la hoja de {elegido.responsable} va a seguir apareciendo hasta que esa tarjeta se regenere, porque ahí está
                                        escrito y firmado.
                                    </>
                                )}
                            </div>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" onClick={() => setElegido(null)}>
                                Cancelar
                            </Button>
                            <Button onClick={trasladar} disabled={destino === '' || enviando || !puede('asignaciones.crear')}>
                                Trasladar
                            </Button>
                        </div>
                    </div>
                )}

                {/* --- Últimos traslados --- */}
                {recientes.length > 0 && (
                    <div className="rounded-lg border p-4">
                        <Label className="mb-3 block">Últimos traslados</Label>

                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Bien</th>
                                        <th className="px-3 py-2 font-medium">De</th>
                                        <th className="px-3 py-2 font-medium">A</th>
                                        <th className="px-3 py-2 font-medium">Fecha</th>
                                        <th className="px-3 py-2 font-medium">Motivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recientes.map((t) => (
                                        <tr key={t.id} className="border-t align-top">
                                            <td className="px-3 py-2">
                                                <span className="font-mono text-xs">{t.codigo}</span>
                                                <span className="text-muted-foreground block max-w-xs text-xs">{t.descripcion}</span>
                                            </td>
                                            <td className="px-3 py-2">{t.de ?? '—'}</td>
                                            <td className="px-3 py-2">{t.a ?? '—'}</td>
                                            <td className="px-3 py-2 whitespace-nowrap">{t.fecha}</td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{t.observaciones ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
