/**
 * Los números del tablero. Son agregados que el backend calcula por rango y no
 * corresponden a ninguna tabla, por eso viven acá y no en `fleet.ts`.
 */

/** El compromiso del mes: 120 viajes de concentrado y a qué ritmo va. */
export type MetaConcentrado = {
    meta: number;
    realizados: number;
    faltantes: number;
    diasRestantes: number;
    proyeccion: number;
    /** Null cuando ya no quedan días: no hay ritmo que alcance. */
    ritmoNecesario: number | null;
};

export type Documentos = {
    vigentes: number;
    vencidos: number;
    por_vencer: number;
    sin_fecha: number;
    total: number;
};

export type Unidades = {
    operativas: number;
    no_programables: number;
    con_documentos_vencidos: number;
    total: number;
};

export type ResumenFlota = {
    tractos: number;
    carretas: number;
    operativos: number;
    conductores: number;
    conductoresRegistrados: number;
    novedadesActivas: number;
    documentosVencidos: number;
};
