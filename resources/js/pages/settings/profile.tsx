import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mi cuenta', href: '/settings/profile' }];

export default function Profile() {
    const { auth } = usePage<SharedData>().props;
    const usuario = auth.user!;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: usuario.name,
        telefono: usuario.telefono ?? '',
        puesto: usuario.puesto ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi cuenta" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Mis datos" description="Actualice su nombre y sus datos de contacto." />

                    <div className="border-border bg-muted/40 grid gap-3 rounded-lg border p-4 sm:grid-cols-3">
                        <Dato etiqueta="Usuario" valor={usuario.username} />
                        <Dato etiqueta="Correo electrónico" valor={usuario.email} />
                        <div>
                            <p className="text-muted-foreground text-xs">Rol</p>
                            <Badge variant="secondary" className="mt-1">
                                {usuario.rol ?? 'Sin rol'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground sm:col-span-3 text-xs leading-relaxed">
                            El usuario, el correo y el rol solo los puede modificar un administrador del sistema.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre completo</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="puesto">Puesto</Label>
                            <Input id="puesto" value={data.puesto} onChange={(e) => setData('puesto', e.target.value)} />
                            <InputError message={errors.puesto} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="telefono">Teléfono</Label>
                            <Input
                                id="telefono"
                                value={data.telefono}
                                onChange={(e) => setData('telefono', e.target.value)}
                            />
                            <InputError message={errors.telefono} />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Guardar</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-mspas-teal text-sm font-medium">Guardado</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div>
            <p className="text-muted-foreground text-xs">{etiqueta}</p>
            <p className="text-foreground mt-1 text-sm font-medium">{valor}</p>
        </div>
    );
}
