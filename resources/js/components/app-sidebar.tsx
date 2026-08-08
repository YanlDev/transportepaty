import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    ClipboardList,
    FileText,
    LayoutGrid,
    Link2,
    Link2Off,
    Route as RouteIcon,
    Truck,
    User,
    Users,
} from 'lucide-react';
import asignaciones from '@/actions/App/Http/Controllers/AsignacionController';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
import usuarios from '@/actions/App/Http/Controllers/UserController';
import vehiculos from '@/actions/App/Http/Controllers/VehiculoController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/** Destinos visibles para cualquier usuario autenticado. */
const navItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

/**
 * El padrón de vehículos y conductores, y su documentación: un desplegable
 * con las dos entradas adentro. Vehículos lo ve cualquier usuario con acceso
 * al sistema (incluido el conductor); Conductores exige admin o visor y se
 * agrega aparte según el rol.
 */
const documentosSubitems: NavItem[] = [
    {
        title: 'Vehículos',
        href: vehiculos.index(),
        icon: Truck,
    },
];

/**
 * Requiere rol admin o visor. «Sin asignar» va aparte de «Asignaciones» a
 * propósito: una responde qué unidades están armadas y la otra qué queda
 * parado, y mezclarlas obliga a leer dos cosas a la vez.
 */
const gestionNavItems: NavItem[] = [
    {
        title: 'Programación',
        href: programacion.index(),
        icon: ClipboardList,
    },
    {
        title: 'Viajes',
        href: viajes.index(),
        icon: RouteIcon,
    },
    {
        title: 'Asistencia',
        href: asistencia.index(),
        icon: CalendarCheck,
    },
    {
        title: 'Asignaciones',
        href: asignaciones.index(),
        icon: Link2,
    },
    {
        title: 'Sin asignar',
        href: asignaciones.disponibles(),
        icon: Link2Off,
    },
];

/** Solo para admin. */
const adminNavItems: NavItem[] = [
    {
        title: 'Usuarios',
        href: usuarios.index(),
        icon: Users,
    },
];

export function AppSidebar() {
    const { auth, sinAsignar } = usePage().props;
    const esAdmin = auth.roles.includes('admin');
    const puedeGestionar = esAdmin || auth.roles.includes('visor');

    // Conductores exige admin o visor, así que solo se agrega al desplegable
    // documental cuando el rol alcanza; el conductor de a pie solo ve Vehículos.
    const documentos: NavItem = {
        title: 'Documentos',
        href: vehiculos.index(),
        icon: FileText,
        items: puedeGestionar
            ? [
                  ...documentosSubitems,
                  {
                      title: 'Conductores',
                      href: conductores.index(),
                      icon: User,
                  },
              ]
            : documentosSubitems,
    };

    // El aviso de lo que está parado se pinta sobre la entrada correspondiente.
    const gestion = gestionNavItems.map((item) =>
        item.title === 'Sin asignar' ? { ...item, badge: sinAsignar } : item,
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto py-1 group-data-[collapsible=icon]:p-0!"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={[...navItems, documentos]} />
                {puedeGestionar && <NavMain items={gestion} />}
                {esAdmin && <NavMain items={adminNavItems} />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
