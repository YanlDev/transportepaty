import { Search } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { abrirBuscador } from '@/components/buscador-global';
import { Campanita } from '@/components/campanita';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {/* En el celular el buscador está en la barra de abajo. */}
            <Button
                variant="outline"
                size="sm"
                onClick={abrirBuscador}
                className="hidden gap-2 text-muted-foreground md:inline-flex"
            >
                <Search className="size-4" />
                Buscar
                <kbd className="rounded border bg-muted px-1.5 font-mono text-[10px]">
                    Ctrl K
                </kbd>
            </Button>
            <Campanita />
        </header>
    );
}
