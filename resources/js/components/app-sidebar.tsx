import { Link } from '@inertiajs/react';
import {
    Building2,
    Calculator,
    CalendarCheck,
    Container,
    Fuel,
    IdCard,
    LayoutGrid,
    Receipt,
    Repeat,
    Route,
    Truck,
    Wrench,
} from 'lucide-react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import clientes from '@/actions/App/Http/Controllers/ClienteController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
import vehiculos from '@/actions/App/Http/Controllers/VehiculoController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
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
 * El menú va por áreas —Flota, RRHH, Programación, Contabilidad— y no
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
    icon: LayoutGrid,
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
        icon: Container,
    },
];

/** El semáforo de taller: qué unidad necesita servicio y cuál viene. */
const mantenimientoNavItem: NavItem = {
    title: 'Mantenimiento',
    href: mantenimiento.index(),
    icon: Wrench,
};

/** Requiere admin o visor: el contador no ve el padrón de choferes. */
const conductoresNavItem: NavItem = {
    title: 'Conductores',
    href: conductores.index(),
    icon: IdCard,
};

/** Los viajes: los lee también el contador, que factura contra ellos. */
const viajesNavItem: NavItem = {
    title: 'Viajes',
    href: viajes.index(),
    icon: Route,
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

/** La ida y el regreso de cada unidad, armados desde sus GR. */
const vueltasNavItem: NavItem = {
    title: 'Vueltas',
    href: vueltas.index(),
    icon: Repeat,
};

/** Lo que le toca a cada unidad y el combustible que se le autoriza. */
const abastecimientoNavItem: NavItem = {
    title: 'Abastecimiento',
    href: abastecimiento.index(),
    icon: Fuel,
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
        icon: Building2,
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

    // Las secciones son las áreas de la empresa —las mismas de los roles y
    // del panel de permisos (`Permiso::AREAS`)—, así cada rol ve justo su
    // bloque del menú.
    const flota = [
        ...(puede('vehiculos.ver') ? flotaNavItems : []),
        ...(puede('mantenimiento.ver') ? [mantenimientoNavItem] : []),
    ];

    const rrhh = [
        ...(puede('conductores.ver') ? [conductoresNavItem] : []),
        ...(puede('asistencia.ver') ? [asistenciaNavItem] : []),
    ];

    const programacionArea = [
        ...(puede('programacion.ver')
            ? [programacionNavItem, vueltasNavItem]
            : []),
        ...(puede('viajes.ver') ? [viajesNavItem] : []),
        // Cotizaciones antes que Clientes: es lo que se usa a diario.
        ...[...gestion].reverse(),
    ];

    const abastecimientoArea = puede('abastecimiento.ver')
        ? [abastecimientoNavItem]
        : [];

    const contabilidadArea = puede('cobranza.ver') ? [contabilidadNavItem] : [];

    // Con roles armados a mano una sección puede quedar vacía: no se pinta
    // el título solo.
    const secciones = [
        { label: 'Flota', items: flota },
        { label: 'RRHH', items: rrhh },
        { label: 'Programación', items: programacionArea },
        { label: 'Abastecimiento', items: abastecimientoArea },
        { label: 'Contabilidad', items: contabilidadArea },
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
                {puede('tablero.ver') && <NavMain items={[dashboardNavItem]} />}
                {secciones.map(
                    ({ label, items }) =>
                        items.length > 0 && (
                            <NavMain key={label} items={items} label={label} />
                        ),
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
