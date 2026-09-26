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
 * El menú va por secciones —Flota, Operación, Administración, Sistema— y no
 * como una lista corrida: con doce destinos, los títulos son lo que permite
 * saltar al bloque que se busca sin leerlos todos. Cada entrada sigue siendo
 * de un clic; agrupar es para leer, no para esconder.
 *
 * Una sección cuyas entradas no le corresponden al rol no se dibuja, ni su
 * título: `NavMain` devuelve null con la lista vacía.
 */
const dashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: dashboard(),
    icon: SquaresFour,
};

/**
 * Las unidades y quienes las manejan. Tractos y carretas van separados —con
 * su propio ícono— para encontrar cada uno directo, en vez de filtrar por tipo
 * dentro de un listado combinado.
 */
const flotaNavItems: NavItem[] = [
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

/** Requiere admin o visor: el contador no ve el padrón de choferes. */
const conductoresNavItem: NavItem = {
    title: 'Conductores',
    href: conductores.index(),
    icon: IdentificationCard,
};

/** Los viajes: los lee también el contador, que factura contra ellos. */
const viajesNavItem: NavItem = {
    title: 'Viajes',
    href: viajes.index(),
    icon: Path,
};

/**
 * Qué unidades salen con carga particular cada día. La lee abastecimiento
 * para preparar la carga, así que entra con el mismo permiso de operación.
 */
const programacionNavItem: NavItem = {
    title: 'Programación',
    href: programacion.index(),
    icon: CalendarCheck,
};

/** Solo para admin: el control del personal. */
const asistenciaNavItem: NavItem = {
    title: 'Asistencia',
    href: asistencia.index(),
    icon: CalendarCheck,
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
const contabilidadNavItem: NavItem = {
    title: 'Contabilidad',
    href: contabilidad.index(),
    icon: Receipt,
};

/** Solo para admin: cuentas del sistema y el número que manda los avisos. */
const sistemaNavItems: NavItem[] = [
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
    const flota = puedeVerOperacion
        ? [...flotaNavItems, conductoresNavItem]
        : flotaNavItems;

    const operacion = [
        ...(puedeVerViajes ? [viajesNavItem] : []),
        ...(puedeVerOperacion ? [programacionNavItem] : []),
        ...(esAdmin ? [asistenciaNavItem] : []),
    ];

    const administracion = [
        ...(puedeVerOperacion ? gestion : []),
        ...(esAdmin || esContador ? [contabilidadNavItem] : []),
    ];

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
                    <NavMain items={[dashboardNavItem]} />
                    <NavMain items={flota} label="Flota" />
                    <NavMain items={operacion} label="Operación" />
                    <NavMain items={administracion} label="Administración" />
                    {esAdmin && (
                        <NavMain items={sistemaNavItems} label="Sistema" />
                    )}
                </IconContext.Provider>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
