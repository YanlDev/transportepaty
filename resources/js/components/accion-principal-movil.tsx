import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    href: InertiaLinkProps['href'];
    icono: ReactNode;
    children: ReactNode;
    className?: string;
};

/**
 * La acción principal de un listado en el celular: botón flotante con texto,
 * abajo a la derecha, justo encima de la BottomNav —donde llega el pulgar sin
 * cambiar de mano—. Desde `md` no se muestra: ahí la acción vive en la
 * cabecera de la pantalla, como siempre.
 *
 * Deja además un espacio al final de la página del alto del botón, para que
 * no tape la última tarjeta ni la paginación.
 */
export function AccionPrincipalMovil({
    href,
    icono,
    children,
    className,
}: Props) {
    return (
        <>
            <div aria-hidden className="h-16 md:hidden" />
            <Link
                href={href}
                prefetch
                className={cn(
                    'fixed right-4 bottom-[calc(3.5rem+env(safe-area-inset-bottom)+1rem)] z-30 inline-flex h-14 items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/30 transition-transform outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 active:scale-95 md:hidden [&_svg]:size-5',
                    className,
                )}
            >
                {icono}
                {children}
            </Link>
        </>
    );
}
