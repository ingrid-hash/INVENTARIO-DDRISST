import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { RequisitosPassword } from '@/pages/auth/cambio-obligatorio';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info, LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface UsuarioEdicion {
    id: number;
    username: string;
    name: string;
    email: string;
    dpi: string | null;
    puesto: string | null;
    unidad: string | null;
    telefono: string | null;
    activo: boolean;
    rol: string | null;
}

interface Props {
    usuario: UsuarioEdicion | null;
    roles: string[];
    passwordSugerida: string | null;
}

type UsuarioForm = {
    username: string;
    name: string;
    email: string;
    dpi: string;
    puesto: string;
    unidad: string;
    telefono: string;
    rol: string;
    activo: boolean;
    password: string;
    password_confirmation: string;
};

export default function UsuarioFormulario({ usuario, roles, passwordSugerida }: Props) {
    const editando = usuario !== null;

    const { data, setData, post, put, processing, errors } = useForm<UsuarioForm>({
        username: usuario?.username ?? '',
        name: usuario?.name ?? '',
        email: usuario?.email ?? '',
        dpi: usuario?.dpi ?? '',
        puesto: usuario?.puesto ?? '',
        unidad: usuario?.unidad ?? '',
        telefono: usuario?.telefono ?? '',
        rol: usuario?.rol ?? '',
        activo: usuario?.activo ?? true,
        password: passwordSugerida ?? '',
        password_confirmation: passwordSugerida ?? '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Usuarios', href: '/admin/usuarios' },
        { title: editando ? 'Editar usuario' : 'Nuevo usuario', href: '#' },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editando) {
            put(`/admin/usuarios/${usuario.id}`);
        } else {
            post('/admin/usuarios');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editando ? 'Editar usuario' : 'Nuevo usuario'} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="icon">
                        <Link href="/admin/usuarios">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">
                            {editando ? `Editar a ${usuario.name}` : 'Nuevo usuario'}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {editando
                                ? 'Los cambios quedan registrados en la bitácora.'
                                : 'La cuenta se crea con una contraseña inicial que el usuario deberá cambiar al ingresar.'}
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="border-border bg-card grid max-w-3xl gap-5 rounded-xl border p-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Campo id="username" etiqueta="Nombre de usuario" error={errors.username}>
                            <Input
                                id="username"
                                value={data.username}
                                onChange={(e) => setData('username', e.target.value)}
                                placeholder="jperez"
                                required
                            />
                        </Campo>

                        <Campo id="name" etiqueta="Nombre completo" error={errors.name}>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Juan Pérez López"
                                required
                            />
                        </Campo>

                        <Campo id="email" etiqueta="Correo electrónico" error={errors.email}>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value.toLowerCase())}
                                placeholder="jperez@mspas.gob.gt"
                                required
                            />
                        </Campo>

                        <Campo id="dpi" etiqueta="DPI (opcional)" error={errors.dpi}>
                            <Input
                                id="dpi"
                                value={data.dpi}
                                onChange={(e) => setData('dpi', e.target.value)}
                                placeholder="13 dígitos"
                                maxLength={13}
                            />
                        </Campo>

                        <Campo id="puesto" etiqueta="Puesto (opcional)" error={errors.puesto}>
                            <Input id="puesto" value={data.puesto} onChange={(e) => setData('puesto', e.target.value)} />
                        </Campo>

                        <Campo id="unidad" etiqueta="Unidad o centro de salud (opcional)" error={errors.unidad}>
                            <Input id="unidad" value={data.unidad} onChange={(e) => setData('unidad', e.target.value)} />
                        </Campo>

                        <Campo id="telefono" etiqueta="Teléfono (opcional)" error={errors.telefono}>
                            <Input
                                id="telefono"
                                value={data.telefono}
                                onChange={(e) => setData('telefono', e.target.value)}
                            />
                        </Campo>

                        <Campo id="rol" etiqueta="Rol" error={errors.rol}>
                            <Select value={data.rol} onValueChange={(valor) => setData('rol', valor)}>
                                <SelectTrigger id="rol">
                                    <SelectValue placeholder="Seleccione un rol" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((rol) => (
                                        <SelectItem key={rol} value={rol}>
                                            {rol}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Campo>
                    </div>

                    {editando ? (
                        <>
                            <Campo id="activo" etiqueta="Estado de la cuenta" error={errors.activo}>
                                <Select
                                    value={data.activo ? 'activo' : 'inactivo'}
                                    onValueChange={(valor) => setData('activo', valor === 'activo')}
                                >
                                    <SelectTrigger id="activo">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="activo">Activa</SelectItem>
                                        <SelectItem value="inactivo">Inactiva (sin acceso)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </Campo>

                            <p className="text-muted-foreground border-border flex gap-2 rounded-lg border border-dashed p-3 text-xs leading-relaxed">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                La contraseña no se edita desde aquí. Use el botón <strong>Restablecer contraseña</strong>{' '}
                                en el listado de usuarios: el sistema genera una temporal y obliga al usuario a cambiarla.
                            </p>
                        </>
                    ) : (
                        <div className="border-border grid gap-5 rounded-lg border border-dashed p-4">
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                El sistema ya propuso una contraseña inicial segura. Anótela y entréguesela al usuario, o
                                escriba otra.
                            </p>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <Campo id="password" etiqueta="Contraseña inicial" error={errors.password}>
                                    <Input
                                        id="password"
                                        type="text"
                                        className="font-mono"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        required
                                    />
                                </Campo>

                                <Campo id="password_confirmation" etiqueta="Confirme la contraseña" error={errors.password_confirmation}>
                                    <Input
                                        id="password_confirmation"
                                        type="text"
                                        className="font-mono"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        required
                                    />
                                </Campo>
                            </div>

                            <RequisitosPassword />
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button asChild variant="outline" type="button">
                            <Link href="/admin/usuarios">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {editando ? 'Guardar cambios' : 'Crear usuario'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Campo({
    id,
    etiqueta,
    error,
    children,
}: {
    id: string;
    etiqueta: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{etiqueta}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
