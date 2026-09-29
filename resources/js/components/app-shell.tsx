import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import { useEsTablet } from '@/hooks/use-es-tablet';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;
    const esTablet = useEsTablet();
    // En tablet (md–lg) el sidebar abierto se come un tercio del ancho: ahí
    // arranca siempre colapsado a íconos, diga lo que diga la cookie, y se
    // puede abrir a mano. En escritorio sigue mandando la cookie.
    const [abiertoEnTablet, setAbiertoEnTablet] = useState(false);

    if (variant === 'header') {
        return <div className="flex min-h-dvh w-full flex-col">{children}</div>;
    }

    return (
        <SidebarProvider
            defaultOpen={isOpen}
            open={esTablet ? abiertoEnTablet : undefined}
            onOpenChange={esTablet ? setAbiertoEnTablet : undefined}
        >
            {children}
        </SidebarProvider>
    );
}
