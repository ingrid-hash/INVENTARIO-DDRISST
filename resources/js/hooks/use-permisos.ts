import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Oculta en pantalla lo que el usuario no puede hacer. Es solo comodidad
 * visual: la autorizacion de verdad la aplica el backend en cada ruta.
 */
export function usePermisos() {
    const { auth } = usePage<SharedData>().props;

    const permisos = auth.user?.permisos ?? [];
    const esSuperadmin = auth.user?.rol === 'Superadmin';

    return {
        esSuperadmin,
        rol: auth.user?.rol ?? null,
        puede: (permiso: string) => esSuperadmin || permisos.includes(permiso),
        puedeAlguno: (...lista: string[]) => esSuperadmin || lista.some((permiso) => permisos.includes(permiso)),
    };
}
