import { AlertaEstado } from '@/components/alerta-estado';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';

interface RolFila {
    id: number;
    name: string;
    permisos: number;
    usuarios: number;
    protegido: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Roles', href: '/admin/roles' },
];

export default function RolesIndex({ roles }: { roles: RolFila[] }) {
    const { puede } = usePermisos();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">Roles</h1>
                        <p className="text-muted-foreground text-sm">
                            Cada rol agrupa los permisos que sus usuarios pueden ejercer.
                        </p>
                    </div>

                    {puede('roles.crear') && (
                        <Button asChild>
                            <Link href="/admin/roles/crear">
                                <Plus className="size-4" />
                                Nuevo rol
                            </Link>
                        </Button>
                    )}
                </div>

                <AlertaEstado />

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {roles.map((rol) => (
                        <div key={rol.id} className="border-border bg-card flex flex-col gap-4 rounded-xl border p-5">
                            <div className="flex items-start justify-between gap-2">
                                <div className="bg-mspas-cyan-light text-mspas-navy dark:bg-mspas-navy dark:text-mspas-cyan flex size-10 items-center justify-center rounded-lg">
                                    <ShieldCheck className="size-5" />
                                </div>

                                {rol.protegido && (
                                    <Badge variant="secondary" className="gap-1">
                                        <Lock className="size-3" />
                                        Del sistema
                                    </Badge>
                                )}
                            </div>

                            <div>
                                <p className="text-foreground font-medium">{rol.name}</p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {rol.permisos} permiso(s) · {rol.usuarios} usuario(s)
                                </p>
                            </div>

                            <div className="mt-auto flex gap-2">
                                {puede('roles.editar') && (
                                    <Button asChild variant="outline" size="sm" className="flex-1">
                                        <Link href={`/admin/roles/${rol.id}/editar`}>
                                            <Pencil className="size-4" />
                                            Permisos
                                        </Link>
                                    </Button>
                                )}

                                {puede('roles.eliminar') && !rol.protegido && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => {
                                            if (window.confirm(`¿Eliminar el rol ${rol.name}?`)) {
                                                router.delete(`/admin/roles/${rol.id}`);
                                            }
                                        }}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
