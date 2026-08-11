import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, LoaderCircle, ShieldAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

type CambioForm = {
    password: string;
    password_confirmation: string;
};

/**
 * Pantalla que bloquea el sistema hasta que el usuario define una contrasena
 * propia. Aparece tras el primer ingreso y tras un restablecimiento.
 */
export default function CambioObligatorio() {
    const { data, setData, post, processing, errors, reset } = useForm<CambioForm>({
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.forzado.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title="Debe cambiar su contraseña"
            description="Su contraseña actual fue asignada por un administrador. Defina una contraseña personal para continuar."
        >
            <Head title="Cambio de contraseña obligatorio" />

            <div className="border-mspas-amber/40 bg-mspas-amber/10 mb-6 flex gap-3 rounded-xl border p-4">
                <ShieldAlert className="text-mspas-amber mt-0.5 size-5 shrink-0" />
                <p className="text-foreground/80 text-sm leading-relaxed">
                    Nadie más debe conocer su contraseña, ni siquiera el administrador. Mientras no la cambie, no podrá
                    usar el sistema.
                </p>
            </div>

            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="password">Nueva contraseña</Label>
                    <div className="relative">
                        <KeyRound className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            id="password"
                            type="password"
                            required
                            autoFocus
                            autoComplete="new-password"
                            className="pl-10"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </div>
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirme la nueva contraseña</Label>
                    <div className="relative">
                        <KeyRound className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            autoComplete="new-password"
                            className="pl-10"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </div>
                    <InputError message={errors.password_confirmation} />
                </div>

                <RequisitosPassword />

                <Button type="submit" size="lg" className="mt-2 w-full" disabled={processing}>
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    Guardar y continuar
                </Button>
            </form>
        </AuthLayout>
    );
}

export function RequisitosPassword() {
    const requisitos = [
        'Al menos 10 caracteres',
        'Mayúsculas y minúsculas',
        'Al menos un número',
        'Al menos un símbolo (! @ # $ %)',
        'Distinta a sus últimas 5 contraseñas',
    ];

    return (
        <ul className="text-muted-foreground grid gap-1.5 text-xs sm:grid-cols-2">
            {requisitos.map((requisito) => (
                <li key={requisito} className="flex items-center gap-2">
                    <span className="bg-mspas-cyan size-1.5 shrink-0 rounded-full" />
                    {requisito}
                </li>
            ))}
        </ul>
    );
}
