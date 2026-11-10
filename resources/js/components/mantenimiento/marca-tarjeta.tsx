import type {
    EstadoMantenimiento,
    UnidadMantenimiento,
} from '@/types/mantenimiento';

export type ConteoEstados = Record<EstadoMantenimiento, number>;

/** El color del número de cada estado del semáforo. */
export const colorEstado: Record<EstadoMantenimiento, string> = {
    vencido: 'text-red-700 dark:text-red-400',
    proximo: 'text-amber-700 dark:text-amber-400',
    al_dia: 'text-emerald-700 dark:text-emerald-400',
    sin_datos: 'text-muted-foreground',
};

export const barraEstado: Record<EstadoMantenimiento, string> = {
    vencido: 'bg-red-500',
    proximo: 'bg-amber-500',
    al_dia: 'bg-emerald-500',
    sin_datos: 'bg-zinc-300 dark:bg-zinc-600',
};

export const ordenEstados: EstadoMantenimiento[] = [
    'vencido',
    'proximo',
    'al_dia',
    'sin_datos',
];

export function contarPorEstado(
    unidades: UnidadMantenimiento[],
): ConteoEstados {
    const conteo = { vencido: 0, proximo: 0, al_dia: 0, sin_datos: 0 };

    for (const unidad of unidades) {
        conteo[unidad.estado]++;
    }

    return conteo;
}
