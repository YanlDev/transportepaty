import type { EstadoAsistencia } from '@/types/fleet';

/** Color y letra de cada estado, compartido entre el rooster y el calendario individual. */
export const estadoConfig: Record<
    EstadoAsistencia,
    { label: string; letra: string; badge: string }
> = {
    asistencia: {
        label: 'Asistencia',
        letra: 'A',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    falta: {
        label: 'Falta',
        letra: 'F',
        badge: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    },
    vacaciones: {
        label: 'Vacaciones',
        letra: 'V',
        badge: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    descanso: {
        label: 'Descanso',
        letra: 'D',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
};
