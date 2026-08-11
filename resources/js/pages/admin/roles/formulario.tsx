import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Lock } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    rol: { id: number; name: string; protegido: boolean } | null;
    /** Permisos disponibles, ya agrupados por módulo por el backend. */
    permisos: Record<string, string[]>;
    asignados: string[];
}

type RolForm = {
    name: string;
    permisos: string[];
};

/** Etiqueta legible para la acción que va después del punto: bienes.dar_baja. */
const ACCIONES: Record<string, string> = {
    ver: 'Ver',
    crear: 'Crear',
    editar: 'Editar',
    eliminar: 'Eliminar',
    resetear_password: 'Restablecer contraseña',
    dar_baja: 'Dar de baja',
    exportar: 'Exportar',
};

export default function RolFormulario({ rol, permisos, asignados }: Props) {
    const editando = rol !== null;

    const { data, setData, post, put, processing, errors } = useForm<RolForm>({
        name: rol?.name ?? '',
        permisos: asignados,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel principal', href: '/dashboard' },
        { title: 'Roles', href: '/admin/roles' },
        { title: editando ? `Permisos de ${rol.name}` : 'Nuevo rol', href: '#' },
    ];

    const alternar = (permiso: string) => {
        setData(
            'permisos',
            data.permisos.includes(permiso)
                ? data.permisos.filter((p) => p !== permiso)
                : [...data.permisos, permiso],
        );
    };

    const alternarModulo = (permisosDelModulo: string[]) => {
        const todosPuestos = permisosDelModulo.every((p) => data.permisos.includes(p));

        setData(
            'permisos',
            todosPuestos
                ? data.permisos.filter((p) => !permisosDelModulo.includes(p))
                : [...new Set([...data.permisos, ...permisosDelModulo])],
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editando) {
            put(`/admin/roles/${rol.id}`);
        } else {
            post('/admin/roles');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={editando ? `Permisos de ${rol.name}` : 'Nuevo rol'} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Button asChild variant="ghost" size="icon">
                        <Link href="/admin/roles">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-foreground text-xl font-semibold">
                            {editando ? `Permisos del rol ${rol.name}` : 'Nuevo rol'}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Marque lo que este rol puede hacer en cada módulo del sistema.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="border-border bg-card grid max-w-md gap-2 rounded-xl border p-6">
                        <Label htmlFor="name">Nombre del rol</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={rol?.protegido}
                            required
                        />
                        {rol?.protegido && (
                            <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <Lock className="size-3" />
                                Es un rol base del sistema: su nombre no se puede cambiar, solo sus permisos.
                            </p>
                        )}
                        <InputError message={errors.name} />
                    </div>

                    <InputError message={errors.permisos} />

                    <div className="grid gap-4 md:grid-cols-2">
                        {Object.entries(permisos).map(([modulo, listado]) => {
                            const todosPuestos = listado.every((p) => data.permisos.includes(p));

                            return (
                                <div key={modulo} className="border-border bg-card rounded-xl border p-5">
                                    <div className="border-border mb-4 flex items-center justify-between border-b pb-3">
                                        <p className="text-foreground font-medium capitalize">{modulo}</p>
                                        <button
                                            type="button"
                                            onClick={() => alternarModulo(listado)}
                                            className="text-mspas-cyan text-xs font-medium hover:underline"
                                        >
                                            {todosPuestos ? 'Quitar todos' : 'Marcar todos'}
                                        </button>
                                    </div>

                                    <div className="grid gap-3">
                                        {listado.map((permiso) => {
                                            const accion = permiso.split('.')[1] ?? permiso;

                                            return (
                                                <div key={permiso} className="flex items-center gap-3">
                                                    <Checkbox
                                                        id={permiso}
                                                        checked={data.permisos.includes(permiso)}
                                                        onClick={() => alternar(permiso)}
                                                    />
                                                    <Label htmlFor={permiso} className="font-normal">
                                                        {ACCIONES[accion] ?? accion}
                                                        <span className="text-muted-foreground ml-1 text-xs">
                                                            ({permiso})
                                                        </span>
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button asChild variant="outline" type="button">
                            <Link href="/admin/roles">Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            Guardar permisos
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
