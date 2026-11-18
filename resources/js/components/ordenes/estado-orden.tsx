import { cn } from '@/lib/utils';

/** El color de cada estado, igual en la lista del mecánico y en la de taller. */
const TONOS: Record<string, string> = {
    solicitada: 'bg-violet-500/15 text-violet-700 dark:text-violet-300',
    pendiente: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    en_proceso: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    por_revisar: 'bg-amber-500/20 text-amber-800 dark:text-amber-300',
    observada: 'bg-red-500/15 text-red-700 dark:text-red-300',
    finalizada: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    anulada: 'bg-muted text-muted-foreground line-through',
};

/** En qué va una orden de trabajo, como etiqueta de color. */
export function EstadoOrden({
    estado,
    etiqueta,
    className,
}: {
    estado: string;
    etiqueta: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                TONOS[estado] ?? TONOS.pendiente,
                className,
            )}
        >
            {etiqueta}
        </span>
    );
}
