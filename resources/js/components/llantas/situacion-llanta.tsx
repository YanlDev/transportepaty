import { cn } from '@/lib/utils';
import type { SituacionLlanta } from '@/types/llantas';

const SITUACIONES: Record<SituacionLlanta, { titulo: string; clase: string }> =
    {
        almacen: {
            titulo: 'En almacén',
            clase: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        },
        entregada: {
            titulo: 'Entregada, sin montar',
            clase: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        },
        montada: {
            titulo: 'Montada',
            clase: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        },
        retirada: {
            titulo: 'Retirada',
            clase: 'bg-zinc-200 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
        },
        baja: {
            titulo: 'De baja',
            clase: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        },
    };

/** Dónde está la llanta, de un vistazo. */
export function SituacionDeLlanta({
    situacion,
    className,
}: {
    situacion: SituacionLlanta;
    className?: string;
}) {
    const { titulo, clase } = SITUACIONES[situacion];

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                clase,
                className,
            )}
        >
            {titulo}
        </span>
    );
}
