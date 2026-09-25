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
    /**
     * Como en una pantalla de salidas, a partir de las GR del día: con guía
     * ya salió; sin guía sigue programada, o queda para revisar si el día ya
     * pasó.
     */
    estado: EstadoSalida;
    /** La GR con la que salió, cuando salió. */
    numero_gr: string | null;
    /** A quién se le puede mandar el aviso, en orden de preferencia. */
    destinatarios: DestinatarioAviso[];
    /** Los avisos a abastecimiento y a facturación, ya armados. */
    avisos_area: AvisoDeArea[];
    /** El flete acordado en soles, o null si todavía no hay precio. */
    precio_flete: number | null;
    /** Si ese monto ya trae el IGV adentro o hay que sumárselo. */
    precio_incluye_igv: boolean;
    /** El celular del conductor, tal como está guardado, para poder editarlo. */
    telefono: string | null;
    /** Su otro celular. */
    telefono_alterno: string | null;
    /** El otro número al que avisar de esta salida, si se cargó uno. */
    whatsapp_adicional: string | null;
    /** El preaviso ya armado; lo arma el servidor para que sea uno solo. */
    mensaje_aviso: string;
    /** Cuándo se le mandó el preaviso al conductor; null si no se avisó. */
    aviso_enviado_at: string | null;
    /** Quién lo mandó. */
    aviso_enviado_por: string | null;
};

/** Un chat al que mandar el aviso: el número ya listo para `wa.me`. */
export type DestinatarioAviso = {
    /** `Conductor`, `Alterno` o `Adicional`. */
    etiqueta: string;
    numero: string;
};

/** El aviso que recibe un área de la casa por una salida programada. */
export type AvisoDeArea = {
    /** `Abastecimiento` o `Facturación`. */
    area: string;
    numero: string;
    mensaje: string;
};

/** El resumen del día para el número de operaciones, armado en el servidor. */
export type AvisoOperaciones = {
    whatsapp: string | null;
    mensaje: string;
};

export type EstadoSalida = 'despachado' | 'programado' | 'sin_gr';

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
    /** Con el que el alta express reconoce al cliente que acaba de crear. */
    ruc: string;
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
