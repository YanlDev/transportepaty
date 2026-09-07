import { Link, usePage } from '@inertiajs/react';
import {
    Calculator,
    CalendarCheck,
    IconContext,
    FileText,
    IdentificationCard,
    Path,
    Buildings,
    SquaresFour,
    Truck,
    TruckTrailer,
    UsersThree,
} from '@phosphor-icons/react';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import clientes from '@/actions/App/Http/Controllers/ClienteController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import guias from '@/actions/App/Http/Controllers/GuiaController';
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

/**
 * Destinos visibles para cualquier usuario autenticado. Tractos y carretas van
 * separados —con su propio ícono— para encontrar cada uno directo, en vez de
 * filtrar por tipo dentro de un listado combinado de vehículos.
 */
const navItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: SquaresFour,
    },
    {
        title: 'Tractos',
        href: vehiculos.tractos(),
        icon: Truck,
    },
    {
        title: 'Carretas',
        href: vehiculos.carretas(),
        icon: TruckTrailer,
    },
];

/** Requiere rol admin o visor. */
const gestionNavItems: NavItem[] = [
    {
        title: 'Viajes',
        href: viajes.index(),
        icon: Path,
    },
    {
        title: 'Clientes',
        href: clientes.index(),
        icon: Buildings,
    },
    {
        title: 'Guías',
        href: guias.index(),
        icon: FileText,
    },
    {
        title: 'Cotizaciones',
        href: cotizaciones.index(),
        icon: Calculator,
    },
];

/** Solo para admin: gestión de personas y control interno, no lectura de flota. */
const adminNavItems: NavItem[] = [
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
    const { auth } = usePage().props;
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
                    {puedeGestionar && <NavMain items={gestionNavItems} />}
                    {esAdmin && <NavMain items={adminNavItems} />}
                </IconContext.Provider>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
