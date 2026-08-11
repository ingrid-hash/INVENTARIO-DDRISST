import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    description: string;
}

/**
 * Portada de acceso al sistema. Estructura de tarjeta flotante en dos mitades:
 * a la izquierda la identidad institucional, a la derecha el formulario.
 * En pantallas pequenas el panel izquierdo se colapsa a un encabezado.
 */
export default function AuthLayout({ children, title, description }: AuthLayoutProps) {
    const { name } = usePage<SharedData>().props;
    const anio = new Date().getFullYear();

    return (
        <div className="from-mspas-navy-dark via-mspas-navy to-mspas-navy-light relative flex min-h-dvh items-center justify-center bg-gradient-to-br p-4 sm:p-8">
            {/* Halos de color: dan profundidad sin competir con el formulario. */}
            <div className="bg-mspas-cyan/25 pointer-events-none absolute -top-32 -left-24 size-96 rounded-full blur-3xl" />
            <div className="bg-mspas-teal/20 pointer-events-none absolute -right-24 -bottom-32 size-96 rounded-full blur-3xl" />

            <div className="relative w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-neutral-950">
                <div className="grid lg:grid-cols-[1.05fr_1fr]">
                    <PanelInstitucional />

                    <div className="flex flex-col justify-center px-6 py-10 sm:px-12 lg:py-14">
                        {/* Encabezado compacto para movil, donde no hay panel izquierdo. */}
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <div className="text-mspas-navy dark:text-mspas-cyan">
                                <AppLogoIcon className="size-10" />
                            </div>
                            <div>
                                <p className="text-foreground text-sm font-semibold">{name}</p>
                                <p className="text-muted-foreground text-xs">DDRISST · MSPAS</p>
                            </div>
                        </div>

                        <div className="mb-8">
                            <h1 className="text-foreground text-2xl font-semibold tracking-tight">{title}</h1>
                            <p className="text-muted-foreground mt-2 text-sm">{description}</p>
                        </div>

                        {children}

                        <p className="text-muted-foreground mt-10 text-center text-xs">
                            Ministerio de Salud Pública y Asistencia Social · {anio}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}

function PanelInstitucional() {
    return (
        <div className="from-mspas-navy to-mspas-navy-light relative hidden flex-col justify-between bg-gradient-to-b p-12 text-white lg:flex">
            {/* Barra celeste: el mismo recurso grafico que separa el escudo del
                logotipo del Ministerio. */}
            <div className="bg-mspas-cyan absolute top-12 bottom-12 left-0 w-1.5 rounded-r-full" />

            <div className="flex items-center gap-4">
                <AppLogoIcon className="size-12 text-white" />
                <div>
                    <p className="text-lg leading-tight font-semibold">Inventario de Bienes</p>
                    <p className="text-mspas-cyan-light/80 text-sm">DDRISST · MSPAS Guatemala</p>
                </div>
            </div>

            <IlustracionInventario className="my-8 w-full max-w-sm self-center" />

            <div className="space-y-4">
                <p className="text-xl leading-snug font-medium text-balance">
                   
                   
                </p>
                <p className="text-mspas-cyan-light/75 text-sm leading-relaxed">
                    Mobiliario, equipo médico etc.
                     registrados, asignados y trazables.
                </p>
                <div className="text-mspas-cyan-light/80 flex items-center gap-2 pt-2 text-xs">
                    <ShieldCheck className="size-4" />
                    <span>Acceso restringido a personal autorizado</span>
                </div>
            </div>
        </div>
    );
}

/** Escena simple: tablilla de registro, cajas y equipo de un centro de salud. */
function IlustracionInventario({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 320 200" fill="none" xmlns="http://www.w3.org/2000/svg">
            <ellipse cx="160" cy="180" rx="130" ry="12" className="fill-white/5" />

            {/* Tablilla con el listado de bienes */}
            <rect x="28" y="30" width="112" height="140" rx="8" className="fill-white/95" />
            <rect x="60" y="22" width="48" height="16" rx="5" className="fill-mspas-cyan" />
            <rect x="44" y="58" width="80" height="7" rx="3.5" className="fill-mspas-navy/25" />
            <rect x="44" y="76" width="62" height="7" rx="3.5" className="fill-mspas-navy/15" />
            <rect x="44" y="94" width="72" height="7" rx="3.5" className="fill-mspas-navy/15" />
            <rect x="44" y="112" width="54" height="7" rx="3.5" className="fill-mspas-navy/15" />
            <path d="m44 133 6 6 12-13" stroke="currentColor" className="text-mspas-teal" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
            <rect x="70" y="130" width="54" height="7" rx="3.5" className="fill-mspas-navy/15" />

            {/* Cajas del inventario */}
            <rect x="164" y="104" width="66" height="66" rx="6" className="fill-mspas-cyan/90" />
            <rect x="164" y="104" width="66" height="18" rx="6" className="fill-white/35" />
            <rect x="188" y="104" width="18" height="30" rx="3" className="fill-white/60" />

            <rect x="238" y="126" width="52" height="44" rx="6" className="fill-white/85" />
            <rect x="238" y="126" width="52" height="14" rx="6" className="fill-mspas-navy/20" />

            {/* Equipo con la cruz de salud */}
            <rect x="196" y="34" width="94" height="58" rx="8" className="fill-white/95" />
            <path d="M235 48h10v9h9v10h-9v9h-10v-9h-9V57h9v-9Z" className="fill-mspas-teal" />
            <rect x="210" y="100" width="10" height="14" rx="2" className="fill-white/40" />
            <rect x="266" y="100" width="10" height="14" rx="2" className="fill-white/40" />
        </svg>
    );
}
