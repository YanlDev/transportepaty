/**
 * De quién es la carga: de la mina (todo lo de Minsur: concentrado,
 * metálico, escoria, materiales, Ransa, sacos) o particular. El vacío no es
 * de nadie. Cada clase tiene su color, el mismo en todo el sistema, y no se
 * cruza con los de las etapas (celeste, ámbar, violeta, verde).
 */
export type ClaseCarga = 'mina' | 'particular';

export type Clase = {
    clase: ClaseCarga;
    titulo: string;
    /** El punto de color, para los filtros. */
    punto: string;
    /** Fondo suave con borde, para chips y etiquetas. */
    suave: string;
};

export const CLASES: Clase[] = [
    {
        clase: 'mina',
        titulo: 'Mina',
        punto: 'bg-stone-600 dark:bg-stone-300',
        suave: 'border-stone-300 bg-stone-100 text-stone-800 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-200',
    },
    {
        clase: 'particular',
        titulo: 'Particular',
        punto: 'bg-rose-500',
        suave: 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
    },
];

export function claseDe(clase: ClaseCarga): Clase {
    return CLASES.find((opcion) => opcion.clase === clase) ?? CLASES[0];
}

/** La clase de una carga; null si va vacía. */
export function claseDeCarga(tipoCarga: string): ClaseCarga | null {
    if (tipoCarga === 'vacio') {
        return null;
    }

    return tipoCarga === 'particular' ? 'particular' : 'mina';
}

/** Las clases de las cargas de un ticket, mina primero: [] si va todo vacío. */
export function clasesDeTicket(
    paradas: { tipo_carga: string }[],
): ClaseCarga[] {
    const clases = new Set(
        paradas.map((parada) => claseDeCarga(parada.tipo_carga)),
    );

    return CLASES.map((opcion) => opcion.clase).filter((clase) =>
        clases.has(clase),
    );
}
