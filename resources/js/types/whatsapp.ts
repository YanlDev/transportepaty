/** En qué quedó un mensaje de WhatsApp que se mandó. */
export type EstadoEnvio =
    | 'pendiente'
    | 'enviado'
    | 'entregado'
    | 'leido'
    | 'fallido';

/** Hasta dónde llegó el último aviso por WhatsApp a un destino. */
export type ResumenEnvio = {
    estado: EstadoEnvio;
    estado_label: string;
    destino: string;
    /** La hora del último avance (enviado, entregado o leído). */
    hora: string;
    error: string | null;
};
