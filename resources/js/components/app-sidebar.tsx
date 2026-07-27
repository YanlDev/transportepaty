import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Truck, User, Users } from 'lucide-react';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
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

const operacionNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Vehículos',
        href: vehiculos.index(),
        icon: Truck,
    },
];

const gestionNavItems: NavItem[] = [
    {
        title: 'Conductores',
        href: conductores.index(),
        icon: User,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Usuarios',
        href: usuarios.index(),
        icon: Users,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const esAdmin = auth.roles.includes('admin');
    const puedeGestionar = esAdmin || auth.roles.includes('visor');

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
                <NavMain label="Operación" items={operacionNavItems} />
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
