import type { EtapaVuelta } from '@/types/vueltas';

export type Etapa = {
    etapa: EtapaVuelta;
    titulo: string;
    ayuda: string;
    /** El punto de color: en el Gantt, las listas y las etiquetas. */
    punto: string;
    /** El texto en el color de la etapa (ida en ámbar, vuelta en violeta). */
    texto: string;
    /** Fondo suave con borde, para etiquetas y tarjetas. */
    suave: string;
};

/**
 * Las etapas del ticket, con un color cada una, el mismo en todo el
 * sistema: por salir celeste, en ida ámbar, en vuelta violeta, en base
 * verde. Es lo primero que se aprende a leer: cualquier pantalla que
 * muestre un ticket usa estos colores.
 */
export const ETAPAS: Etapa[] = [
    {
        etapa: 'programada',
        titulo: 'Por salir',
        ayuda: 'Programadas, todavía sin GR',
        punto: 'bg-sky-500',
        texto: 'text-sky-700 dark:text-sky-400',
        suave: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
    },
    {
        etapa: 'en_ida',
        titulo: 'En ida',
        ayuda: 'De Juliaca o la mina hacia Pisco o Lima',
        punto: 'bg-amber-500',
        texto: 'text-amber-700 dark:text-amber-400',
        suave: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    },
    {
        etapa: 'en_vuelta',
        titulo: 'En vuelta',
        ayuda: 'Regresando, todavía no llega a la base',
        punto: 'bg-violet-500',
        texto: 'text-violet-700 dark:text-violet-400',
        suave: 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200',
    },
    {
        etapa: 'en_base',
        titulo: 'En base',
        ayuda: 'En Juliaca o la mina, disponibles para programar',
        punto: 'bg-emerald-500',
        texto: 'text-emerald-700 dark:text-emerald-400',
        suave: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    },
];

export function etapaDe(etapa: EtapaVuelta): Etapa {
    return ETAPAS.find((opcion) => opcion.etapa === etapa) ?? ETAPAS[0];
}

/** El color del tramo: la ida en ámbar, la vuelta (regreso) en violeta. */
export function colorDeSentido(sentido: 'ida' | 'regreso'): string {
    return etapaDe(sentido === 'ida' ? 'en_ida' : 'en_vuelta').texto;
}
