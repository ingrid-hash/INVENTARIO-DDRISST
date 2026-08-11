import { Paginacion } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginado } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';

interface RegistroBitacora {
    id: number;
    evento: string;
    descripcion: string | null;
    usuario: string;
    ip: string | null;
    fecha: string;
}

interface Props {
    registros: Paginado<RegistroBitacora>;
    eventos: string[];
    filtros: { evento: string; buscar: string };
}

const TODOS = 'todos';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Bitácora', href: '/admin/bitacora' },
];

/** Los eventos que delatan un problema se pintan distinto. */
function colorEvento(evento: string) {
    if (evento.startsWith('acceso.fallido') || evento.includes('bloqueada') || evento.includes('inactiva')) {
        return 'bg-mspas-rose/10 text-mspas-rose border-mspas-rose/25';
    }

    if (evento.startsWith('acceso.exitoso')) {
        return 'bg-mspas-teal/10 text-mspas-teal border-mspas-teal/25';
    }

    if (evento.startsWith('password.')) {
        return 'bg-mspas-amber/10 text-mspas-amber border-mspas-amber/30';
    }

    return 'bg-muted text-muted-foreground border-border';
}

export default function BitacoraIndex({ registros, eventos, filtros }: Props) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    const filtrar = (cambios: Partial<{ evento: string; buscar: string }>) => {
        router.get(
            '/admin/bitacora',
            { evento: filtros.evento, buscar, ...cambios },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bitácora" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-foreground text-xl font-semibold">Bitácora del sistema</h1>
                    <p className="text-muted-foreground text-sm">
                        Accesos, intentos fallidos, bloqueos, cambios de contraseña y movimientos de usuarios y roles.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            filtrar({});
                        }}
                        className="flex max-w-md flex-1 gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                className="pl-10"
                                placeholder="Buscar por descripción, correo o IP"
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Buscar
                        </Button>
                    </form>

                    <Select
                        value={filtros.evento || TODOS}
                        onValueChange={(valor) => filtrar({ evento: valor === TODOS ? '' : valor })}
                    >
                        <SelectTrigger className="w-60">
                            <SelectValue placeholder="Todos los eventos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>Todos los eventos</SelectItem>
                            {eventos.map((evento) => (
                                <SelectItem key={evento} value={evento}>
                                    {evento}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Fecha</th>
                                <th className="px-4 py-3 font-medium">Evento</th>
                                <th className="px-4 py-3 font-medium">Descripción</th>
                                <th className="px-4 py-3 font-medium">Usuario</th>
                                <th className="px-4 py-3 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {registros.data.map((registro) => (
                                <tr key={registro.id} className="hover:bg-muted/30">
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                        {registro.fecha}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline" className={cn('font-mono text-xs', colorEvento(registro.evento))}>
                                            {registro.evento}
                                        </Badge>
                                    </td>
                                    <td className="text-foreground px-4 py-3">{registro.descripcion ?? '—'}</td>
                                    <td className="px-4 py-3">{registro.usuario}</td>
                                    <td className="text-muted-foreground px-4 py-3 font-mono text-xs">
                                        {registro.ip ?? '—'}
                                    </td>
                                </tr>
                            ))}

                            {registros.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-4 py-10 text-center">
                                        No hay registros que coincidan con el filtro.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Paginacion pagina={registros} />
            </div>
        </AppLayout>
    );
}
