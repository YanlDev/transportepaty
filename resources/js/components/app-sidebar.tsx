import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Car,
    Fuel,
    LayoutGrid,
    Map,
    Satellite,
    User,
    Users,
    Wrench,
} from 'lucide-react';
import {
    pendientes as combustiblePendientes,
    rapido as registrarCarga,
} from '@/actions/App/Http/Controllers/CargaCombustibleController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import tracksolid from '@/actions/App/Http/Controllers/Integraciones/TracksolidController';
import { index as mapa } from '@/actions/App/Http/Controllers/MapaController';
import { index as plantillasMantenimiento } from '@/actions/App/Http/Controllers/PlantillaMantenimientoController';
import sucursales from '@/actions/App/Http/Controllers/SucursalController';
import usuarios from '@/actions/App/Http/Controllers/UserController';
import vehiculos from '@/actions/App/Http/Controllers/VehiculoController';
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

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Vehículos',
        href: vehiculos.index(),
        icon: Car,
    },
    {
        title: 'Mapa de flota',
        href: mapa(),
        icon: Map,
    },
];

const gestionNavItems: NavItem[] = [
    {
        title: 'Conductores',
        href: conductores.index(),
        icon: User,
    },
    {
        title: 'Sucursales',
        href: sucursales.index(),
        icon: Building2,
    },
];

const conductorNavItems: NavItem[] = [
    {
        title: 'Registrar carga',
        href: registrarCarga(),
        icon: Fuel,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Usuarios',
        href: usuarios.index(),
        icon: Users,
    },
    {
        title: 'Plantillas de mant.',
        href: plantillasMantenimiento(),
        icon: Wrench,
    },
    {
        title: 'Dispositivos GPS',
        href: tracksolid.index(),
        icon: Satellite,
    },
];

export function AppSidebar() {
    const { auth, combustiblePendiente } = usePage().props;
    const esAdmin = auth.roles.includes('admin');
    const puedeGestionar = esAdmin || auth.roles.includes('visor');
    const esConductor = auth.roles.includes('conductor');

    // Operación: lo que usa el día a día. El conductor registra carga; el
    // admin además ve la bandeja de cargas pendientes.
    const operacionItems: NavItem[] = [
        ...mainNavItems,
        ...(esConductor ? conductorNavItems : []),
        ...(esAdmin
            ? [
                  {
                      title: 'Cargas por procesar',
                      href: combustiblePendientes(),
                      icon: Fuel,
                      badge: combustiblePendiente,
                  },
              ]
            : []),
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
                <NavMain label="Operación" items={operacionItems} />
                {puedeGestionar && (
                    <NavMain label="Gestión" items={gestionNavItems} />
                )}
                {esAdmin && (
                    <NavMain label="Administración" items={adminNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
