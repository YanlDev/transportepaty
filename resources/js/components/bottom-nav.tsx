import { Link } from '@inertiajs/react';
import {
    CalendarCheck,
    IconContext,
    IdentificationCard,
    Path,
    Receipt,
    SquaresFour,
    Truck,
} from '@phosphor-icons/react';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import vehiculos from '@/actions/App/Http/Controllers/VehiculoController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * Navegación de pulgar para el celular: los destinos del día a día quedan
 * abajo, al alcance de una mano, en vez de detrás del botón de menú de la
 * esquina superior —el punto más difícil de tocar sosteniendo el teléfono.
 *
 * Solo se ve por debajo de `md`; en escritorio manda el sidebar de siempre.
 * Deja fuera lo que no es tarea de calle (Clientes, Usuarios, Configuración):
 * eso sigue viviendo en el menú lateral, que también funciona en móvil. Más
 * de cinco destinos acá y ninguno se puede tocar sin errarle.
 */
export function BottomNav() {
    const { esAdmin, esContador, puedeVerOperacion } = usePermisos();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const items: NavItem[] = [
        { title: 'Inicio', href: dashboard(), icon: SquaresFour },
        { title: 'Tractos', href: vehiculos.tractos(), icon: Truck },
        ...(puedeVerOperacion || esContador
            ? [{ title: 'Viajes', href: viajes.index(), icon: Path }]
            : []),
        ...(puedeVerOperacion
            ? [
                  {
                      title: 'Conductores',
                      href: conductores.index(),
                      icon: IdentificationCard,
                  },
              ]
            : []),
        // Para el contador la cobranza es su pantalla, no una más: por eso
        // entra al pulgar aunque el admin ya tenga cinco destinos acá.
        ...(esContador
            ? [
                  {
                      title: 'Cobranza',
                      href: contabilidad.index(),
                      icon: Receipt,
                  },
              ]
            : []),
        ...(esAdmin
            ? [
                  {
                      title: 'Asistencia',
                      href: asistencia.index(),
                      icon: CalendarCheck,
                  },
              ]
            : []),
    ];

    return (
        <nav
            aria-label="Navegación principal"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-sidebar-border/70 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:hidden"
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <IconContext.Provider value={{ weight: 'duotone' }}>
                <ul className="flex items-stretch justify-around">
                    {items.map((item) => {
                        const activo = isCurrentOrParentUrl(item.href);

                        return (
                            <li key={item.title} className="flex-1">
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={activo ? 'page' : undefined}
                                    // min-h-14: área táctil cómoda (56px) para
                                    // el pulgar, por encima de los 44px mínimos.
                                    className={cn(
                                        'flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-1.5 text-[10px] font-medium transition-colors',
                                        activo
                                            ? 'text-primary'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {item.icon && (
                                        <item.icon className="size-5" />
                                    )}
                                    <span className="truncate">
                                        {item.title}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </IconContext.Provider>
        </nav>
    );
}
