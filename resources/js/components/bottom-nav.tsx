import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Menu, Search } from 'lucide-react';
import type { ElementType, ReactNode } from 'react';
import { abrirBuscador } from '@/components/buscador-global';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermisos } from '@/hooks/use-permisos';
import { destinosMoviles } from '@/lib/destinos-movil';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

// min-h-14: área táctil cómoda (56px) para el pulgar, por encima de los 44px
// mínimos.
const CLASE_ITEM =
    'relative flex min-h-14 w-full flex-col items-center justify-center gap-0.5 px-1 py-1.5 text-[11px] font-medium transition-colors';

/**
 * Navegación de pulgar para el celular: lo de todos los días queda abajo, al
 * alcance de una mano, en vez de detrás del botón de menú de la esquina
 * superior —el punto más difícil de tocar sosteniendo el teléfono.
 *
 * Cinco lugares y no más (con seis ninguno se toca sin errarle): Inicio, las
 * dos áreas donde la persona trabaja, Buscar y «Más», que abre el menú
 * completo. Las áreas salen de sus permisos (`destinosMoviles`), así taller,
 * almacén, abastecimiento y cobranza tienen cada uno su pantalla a un toque.
 *
 * Solo se ve por debajo de `md`; en escritorio manda el sidebar de siempre.
 */
export function BottomNav() {
    const { puede } = usePermisos();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { pendientesMenu } = usePage().props;
    const { toggleSidebar } = useSidebar();

    const conInicio = puede('tablero.ver');
    const destinos = destinosMoviles(puede, conInicio ? 2 : 3);

    return (
        <nav
            aria-label="Navegación principal"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-sidebar-border/70 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:hidden"
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <ul className="flex items-stretch justify-around">
                {conInicio && (
                    <Destino
                        titulo="Inicio"
                        icono={LayoutGrid}
                        href={dashboard()}
                        activo={isCurrentOrParentUrl(dashboard())}
                    />
                )}
                {destinos.map((destino) => (
                    <Destino
                        key={destino.clave}
                        titulo={destino.titulo}
                        icono={destino.icono}
                        href={destino.href}
                        activo={isCurrentOrParentUrl(destino.href)}
                        pendientes={
                            destino.pendientes
                                ? pendientesMenu[destino.pendientes]
                                : undefined
                        }
                    />
                ))}
                <li className="flex-1">
                    <button
                        type="button"
                        onClick={abrirBuscador}
                        className={cn(
                            CLASE_ITEM,
                            'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Search className="size-5" />
                        <span className="truncate">Buscar</span>
                    </button>
                </li>
                <li className="flex-1">
                    <button
                        type="button"
                        onClick={toggleSidebar}
                        aria-label="Más: abrir el menú completo"
                        className={cn(
                            CLASE_ITEM,
                            'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Menu className="size-5" />
                        <span className="truncate">Más</span>
                    </button>
                </li>
            </ul>
        </nav>
    );
}

function Destino({
    titulo,
    icono: Icono,
    href,
    activo,
    pendientes,
}: {
    titulo: string;
    icono: ElementType;
    href: React.ComponentProps<typeof Link>['href'];
    activo: boolean;
    pendientes?: number;
}): ReactNode {
    return (
        <li className="min-w-0 flex-1">
            <Link
                href={href}
                prefetch
                aria-current={activo ? 'page' : undefined}
                className={cn(
                    CLASE_ITEM,
                    activo
                        ? 'text-primary'
                        : 'text-muted-foreground hover:text-foreground',
                )}
            >
                <span className="relative">
                    <Icono className="size-5" />
                    {pendientes !== undefined && pendientes > 0 && (
                        <span
                            className="absolute -top-1.5 -right-2.5 flex min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] leading-4 font-semibold text-white tabular-nums"
                            aria-label={`${pendientes} pendientes`}
                        >
                            {pendientes > 99 ? '99+' : pendientes}
                        </span>
                    )}
                </span>
                <span className="max-w-full truncate">{titulo}</span>
            </Link>
        </li>
    );
}
