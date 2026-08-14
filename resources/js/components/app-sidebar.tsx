import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    Clock,
    IconContext,
    IdentificationCard,
    Link as LinkIcon,
    LinkBreak,
    Path,
    SquaresFour,
    Truck,
    UsersThree,
} from '@phosphor-icons/react';
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
        icon: SquaresFour,
    },
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
        title: 'Viajes',
        href: viajes.index(),
        icon: Path,
    },
    {
        title: 'Asignaciones',
        href: asignaciones.index(),
        icon: LinkIcon,
    },
    {
        title: 'Sin asignar',
        href: asignaciones.disponibles(),
        icon: LinkBreak,
    },
];

/** Solo para admin: gestión de personas y control interno, no lectura de flota. */
const adminNavItems: NavItem[] = [
    {
        title: 'Programación',
        href: programacion.index(),
        icon: Clock,
    },
    {
        title: 'Asistencia',
        href: asistencia.index(),
        icon: CalendarCheck,
    },
    {
        title: 'Usuarios',
        href: usuarios.index(),
        icon: UsersThree,
    },
];

export function AppSidebar() {
    const { auth, sinAsignar } = usePage().props;
    const esAdmin = auth.roles.includes('admin');
    const puedeGestionar = esAdmin || auth.roles.includes('visor');

    // Conductores exige admin o visor; el conductor de a pie solo ve Vehículos.
    const principales = puedeGestionar
        ? [
              ...navItems,
              {
                  title: 'Conductores',
                  href: conductores.index(),
                  icon: IdentificationCard,
              },
          ]
        : navItems;

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
                <IconContext.Provider value={{ weight: 'duotone' }}>
                    <NavMain items={principales} />
                    {puedeGestionar && <NavMain items={gestion} />}
                    {esAdmin && <NavMain items={adminNavItems} />}
                </IconContext.Provider>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
