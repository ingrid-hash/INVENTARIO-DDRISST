import { Paginacion } from '@/components/paginacion';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginado } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';

interface RegistroHistorial {
    id: number;
    motivo: string;
    autor: string;
    ip: string | null;
    fecha: string;
}

interface Props {
    usuario: { id: number; username: string; name: string };
    historial: Paginado<RegistroHistorial>;
}

export default function HistorialPasswords({ usuario, historial }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Usuarios', href: '/admin/usuarios' },
        { title: 'Historial de contraseñas', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Historial · ${usuario.username}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="icon">
                        <Link href="/admin/usuarios">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">Historial de contraseñas</h1>
                        <p className="text-muted-foreground text-sm">
                            {usuario.name} · {usuario.username}
                        </p>
                    </div>
                </div>

                <div className="border-border text-muted-foreground flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    Aquí se registra cada cambio y quién lo hizo. Las contraseñas nunca se guardan en texto legible: solo
                    se conserva su huella cifrada, que sirve para impedir que el usuario reutilice una anterior.
                </div>

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Fecha</th>
                                <th className="px-4 py-3 font-medium">Motivo</th>
                                <th className="px-4 py-3 font-medium">Realizado por</th>
                                <th className="px-4 py-3 font-medium">Dirección IP</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {historial.data.map((registro) => (
                                <tr key={registro.id} className="hover:bg-muted/30">
                                    <td className="text-foreground px-4 py-3">{registro.fecha}</td>
                                    <td className="px-4 py-3">{registro.motivo}</td>
                                    <td className="px-4 py-3">{registro.autor}</td>
                                    <td className="text-muted-foreground px-4 py-3 font-mono text-xs">
                                        {registro.ip ?? '—'}
                                    </td>
                                </tr>
                            ))}

                            {historial.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground px-4 py-10 text-center">
                                        Este usuario todavía no tiene cambios de contraseña registrados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Paginacion pagina={historial} />
            </div>
        </AppLayout>
    );
}
