import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, IdCard, KeyRound, UserCog } from 'lucide-react';

/**
 * El sistema no envia correos de recuperacion. La contrasena la restablece un
 * administrador y esta pantalla existe para explicarle al usuario que hacer.
 */
export default function OlvidePassword() {
    const pasos = [
        {
            icono: UserCog,
            titulo: 'Comuníquese con el administrador',
            detalle: 'Acuda al encargado del sistema de su unidad o al administrador de la DDRISST.',
        },
        {
            icono: IdCard,
            titulo: 'Identifíquese',
            detalle: 'Indique su nombre de usuario y sus datos para que el administrador confirme su identidad.',
        },
        {
            icono: KeyRound,
            titulo: 'Reciba su contraseña temporal',
            detalle: 'El administrador le entregará una contraseña temporal. El sistema le pedirá cambiarla al ingresar.',
        },
    ];

    return (
        <AuthLayout
            title="¿Olvidó su contraseña?"
            description="Por seguridad, este sistema no envía correos de recuperación: la contraseña la restablece únicamente un administrador."
        >
            <Head title="Recuperar contraseña" />

            <ol className="space-y-4">
                {pasos.map((paso, indice) => (
                    <li key={paso.titulo} className="border-border bg-muted/40 flex gap-4 rounded-xl border p-4">
                        <div className="bg-mspas-navy dark:bg-mspas-cyan flex size-9 shrink-0 items-center justify-center rounded-lg text-white dark:text-neutral-900">
                            <paso.icono className="size-4.5" />
                        </div>
                        <div>
                            <p className="text-foreground text-sm font-medium">
                                {indice + 1}. {paso.titulo}
                            </p>
                            <p className="text-muted-foreground mt-1 text-sm leading-relaxed">{paso.detalle}</p>
                        </div>
                    </li>
                ))}
            </ol>

            <Button asChild variant="outline" size="lg" className="mt-8 w-full">
                <Link href={route('login')}>
                    <ArrowLeft className="size-4" />
                    Volver al inicio de sesión
                </Link>
            </Button>
        </AuthLayout>
    );
}
