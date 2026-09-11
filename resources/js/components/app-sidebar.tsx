import { Link } from '@inertiajs/react';
import {
    Calculator,
    CalendarCheck,
    IconContext,
    IdentificationCard,
    Path,
    Buildings,
    Receipt,
    SquaresFour,
    Truck,
    TruckTrailer,
    UsersThree,
} from '@phosphor-icons/react';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import clientes from '@/actions/App/Http/Controllers/ClienteController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
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
import { usePermisos } from '@/hooks/use-permisos';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * Destinos visibles para cualquier rol. Tractos y carretas van separados —con
 * su propio ícono— para encontrar cada uno directo, en vez de filtrar por tipo
 * dentro de un listado combinado de vehículos.
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

/** Los viajes: la lee también el contador, que factura contra ellos. */
const viajesNavItem: NavItem = {
    title: 'Viajes',
    href: viajes.index(),
    icon: Path,
};

/** Requiere rol admin o visor. */
const gestionNavItems: NavItem[] = [
    {
        title: 'Clientes',
        href: clientes.index(),
        icon: Buildings,
    },
    {
        title: 'Cotizaciones',
        href: cotizaciones.index(),
        icon: Calculator,
    },
];

/** Solo para admin y contador: la cobranza y las cuentas de la empresa. */
const contabilidadNavItems: NavItem[] = [
    {
        title: 'Contabilidad',
        href: contabilidad.index(),
        icon: Receipt,
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
    const { esAdmin, esContador, puedeVerOperacion } = usePermisos();
    // El contador no gestiona la operación, pero sí lee los viajes: son la
    // contrapartida de lo que factura.
    const puedeVerViajes = puedeVerOperacion || esContador;

    // Conductores exige admin o visor: el contador ve las unidades para
    // identificar la placa de una guía, pero no el padrón de choferes.
    const principales = puedeVerOperacion
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
                    {puedeVerViajes && <NavMain items={[viajesNavItem]} />}
                    {puedeVerOperacion && <NavMain items={gestionNavItems} />}
                    {(esAdmin || esContador) && (
                        <NavMain items={contabilidadNavItems} />
                    )}
                    {esAdmin && <NavMain items={adminNavItems} />}
                </IconContext.Provider>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
