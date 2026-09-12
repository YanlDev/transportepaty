/**
 * Una unidad programada con carga particular para un día. Se carga antes de
 * que exista la guía de remisión, así que no sale de `viajes`.
 */
export type ProgramacionTarjeta = {
    id: number;
    fecha: string;
    vehiculo_id: number;
    placa: string;
    conductor_id: number;
    conductor: string;
    cliente_id: number;
    /** El alias del cliente: el nombre corto, y el que colorea la tarjeta. */
    cliente: string;
    destino: string;
};

export type DiaDeSemana = {
    fecha: string;
    programadas: number;
};

export type UnidadOpcion = {
    id: number;
    placa: string;
};

export type ConductorOpcion = {
    id: number;
    nombre: string;
};

export type ClienteOpcion = {
    id: number;
    alias: string;
};

/**
 * Con qué tracto salió cada conductor la última vez, según sus guías.
 * Alimenta el prellenado del formulario, indexado por `conductor_id`.
 */
export type UltimoViaje = {
    vehiculo_id: number;
    placa: string;
    fecha: string;
};
