import { Link } from '@inertiajs/react';
import {
    Building2,
    Calculator,
    CalendarCheck,
    CalendarDays,
    ClipboardCheck,
    Fuel,
    IdCard,
    LayoutGrid,
    Receipt,
    Route,
    Settings,
    Truck,
    Wrench,
} from 'lucide-react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import clientes from '@/actions/App/Http/Controllers/ClienteController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import taller from '@/actions/App/Http/Controllers/TallerController';
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
 * El menú va por áreas de trabajo y no por tablas: Operación es todo lo que
 * le pasa a un ticket (programarlo, el OK de taller, el combustible, sus
 * GR); Flota, las unidades y quienes las manejan; Comercial y Finanzas, lo
 * que se cobra. La configuración entera está en una sola entrada, abajo.
 *
 * Una sección cuyas entradas no le corresponden al rol no se dibuja, ni su
 * título: `NavMain` devuelve null con la lista vacía.
 */
const dashboardNavItem: NavItem = {
    title: 'Inicio',
    href: dashboard(),
    icon: LayoutGrid,
};

/**
 * Tractos y carretas en una sola entrada: la pantalla tiene una pestaña para
 * cada uno.
 */
const unidadesNavItem: NavItem = {
    title: 'Unidades',
    href: vehiculos.tractos(),
    icon: Truck,
};

/** El semáforo de taller: qué unidad necesita servicio y cuál viene. */
const mantenimientoNavItem: NavItem = {
    title: 'Mantenimiento',
    href: mantenimiento.index(),
    icon: Wrench,
};

/** El OK de salida de cada ticket: la bandeja de taller. */
const tallerNavItem: NavItem = {
    title: 'Taller',
    href: taller.index(),
    icon: ClipboardCheck,
};

/** Requiere admin o visor: el contador no ve el padrón de choferes. */
const conductoresNavItem: NavItem = {
    title: 'Conductores',
    href: conductores.index(),
    icon: IdCard,
};

/** Las guías de remisión: las lee también el contador, que factura contra ellas. */
const viajesNavItem: NavItem = {
    title: 'Guías (GR)',
    href: viajes.index(),
    icon: Route,
};

/** Los tickets: cada viaje nace acá. */
const programacionNavItem: NavItem = {
    title: 'Programación',
    href: vueltas.index(),
    icon: CalendarCheck,
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
    icon: CalendarDays,
};

/** Todos los ajustes en una sola puerta; cada quien ve los suyos. */
const configuracionNavItem: NavItem = {
    title: 'Configuración',
    href: configuracion.index(),
    icon: Settings,
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

    // Las secciones son las áreas de la empresa —las mismas del panel de
    // permisos (`Permiso::AREAS`)—, así cada rol ve justo su bloque.
    const operacion = [
        ...(puede('programacion.ver') ? [programacionNavItem] : []),
        ...(puede('taller.ver') ? [tallerNavItem] : []),
        // Facturación entra solo a los viáticos: también ve el área.
        ...(puede('abastecimiento.ver') || puede('abastecimiento.viaticos')
            ? [abastecimientoNavItem]
            : []),
        ...(puede('viajes.ver') ? [viajesNavItem] : []),
    ];

    const flota = [
        // Tractos y carretas son una sola entrada, con pestañas; se marca
        // en cualquiera de las dos y en la ficha de una unidad.
        ...(puede('vehiculos.ver')
            ? [
                  {
                      ...unidadesNavItem,
                      isActive:
                          isCurrentUrl(vehiculos.tractos()) ||
                          isCurrentUrl(vehiculos.carretas()) ||
                          isCurrentUrl('/vehiculos', undefined, true),
                  },
              ]
            : []),
        ...(puede('mantenimiento.ver') ? [mantenimientoNavItem] : []),
        ...(puede('conductores.ver') ? [conductoresNavItem] : []),
        ...(puede('asistencia.ver') ? [asistenciaNavItem] : []),
    ];

    // Cotizaciones antes que Clientes: es lo que se usa a diario.
    const comercial = [...gestion].reverse();

    const finanzas = puede('cobranza.ver') ? [contabilidadNavItem] : [];

    // Con roles armados a mano una sección puede quedar vacía: no se pinta
    // el título solo.
    const secciones = [
        { label: 'Operación', items: operacion },
        { label: 'Flota', items: flota },
        { label: 'Comercial', items: comercial },
        { label: 'Finanzas', items: finanzas },
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
                {/* Todo el que entra tiene al menos su cuenta: siempre se ve. */}
                <div className="mt-auto pt-4">
                    <NavMain
                        items={[
                            {
                                ...configuracionNavItem,
                                isActive:
                                    isCurrentUrl(configuracion.index()) ||
                                    isCurrentUrl(
                                        '/settings',
                                        undefined,
                                        true,
                                    ) ||
                                    isCurrentUrl('/vueltas/configuracion') ||
                                    isCurrentUrl(
                                        '/abastecimiento/configuracion',
                                    ),
                            },
                        ]}
                    />
                </div>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
