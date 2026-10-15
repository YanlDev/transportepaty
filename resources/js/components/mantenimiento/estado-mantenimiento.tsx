import { StatusBadge } from '@/components/ui/status-badge';
import type { StatusTone } from '@/components/ui/status-badge';
import { cn } from '@/lib/utils';
import type { EstadoMantenimiento } from '@/types/mantenimiento';

const tonos: Record<EstadoMantenimiento, StatusTone> = {
    vencido: 'danger',
    proximo: 'warning',
    al_dia: 'success',
    sin_datos: 'neutral',
};

/** El mismo semáforo que los documentos: rojo, ámbar, verde y gris. */
export function EstadoMantenimientoBadge({
    estado,
    label,
    className,
}: {
    estado: EstadoMantenimiento;
    label: string;
    className?: string;
}) {
    return (
        <StatusBadge label={label} tone={tonos[estado]} className={className} />
    );
}

/** El borde izquierdo de la tarjeta, para leer la flota de un vistazo. */
export const bordeEstado: Record<EstadoMantenimiento, string> = {
    vencido: 'border-l-red-500',
    proximo: 'border-l-amber-500',
    al_dia: 'border-l-emerald-500',
    sin_datos: 'border-l-zinc-300 dark:border-l-zinc-600',
};

export function formatearKm(km: number | null | undefined): string {
    return km === null || km === undefined
        ? '—'
        : `${km.toLocaleString('es-PE')} km`;
}

/**
 * «Faltan 2,300 km» o «Pasado por 1,200 km», con el color del semáforo.
 */
export function FaltanKm({
    faltan,
    estado,
    className,
}: {
    faltan: number | null;
    estado: EstadoMantenimiento;
    className?: string;
}) {
    if (faltan === null) {
        return (
            <span className={cn('text-muted-foreground', className)}>
                Sin historial
            </span>
        );
    }

    return (
        <span
            className={cn(
                'font-medium tabular-nums',
                estado === 'vencido' && 'text-red-700 dark:text-red-400',
                estado === 'proximo' && 'text-amber-700 dark:text-amber-400',
                className,
            )}
        >
            {faltan > 0
                ? `Faltan ${formatearKm(faltan)}`
                : `Pasado por ${formatearKm(Math.abs(faltan))}`}
        </span>
    );
}
