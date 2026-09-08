import type { EstadoAsistencia } from '@/types/fleet';

/**
 * Los días de la semana en el orden en que se rotulan las grillas: de lunes
 * a domingo, igual que el calendario mensual que arma el backend. Las letras
 * son las mismas que manda `dia_semana`, así que el índice acá sirve para
 * saber en qué columna cae una fecha.
 */
export const diasSemana = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

/**
 * Color, letra y etiquetas de cada estado, compartido entre el rooster, el
 * calendario individual y la ficha del conductor.
 *
 * Hay tres variantes del mismo color según cuánto espacio hay: `badge` para
 * las celdas donde se lee la letra (rooster y calendario del año), `solido`
 * para el calendario chico de la ficha —ahí el día se pinta entero y el
 * número va encima— y `punto` para las listas de resumen, donde basta un
 * punto de color.
 *
 * `label` describe un día suelto («Descanso», para el tooltip de una celda) y
 * `labelResumen` la cuenta del mes («Días de descanso: 8»).
 */
export const estadoConfig: Record<
    EstadoAsistencia,
    {
        label: string;
        labelResumen: string;
        letra: string;
        badge: string;
        solido: string;
        punto: string;
    }
> = {
    asistencia: {
        label: 'Asistencia',
        labelResumen: 'Días trabajados',
        letra: 'A',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        solido: 'bg-emerald-500 text-white',
        punto: 'bg-emerald-500',
    },
    falta: {
        label: 'Falta',
        labelResumen: 'Faltas',
        letra: 'F',
        badge: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        solido: 'bg-red-500 text-white',
        punto: 'bg-red-500',
    },
    vacaciones: {
        label: 'Vacaciones',
        labelResumen: 'Vacaciones',
        letra: 'V',
        badge: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        solido: 'bg-sky-500 text-white',
        punto: 'bg-sky-500',
    },
    descanso: {
        label: 'Descanso',
        labelResumen: 'Días de descanso',
        letra: 'D',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        solido: 'bg-amber-500 text-white',
        punto: 'bg-amber-500',
    },
};
