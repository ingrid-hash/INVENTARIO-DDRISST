import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Permiso necesario para que la opción aparezca en el menú. */
    permiso?: string;
}

export interface PasswordTemporal {
    usuario: string;
    password: string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: {
        status?: string;
        passwordTemporal?: PasswordTemporal;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    username: string;
    name: string;
    email: string;
    puesto: string | null;
    unidad: string | null;
    telefono: string | null;
    rol: string | null;
    permisos: string[];
    avatar?: string;
    [key: string]: unknown;
}

/** Página de resultados tal como la entrega el paginador de Laravel. */
export interface Paginado<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}
