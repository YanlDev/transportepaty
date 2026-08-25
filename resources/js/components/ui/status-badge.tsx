import { cn } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'neutral' | 'danger' | 'info';

const toneStyles: Record<StatusTone, { badge: string; dot: string }> = {
    success: {
        badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-950 dark:text-emerald-300',
        dot: 'bg-emerald-500',
    },
    warning: {
        badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300',
        dot: 'bg-amber-500',
    },
    neutral: {
        badge: 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300',
        dot: 'bg-zinc-400',
    },
    danger: {
        badge: 'bg-red-50 text-red-700 ring-1 ring-red-600/20 dark:bg-red-950 dark:text-red-300',
        dot: 'bg-red-500',
    },
    info: {
        badge: 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20 dark:bg-sky-950 dark:text-sky-300',
        dot: 'bg-sky-500',
    },
};

type StatusBadgeProps = {
    label: string;
    tone: StatusTone;
    /** Punto de color junto al texto. Default true. */
    dot?: boolean;
    className?: string;
};

/**
 * Badge de estado semántico (pill + punto de color), pensado para el estado
 * "sí/no" de una entidad (activo/inactivo, etc.). Distinto de `Badge`
 * (`ui/badge.tsx`), que es para acciones/variantes visuales arbitrarias, no
 * para significado de estado.
 */
export function StatusBadge({
    label,
    tone,
    dot = true,
    className,
}: StatusBadgeProps) {
    const estilos = toneStyles[tone];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                estilos.badge,
                className,
            )}
        >
            {dot && <span className={cn('size-1.5 rounded-full', estilos.dot)} />}
            {label}
        </span>
    );
}
