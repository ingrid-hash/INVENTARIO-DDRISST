import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermisos } from '@/hooks/use-permisos';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    ClipboardList,
    DatabaseBackup,
    FileSpreadsheet,
    FileText,
    IdCard,
    LayoutGrid,
    Package,
    ScrollText,
    ShieldCheck,
    Tags,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const navItems: NavItem[] = [
    { title: 'Panel principal', url: '/dashboard', icon: LayoutGrid },

    // Inventario
    { title: 'Bienes', url: '/inventario/bienes', icon: Package, permiso: 'bienes.ver' },
    { title: 'Tarjetas', url: '/inventario/tarjetas', icon: FileText, permiso: 'tarjetas.ver' },
    { title: 'Empleados', url: '/inventario/empleados', icon: IdCard, permiso: 'empleados.ver' },
    { title: 'Cuentas', url: '/inventario/cuentas', icon: Tags, permiso: 'renglones.ver' },
    { title: 'Unidades de servicio', url: '/inventario/unidades', icon: Building2, permiso: 'unidades.ver' },
    { title: 'Reportes', url: '/inventario/reportes', icon: BarChart3, permiso: 'reportes.ver' },
    { title: 'Importar de Excel', url: '/inventario/importacion', icon: FileSpreadsheet, permiso: 'importaciones.ver' },

    // Seguridad
    { title: 'Usuarios', url: '/admin/usuarios', icon: Users, permiso: 'usuarios.ver' },
    { title: 'Roles', url: '/admin/roles', icon: ShieldCheck, permiso: 'roles.ver' },
    { title: 'Permisos', url: '/admin/permisos', icon: ClipboardList, permiso: 'permisos.ver' },
    { title: 'Bitácora', url: '/admin/bitacora', icon: ScrollText, permiso: 'bitacora.ver' },
    { title: 'Respaldos', url: '/admin/respaldos', icon: DatabaseBackup, permiso: 'respaldos.ver' },
];

export function AppSidebar() {
    const { puede } = usePermisos();

    const visibles = navItems.filter((item) => !item.permiso || puede(item.permiso));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibles} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
