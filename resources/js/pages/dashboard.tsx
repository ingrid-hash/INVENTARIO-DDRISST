import { usePermisos } from '@/hooks/use-permisos';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    IdCard,
    Package,
    PackageMinus,
    ScrollText,
    ShieldCheck,
    Tags,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Panel principal', href: '/dashboard' }];

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const { puede, rol } = usePermisos();

    const inventario = [
        {
            titulo: 'Bienes',
            detalle: 'Registro y búsqueda de mobiliario y equipo.',
            url: '/inventario/bienes',
            icono: Package,
            permiso: 'bienes.ver',
        },
        {
            titulo: 'Tarjetas',
            detalle: 'Armado de la tarjeta de responsabilidad por empleado.',
            url: '/inventario/tarjetas',
            icono: FileText,
            permiso: 'tarjetas.ver',
        },
        {
            titulo: 'Empleados',
            detalle: 'Personal que responde por los bienes.',
            url: '/inventario/empleados',
            icono: IdCard,
            permiso: 'empleados.ver',
        },
        {
            titulo: 'Cuentas',
            detalle: 'Renglones presupuestarios del inventario.',
            url: '/inventario/cuentas',
            icono: Tags,
            permiso: 'renglones.ver',
        },
        {
            titulo: 'Unidades de servicio',
            detalle: 'Áreas, distritos, centros y puestos de salud.',
            url: '/inventario/unidades',
            icono: Building2,
            permiso: 'unidades.ver',
        },
        {
            titulo: 'Importar de Excel',
            detalle: 'Carga de inventarios y tarjetas con mapeo de columnas.',
            url: '/inventario/importacion',
            icono: FileSpreadsheet,
            permiso: 'importaciones.ver',
        },
    ].filter((item) => puede(item.permiso));

    const seguridad = [
        {
            titulo: 'Usuarios',
            detalle: 'Cuentas de acceso y restablecimiento de contraseñas.',
            url: '/admin/usuarios',
            icono: Users,
            permiso: 'usuarios.ver',
        },
        {
            titulo: 'Roles',
            detalle: 'Asignación de permisos por rol.',
            url: '/admin/roles',
            icono: ShieldCheck,
            permiso: 'roles.ver',
        },
        {
            titulo: 'Permisos',
            detalle: 'Catálogo de permisos por módulo.',
            url: '/admin/permisos',
            icono: ClipboardList,
            permiso: 'permisos.ver',
        },
        {
            titulo: 'Bitácora',
            detalle: 'Accesos, cambios y movimientos registrados.',
            url: '/admin/bitacora',
            icono: ScrollText,
            permiso: 'bitacora.ver',
        },
    ].filter((item) => puede(item.permiso));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Panel principal" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="from-mspas-navy to-mspas-navy-light relative overflow-hidden rounded-2xl bg-gradient-to-br p-8 text-white">
                    <div className="bg-mspas-cyan/25 pointer-events-none absolute -top-16 -right-10 size-56 rounded-full blur-3xl" />
                    <p className="text-mspas-cyan-light/80 text-sm">Bienvenido(a)</p>
                    <h1 className="mt-1 text-2xl font-semibold">{auth.user?.name}</h1>
                    <p className="text-mspas-cyan-light/75 mt-2 text-sm">
                        {rol} {auth.user?.unidad ? `· ${auth.user.unidad}` : null}
                    </p>
                </div>

                {inventario.length > 0 && <Grupo titulo="Inventario" accesos={inventario} />}
                {seguridad.length > 0 && <Grupo titulo="Administración" accesos={seguridad} />}

                <div className="border-border bg-card grid gap-6 rounded-xl border border-dashed p-8 sm:grid-cols-2">
                    <Pendiente
                        icono={PackageMinus}
                        titulo="Bajas de bienes"
                        detalle="Expediente de baja con solicitud y autorización por separado, y regeneración de la tarjeta al autorizarse."
                    />
                    <Pendiente
                        icono={BarChart3}
                        titulo="Reportes exportables"
                        detalle="Listado general por unidad, adiciones por período y bienes de baja, en Excel y PDF."
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function Grupo({
    titulo,
    accesos,
}: {
    titulo: string;
    accesos: { titulo: string; detalle: string; url: string; icono: typeof Package }[];
}) {
    return (
        <section className="grid gap-3">
            <p className="text-muted-foreground text-xs font-bold tracking-wider uppercase">{titulo}</p>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {accesos.map((acceso) => (
                    <Link
                        key={acceso.titulo}
                        href={acceso.url}
                        className="border-border bg-card hover:border-mspas-cyan rounded-xl border p-5 transition-colors"
                    >
                        <div className="bg-mspas-cyan-light text-mspas-navy dark:bg-mspas-navy dark:text-mspas-cyan flex size-10 items-center justify-center rounded-lg">
                            <acceso.icono className="size-5" />
                        </div>
                        <p className="text-foreground mt-4 font-medium">{acceso.titulo}</p>
                        <p className="text-muted-foreground mt-1 text-sm leading-relaxed">{acceso.detalle}</p>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function Pendiente({
    icono: Icono,
    titulo,
    detalle,
}: {
    icono: typeof Package;
    titulo: string;
    detalle: string;
}) {
    return (
        <div className="flex gap-3">
            <Icono className="text-muted-foreground mt-0.5 size-5 shrink-0" />
            <div>
                <p className="text-foreground text-sm font-medium">
                    {titulo}
                    <span className="text-muted-foreground ml-2 text-xs font-normal">en construcción</span>
                </p>
                <p className="text-muted-foreground mt-1 text-sm leading-relaxed">{detalle}</p>
            </div>
        </div>
    );
}
