import type { EstadoDocumento } from '@/types/fleet';

/**
 * Los problemas gritan y lo que está en regla se calla: un documento vigente
 * se lee en neutro y solo su ícono va en verde; lo que falta o venció tiñe la
 * ficha entera para que salte en la lista sin tener que leerla.
 *
 * Compartido por la tarjeta del expediente (`DocumentoTarjeta`) y la fila de
 * la tabla de documentos del vehículo, para que un «vencido» se vea igual en
 * los dos lados.
 */
export const estiloDocumento: Record<
    EstadoDocumento,
    { tarjeta: string; icono: string; chip: string; punto: string }
> = {
    vigente: {
        tarjeta:
            'border-border bg-card hover:border-zinc-300 dark:hover:border-zinc-700',
        icono: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        chip: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        punto: 'bg-emerald-500',
    },
    por_vencer: {
        tarjeta:
            'border-amber-300 bg-amber-50/60 hover:border-amber-400 dark:border-amber-900 dark:bg-amber-950/25',
        icono: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        chip: 'bg-amber-500 text-amber-950',
        punto: 'bg-amber-500',
    },
    vencido: {
        tarjeta:
            'border-red-300 bg-red-50/70 hover:border-red-400 dark:border-red-900 dark:bg-red-950/30',
        icono: 'bg-red-500/15 text-red-600 dark:text-red-400',
        chip: 'bg-red-600 text-white',
        punto: 'bg-red-500',
    },
    faltante: {
        tarjeta:
            'border-dashed border-zinc-300 bg-transparent hover:border-red-400 dark:border-zinc-700',
        icono: 'bg-muted text-muted-foreground',
        chip: 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
        punto: 'bg-zinc-400',
    },
};
