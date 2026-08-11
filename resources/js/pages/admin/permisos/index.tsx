import { AlertaEstado } from '@/components/alerta-estado';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Info, LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface PermisoFila {
    id: number;
    name: string;
    modulo: string;
    roles: string[];
}

interface Props {
    permisosPorModulo: Record<string, PermisoFila[]>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Permisos', href: '/admin/permisos' },
];

export default function PermisosIndex({ permisosPorModulo }: Props) {
    const { puede } = usePermisos();

    const { data, setData, post, processing, errors, reset } = useForm<{ name: string }>({ name: '' });

    const crear: FormEventHandler = (e) => {
        e.preventDefault();
        post('/admin/permisos', { onSuccess: () => reset('name'), preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Permisos" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-foreground text-xl font-semibold">Permisos</h1>
                    <p className="text-muted-foreground text-sm">
                        Catálogo de acciones que se pueden otorgar a un rol. Los permisos se asignan desde la pantalla de
                        Roles.
                    </p>
                </div>

                <AlertaEstado />

                {puede('permisos.crear') && (
                    <form onSubmit={crear} className="border-border bg-card grid gap-3 rounded-xl border p-6">
                        <Label htmlFor="name">Nuevo permiso</Label>
                        <div className="flex flex-wrap gap-2">
                            <Input
                                id="name"
                                className="max-w-xs font-mono"
                                placeholder="bienes.dar_baja"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value.toLowerCase())}
                                required
                            />
                            <Button type="submit" disabled={processing}>
                                {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Plus className="size-4" />}
                                Agregar
                            </Button>
                        </div>
                        <InputError message={errors.name} />
                        <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Info className="size-3.5" />
                            Escríbalo como <code className="font-mono">modulo.accion</code>, en minúsculas. Así el sistema
                            lo agrupa solo.
                        </p>
                    </form>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    {Object.entries(permisosPorModulo).map(([modulo, permisos]) => (
                        <div key={modulo} className="border-border bg-card rounded-xl border p-5">
                            <p className="text-foreground border-border mb-4 border-b pb-3 font-medium capitalize">
                                {modulo}
                            </p>

                            <ul className="divide-border divide-y">
                                {permisos.map((permiso) => (
                                    <li key={permiso.id} className="flex items-start justify-between gap-3 py-3">
                                        <div>
                                            <code className="text-foreground font-mono text-sm">{permiso.name}</code>
                                            <div className="mt-1.5 flex flex-wrap gap-1">
                                                {permiso.roles.length > 0 ? (
                                                    permiso.roles.map((rol) => (
                                                        <Badge key={rol} variant="secondary" className="text-xs">
                                                            {rol}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">
                                                        Sin roles asignados
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        {puede('permisos.eliminar') && permiso.roles.length === 0 && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    if (window.confirm(`¿Eliminar el permiso ${permiso.name}?`)) {
                                                        router.delete(`/admin/permisos/${permiso.id}`, {
                                                            preserveScroll: true,
                                                        });
                                                    }
                                                }}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
