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
} from '@phosphor-icons/react';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import clientes from '@/actions/App/Http/Controllers/ClienteController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
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

/** El control del personal. */
const asistenciaNavItem: NavItem = {
    title: 'Asistencia',
    href: asistencia.index(),
    icon: CalendarCheck,
};

/** Cada uno aparece con su permiso de lectura. */
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

/** La cobranza y las cuentas de la empresa. */
const contabilidadNavItem: NavItem = {
    title: 'Contabilidad',
    href: contabilidad.index(),
    icon: Receipt,
};

export function AppSidebar() {
    const { puede } = usePermisos();
    const { isCurrentUrl } = useCurrentUrl();

    // Cotizaciones es un solo módulo con pestañas (cotizador, emitidas,
    // tarifario). Quien puede cotizar entra por el cotizador, que es para lo
    // que viene; el resto solo puede leer las emitidas.
    const gestion = gestionNavItems
        .filter((item) =>
            item.title === 'Cotizaciones'
                ? puede('cotizaciones.ver') ||
                  puede('cotizaciones.crear') ||
                  puede('costos.editar')
                : puede('clientes.ver'),
        )
        .map((item) =>
            item.title === 'Cotizaciones'
                ? {
                      ...item,
                      href: puede('cotizaciones.crear')
                          ? cotizaciones.cotizador()
                          : puede('cotizaciones.ver')
                            ? cotizaciones.index()
                            : parametrosCosto.edit(),
                      isActive:
                          isCurrentUrl(cotizaciones.index(), undefined, true) ||
                          isCurrentUrl(parametrosCosto.edit(), undefined, true),
                  }
                : item,
        );

    const flota = [
        ...(puede('vehiculos.ver') ? flotaNavItems : []),
        ...(puede('conductores.ver') ? [conductoresNavItem] : []),
    ];

    const operacion = [
        ...(puede('viajes.ver') ? [viajesNavItem] : []),
        ...(puede('programacion.ver') ? [programacionNavItem] : []),
        ...(puede('asistencia.ver') ? [asistenciaNavItem] : []),
    ];

    const administracion = [
        ...gestion,
        ...(puede('cobranza.ver') ? [contabilidadNavItem] : []),
    ];

    // Con roles armados a mano una sección puede quedar vacía: no se pinta
    // el título solo.
    const secciones = [
        { label: 'Flota', items: flota },
        { label: 'Operación', items: operacion },
        { label: 'Administración', items: administracion },
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
                    {puede('tablero.ver') && (
                        <NavMain items={[dashboardNavItem]} />
                    )}
                    {secciones.map(
                        ({ label, items }) =>
                            items.length > 0 && (
                                <NavMain
                                    key={label}
                                    items={items}
                                    label={label}
                                />
                            ),
                    )}
                </IconContext.Provider>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
