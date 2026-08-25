import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label,
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    if (items.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) =>
                    item.items ? (
                        <NavMainDropdown
                            key={item.title}
                            item={item}
                            isCurrentUrl={isCurrentUrl}
                        />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                    {item.badge !== undefined &&
                                        item.badge > 0 && (
                                            <span className="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white">
                                                {item.badge}
                                            </span>
                                        )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function NavMainDropdown({
    item,
    isCurrentUrl,
}: {
    item: NavItem;
    isCurrentUrl: ReturnType<typeof useCurrentUrl>['isCurrentUrl'];
}) {
    const subitems = item.items ?? [];
    const activo = subitems.some((subitem) => isCurrentUrl(subitem.href));

    return (
        <Collapsible asChild defaultOpen={activo} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        isActive={activo}
                        tooltip={{ children: item.title }}
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {subitems.map((subitem) => (
                            <SidebarMenuSubItem key={subitem.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentUrl(subitem.href)}
                                >
                                    <Link href={subitem.href} prefetch>
                                        {subitem.icon && <subitem.icon />}
                                        <span>{subitem.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
