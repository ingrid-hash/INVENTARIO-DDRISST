import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, Lock, User } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type LoginForm = {
    login: string;
    password: string;
    remember: boolean;
};

export default function Login({ status }: { status?: string }) {
    const [verPassword, setVerPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        login: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Inicio de sesión" description="Ingrese sus credenciales institucionales para acceder al sistema.">
            <Head title="Inicio de sesión" />

            {status && (
                <div className="border-mspas-teal/30 bg-mspas-teal/10 text-mspas-teal mb-6 rounded-lg border px-4 py-3 text-sm font-medium">
                    {status}
                </div>
            )}

            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="login">Usuario o correo electrónico</Label>
                    <div className="relative">
                        <User className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            id="login"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            className="pl-10"
                            value={data.login}
                            onChange={(e) => setData('login', e.target.value)}
                            placeholder="jperez  ·  jperez@mspas.gob.gt"
                        />
                    </div>
                    <InputError message={errors.login} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password">Contraseña</Label>
                        <TextLink href={route('password.ayuda')} className="text-xs" tabIndex={5}>
                            ¿Olvidó su contraseña?
                        </TextLink>
                    </div>
                    <div className="relative">
                        <Lock className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            id="password"
                            type={verPassword ? 'text' : 'password'}
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            className="px-10"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            tabIndex={-1}
                            onClick={() => setVerPassword((v) => !v)}
                            aria-label={verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                            className="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 focus-visible:ring-2 focus-visible:outline-none"
                        >
                            {verPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </button>
                    </div>
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="remember"
                        name="remember"
                        tabIndex={3}
                        checked={data.remember}
                        onClick={() => setData('remember', !data.remember)}
                    />
                    <Label htmlFor="remember" className="text-muted-foreground font-normal">
                        Mantener la sesión iniciada en este equipo
                    </Label>
                </div>

                <Button type="submit" size="lg" className="mt-2 w-full" tabIndex={4} disabled={processing}>
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    Iniciar sesión
                </Button>
            </form>

            <p className="text-muted-foreground mt-8 text-center text-xs leading-relaxed">
                El sistema no permite el auto registro. Las cuentas las crea el administrador del sistema.
            </p>
        </AuthLayout>
    );
}
