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
    WhatsappLogo,
} from '@phosphor-icons/react';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import clientes from '@/actions/App/Http/Controllers/ClienteController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
import usuarios from '@/actions/App/Http/Controllers/UserController';
import vehiculos from '@/actions/App/Http/Controllers/VehiculoController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import whatsapp from '@/actions/App/Http/Controllers/WhatsappController';
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
import { useCurrentUrl } from '@/hooks/use-current-url';
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

/**
 * Qué unidades salen con carga particular cada día. La lee abastecimiento
 * para preparar la carga, así que entra con el mismo permiso de operación.
 */
const programacionNavItem: NavItem = {
    title: 'Programación',
    href: programacion.index(),
    icon: CalendarCheck,
};

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
    {
        title: 'WhatsApp',
        href: whatsapp.index(),
        icon: WhatsappLogo,
    },
];

export function AppSidebar() {
    const { esAdmin, esContador, puedeVerOperacion } = usePermisos();
    const { isCurrentUrl } = useCurrentUrl();
    // El contador no gestiona la operación, pero sí lee los viajes: son la
    // contrapartida de lo que factura.
    const puedeVerViajes = puedeVerOperacion || esContador;

    // Cotizaciones es un solo módulo con pestañas (cotizador, emitidas,
    // tarifario). El admin entra por el cotizador, que es para lo que viene;
    // el visor solo puede leer las emitidas.
    const gestion = gestionNavItems.map((item) =>
        item.title === 'Cotizaciones'
            ? {
                  ...item,
                  href: esAdmin
                      ? cotizaciones.cotizador()
                      : cotizaciones.index(),
                  isActive:
                      isCurrentUrl(cotizaciones.index(), undefined, true) ||
                      isCurrentUrl(parametrosCosto.edit(), undefined, true),
              }
            : item,
    );

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
                    {puedeVerOperacion && (
                        <NavMain items={[programacionNavItem]} />
                    )}
                    {puedeVerOperacion && <NavMain items={gestion} />}
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
