import { AlertaEstado } from '@/components/alerta-estado';
import { Paginacion } from '@/components/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginado, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Copy, History, KeyRound, LockOpen, Pencil, Plus, Power, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface UsuarioFila {
    id: number;
    username: string;
    name: string;
    email: string;
    unidad: string | null;
    activo: boolean;
    bloqueado: boolean;
    debe_cambiar_password: boolean;
    ultimo_acceso_en: string | null;
    rol: string | null;
}

interface Props {
    usuarios: Paginado<UsuarioFila>;
    filtros: { buscar: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel principal', href: '/dashboard' },
    { title: 'Usuarios', href: '/admin/usuarios' },
];

export default function UsuariosIndex({ usuarios, filtros }: Props) {
    const { puede } = usePermisos();
    const { flash } = usePage<SharedData>().props;
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    const buscarUsuarios = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/usuarios', { buscar }, { preserveState: true, replace: true });
    };

    const confirmarYEnviar = (mensaje: string, accion: () => void) => {
        if (window.confirm(mensaje)) {
            accion();
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuarios" />

            <DialogPasswordTemporal />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">Usuarios</h1>
                        <p className="text-muted-foreground text-sm">Cuentas con acceso al sistema de inventario.</p>
                    </div>

                    {puede('usuarios.crear') && (
                        <Button asChild>
                            <Link href="/admin/usuarios/crear">
                                <Plus className="size-4" />
                                Nuevo usuario
                            </Link>
                        </Button>
                    )}
                </div>

                <AlertaEstado />

                <form onSubmit={buscarUsuarios} className="flex max-w-md gap-2">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            className="pl-10"
                            placeholder="Buscar por nombre, usuario o correo"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </form>

                <div className="border-border bg-card overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Usuario</th>
                                <th className="px-4 py-3 font-medium">Rol</th>
                                <th className="px-4 py-3 font-medium">Estado</th>
                                <th className="px-4 py-3 font-medium">Último acceso</th>
                                <th className="px-4 py-3 text-right font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {usuarios.data.map((usuario) => (
                                <tr key={usuario.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <p className="text-foreground font-medium">{usuario.name}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {usuario.username} · {usuario.email}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">{usuario.rol ?? 'Sin rol'}</Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            <EstadoUsuario usuario={usuario} />
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {usuario.ultimo_acceso_en ?? 'Nunca ha ingresado'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            {puede('usuarios.ver') && (
                                                <Button asChild variant="ghost" size="icon" title="Historial de contraseñas">
                                                    <Link href={`/admin/usuarios/${usuario.id}/historial`}>
                                                        <History className="size-4" />
                                                    </Link>
                                                </Button>
                                            )}

                                            {puede('usuarios.resetear_password') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Restablecer contraseña"
                                                    onClick={() =>
                                                        confirmarYEnviar(
                                                            `¿Restablecer la contraseña de ${usuario.username}? Se generará una contraseña temporal y el usuario deberá cambiarla al ingresar.`,
                                                            () =>
                                                                router.patch(
                                                                    `/admin/usuarios/${usuario.id}/resetear-password`,
                                                                    {},
                                                                    { preserveScroll: true },
                                                                ),
                                                        )
                                                    }
                                                >
                                                    <KeyRound className="size-4" />
                                                </Button>
                                            )}

                                            {puede('usuarios.editar') && usuario.bloqueado && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Desbloquear cuenta"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/admin/usuarios/${usuario.id}/desbloquear`,
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <LockOpen className="size-4" />
                                                </Button>
                                            )}

                                            {puede('usuarios.editar') && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title={usuario.activo ? 'Desactivar' : 'Activar'}
                                                        onClick={() =>
                                                            confirmarYEnviar(
                                                                `¿${usuario.activo ? 'Desactivar' : 'Activar'} la cuenta de ${usuario.username}?`,
                                                                () =>
                                                                    router.patch(
                                                                        `/admin/usuarios/${usuario.id}/estado`,
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    ),
                                                            )
                                                        }
                                                    >
                                                        <Power className="size-4" />
                                                    </Button>

                                                    <Button asChild variant="ghost" size="icon" title="Editar">
                                                        <Link href={`/admin/usuarios/${usuario.id}/editar`}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                </>
                                            )}

                                            {puede('usuarios.eliminar') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Eliminar"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        confirmarYEnviar(
                                                            `¿Eliminar al usuario ${usuario.username}? Dejará de tener acceso, pero su rastro se conserva en la bitácora.`,
                                                            () => router.delete(`/admin/usuarios/${usuario.id}`),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {usuarios.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-4 py-10 text-center">
                                        No se encontraron usuarios con ese criterio.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Paginacion pagina={usuarios} />
            </div>
        </AppLayout>
    );

    function EstadoUsuario({ usuario }: { usuario: UsuarioFila }) {
        if (!usuario.activo) {
            return <Badge variant="destructive">Inactivo</Badge>;
        }

        return (
            <>
                <Badge className="bg-mspas-teal text-white">Activo</Badge>
                {usuario.bloqueado && <Badge className="bg-mspas-rose text-white">Bloqueado</Badge>}
                {usuario.debe_cambiar_password && (
                    <Badge className="bg-mspas-amber text-neutral-900">Debe cambiar contraseña</Badge>
                )}
            </>
        );
    }

    function DialogPasswordTemporal() {
        const temporal = flash?.passwordTemporal;
        const [abierto, setAbierto] = useState(Boolean(temporal));
        const [copiado, setCopiado] = useState(false);

        if (!temporal) {
            return null;
        }

        return (
            <Dialog open={abierto} onOpenChange={setAbierto}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Contraseña temporal generada</DialogTitle>
                        <DialogDescription>
                            Entréguesela a <strong>{temporal.usuario}</strong> en persona o por teléfono. No se volverá a
                            mostrar y el usuario deberá cambiarla en su próximo ingreso.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="border-border bg-muted flex items-center justify-between gap-3 rounded-lg border p-4">
                        <code className="text-foreground font-mono text-lg tracking-wider">{temporal.password}</code>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                navigator.clipboard.writeText(temporal.password);
                                setCopiado(true);
                            }}
                        >
                            <Copy className="size-4" />
                            {copiado ? 'Copiada' : 'Copiar'}
                        </Button>
                    </div>

                    <Button onClick={() => setAbierto(false)}>Ya la anoté</Button>
                </DialogContent>
            </Dialog>
        );
    }
}
