import type { EstadoEnvio } from '@/types/whatsapp';

/** Una alerta en la campanita o en la bandeja de quien la recibió. */
export type Alerta = {
    id: string;
    tipo: string;
    tipo_label: string;
    titulo: string;
    mensaje: string;
    leida: boolean;
    /** ISO 8601. */
    fecha: string;
};

/** Lo que trae cada página para la campanita del encabezado. */
export type Campanita = {
    sin_leer: number;
    recientes: Alerta[];
};

/** Cómo se avisa un tipo de alerta, tal como lo edita la configuración. */
export type ConfiguracionTipoAlerta = {
    tipo: string;
    label: string;
    descripcion: string;
    /** Sale una vez al día, a su hora; las demás, en el momento. */
    es_resumen: boolean;
    activa: boolean;
    por_app: boolean;
    por_whatsapp: boolean;
    roles: string[];
    usuarios: number[];
    areas: number[];
    hora: string | null;
    /** Quiénes la reciben hoy con esta configuración. */
    destinatarios: { nombre: string; con_celular: boolean }[];
};

export type UsuarioAlerta = {
    id: number;
    nombre: string;
    con_celular: boolean;
};

export type AreaAlerta = { id: number; nombre: string; activa: boolean };

/** Una alerta que salió por WhatsApp, para ver si están llegando. */
export type EnvioAlerta = {
    id: number;
    alerta: string;
    destino: string;
    estado: EstadoEnvio;
    estado_label: string;
    error: string | null;
    fecha: string;
};
