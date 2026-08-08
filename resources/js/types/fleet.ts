export type EnumOption = {
    value: string;
    label: string;
};

export type Conductor = {
    id: number;
    user_id: number | null;
    nombres: string;
    apellidos: string;
    documento: string;
    licencia: string | null;
    categoria_licencia: string | null;
    licencia_vence: string | null;
    telefono: string | null;
    email: string | null;
    fecha_nacimiento: string | null;
    procedencia: string | null;
    activo: boolean;
    nombre_completo: string;
};

export type ConductorListItem = {
    id: number;
    nombres: string;
    apellidos: string;
    nombre_completo: string;
    documento: string;
    licencia: string | null;
    categoria_licencia: string | null;
    licencia_vence: string | null;
    telefono: string | null;
    email: string | null;
    procedencia: string | null;
    activo: boolean;
    documentacion: EstadoDocumental;
};

/** A user account as listed in the admin users screen. */
export type UserListItem = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: { id: number; name: string }[];
};

/** A conductor option for linking from the user form. */
export type ConductorLinkOption = {
    id: number;
    nombres: string;
    apellidos: string;
    nombre_completo: string;
};

/** Situación de un documento obligatorio concreto. */
export type EstadoDocumento = 'vigente' | 'por_vencer' | 'vencido' | 'faltante';

/** Un documento obligatorio con su situación, para listarlos uno por uno. */
export type DocumentoResumen = {
    tipo: string;
    /** Etiqueta corta para la tabla; el nombre largo va en `label`. */
    abreviatura: string;
    label: string;
    estado: EstadoDocumento;
    estado_label: string;
    /** Formato Y-m-d; null si el documento no vence o no está cargado. */
    vence: string | null;
};

/**
 * Semáforo y listas de problemas. Verde: todo presente y vigente. Ámbar: algo
 * vence dentro de 30 días. Rojo: falta algo o ya venció.
 *
 * Es lo único que se puede agregar entre varios vehículos, así que es la forma
 * que toma la documentación de una unidad completa (tracto + carreta).
 */
export type ResumenDocumental = {
    semaforo: 'verde' | 'ambar' | 'rojo';
    faltantes: string[];
    vencidos: string[];
    por_vencer: string[];
};

/**
 * La documentación de un vehículo concreto: el resumen más el detalle documento
 * por documento, que solo tiene sentido para una sola unidad.
 */
export type EstadoDocumental = ResumenDocumental & {
    documentos: DocumentoResumen[];
};

export type VehiculoListItem = {
    id: number;
    placa: string;
    marca: string | null;
    anio: number | null;
    tipo: string;
    tipo_label: string;
    estado: string;
    caja: string | null;
    caja_label: string | null;
    color: string | null;
    ejes: number | null;
    /** Número de la habilitación MTC (TUC); null si no está cargado. */
    tuc_numero: string | null;
    documentacion: EstadoDocumental;
};

export type Vehiculo = {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    anio: number | null;
    tipo: string;
    estado: string;
    caja: string | null;
    vin: string | null;
    numero_motor: string | null;
    color: string | null;
    ejes: number | null;
    peso_neto: number | null;
    peso_bruto: number | null;
    carga_util: number | null;
    fecha_adquisicion: string | null;
    observaciones: string | null;
    created_at: string;
    updated_at: string;
};

export type VehiculoDocumentoItem = {
    id: number;
    tipo: string;
    tipo_label: string;
    nombre: string | null;
    numero: string | null;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    url: string;
    es_pdf: boolean;
};

/**
 * Una posición fija del expediente del vehículo. `documento` en null es un hueco
 * a la vista: el papel que falta no desaparece de la lista ni deja que otro le
 * corra el lugar.
 */
export type RanuraDocumental = {
    tipo: string;
    abreviatura: string;
    label: string;
    estado: EstadoDocumento;
    estado_label: string;
    /** false para los papeles sueltos («Otro»), que van al final. */
    obligatorio: boolean;
    documento: VehiculoDocumentoItem | null;
};

/** Una unidad armada: conductor + tracto + carreta, tal como se lista. */
export type AsignacionListItem = {
    id: number;
    conductor: {
        id: number;
        nombre_completo: string;
        telefono: string | null;
    };
    tracto: {
        id: number;
        placa: string;
        marca: string | null;
        tuc_numero: string | null;
    };
    /** Null cuando el tracto está asignado sin carreta. */
    carreta: { id: number; placa: string; tuc_numero: string | null } | null;
    desde: string;
    /** Null mientras la asignación sigue vigente. */
    hasta: string | null;
    observaciones: string | null;
    vigente: boolean;
    /** Peor semáforo entre el tracto y la carreta de la unidad. */
    documentacion: ResumenDocumental;
};

/** La asignación tal como la carga el formulario de edición. */
export type Asignacion = {
    id: number;
    conductor_id: number;
    tracto_id: number;
    tracto_placa: string;
    carreta_id: number | null;
    desde: string;
    observaciones: string | null;
};

export type ConductorOption = {
    id: number;
    nombre_completo: string;
    telefono: string | null;
};

export type VehiculoOption = {
    id: number;
    placa: string;
    descripcion: string;
};

/** Un tracto parado, sin conductor asignado. */
export type TractoLibre = {
    id: number;
    placa: string;
    marca: string | null;
    tuc_numero: string | null;
    estado: string;
    caja_label: string | null;
    documentacion: EstadoDocumental;
};

/** Una carreta sin enganchar a ninguna unidad. */
export type CarretaLibre = {
    id: number;
    placa: string;
    marca: string | null;
    estado: string;
    documentacion: EstadoDocumental;
};

/** Un conductor activo que hoy no maneja ninguna unidad. */
export type ConductorLibre = {
    id: number;
    nombre_completo: string;
    telefono: string | null;
    licencia: string | null;
    categoria_licencia: string | null;
    documentacion: EstadoDocumental;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type EstadoConfig = {
    label: string;
    badge: string;
    dot: string;
};

/**
 * Visual config per vehicle status (label, badge classes, indicator dot).
 */
export const estadoConfig: Record<string, EstadoConfig> = {
    activo: {
        label: 'Operativo',
        badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
        dot: 'bg-emerald-500',
    },
    en_mantenimiento: {
        label: 'En mantenimiento',
        badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
        dot: 'bg-amber-500',
    },
    inactivo: {
        label: 'Inactivo',
        badge: 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-500/20',
        dot: 'bg-zinc-400',
    },
    dado_de_baja: {
        label: 'Dado de baja',
        badge: 'bg-red-50 text-red-700 ring-1 ring-red-600/20',
        dot: 'bg-red-500',
    },
};

export function estadoInfo(estado: string): EstadoConfig {
    return estadoConfig[estado] ?? estadoConfig.inactivo;
}

export const tipoLabels: Record<string, string> = {
    tracto: 'Tracto',
    carreta: 'Carreta',
};

export const cajaLabels: Record<string, string> = {
    mecanica: 'Mecánica',
    automatica: 'Automática',
};

/** Un viaje registrado a partir de la GR-transportista subida. */
export type ViajeListItem = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
    placa_tracto: string;
    placa_carreta: string | null;
    /** Null cuando la placa no matcheó contra el padrón. */
    tracto_id: number | null;
    carreta_id: number | null;
    conductor_nombre: string;
    /** Null cuando el DNI no matcheó contra el padrón. */
    conductor_id: number | null;
    cliente: string;
    destinatario: string;
    origen: string;
    /** Dirección completa; para la tabla usa `destino_region`. */
    destino: string;
    /** Departamento/región, el final de la dirección (ej. «PUNO»). */
    destino_region: string;
    tipo_carga: string;
    tipo_carga_label: string;
    peso: number;
    unidad_peso: string;
    /** Null si por alguna razón el PDF no quedó adjunto. */
    archivo_url: string | null;
};

/** El estado de un conductor en un día puntual del rooster. */
export type EstadoAsistencia =
    | 'asistencia'
    | 'falta'
    | 'vacaciones'
    | 'descanso';

/** Una columna del rooster: un día del mes consultado. */
export type AsistenciaDia = {
    numero: number;
    fecha: string;
    /** L, M, X, J, V, S o D. */
    dia_semana: string;
    es_domingo: boolean;
};

/** Una celda marcada del rooster. Sin entrada para una fecha = sin marcar. */
export type AsistenciaMarca = {
    asistencia_id: number;
    estado: EstadoAsistencia;
    estado_label: string;
};

/** Una fila del rooster: un conductor y sus marcas del mes, por fecha. */
export type AsistenciaFila = {
    conductor_id: number;
    nombre_completo: string;
    marcas: Record<string, AsistenciaMarca>;
};

/** Lo que un aviso de WhatsApp reportó para un tracto: carga o libre. */
export type EstadoProgramacion =
    | 'metalico'
    | 'concentrado'
    | 'escoria'
    | 'ransa'
    | 'polytex'
    | 'particular'
    | 'salida'
    | 'libre';

/** La marca de un tracto para un día. Sin marca = sin aviso todavía. */
export type ProgramacionMarca = {
    programacion_id: number;
    estado: EstadoProgramacion;
    estado_label: string;
    /** Vistazo rápido: cuál era el estado justo antes de este cambio. */
    estado_anterior_label: string | null;
    /** Hora (H:i) del último cambio de estado, o null si nunca cambió hoy. */
    estado_cambiado_en: string | null;
    /** Hacia dónde va la unidad (Callao, Pisco, mina...). */
    destino: string | null;
    /** El cliente final del viaje, solo cuando `estado` es "particular". */
    cliente: string | null;
    observaciones: string | null;
};

/** Una fila del tablero de programación: un tracto y su marca del día. */
export type ProgramacionFila = {
    vehiculo_id: number;
    placa: string;
    caja_label: string | null;
    conductor_nombre: string | null;
    marca: ProgramacionMarca | null;
};

export type NovedadItem = {
    id: number;
    tracto_id: number;
    placa: string;
    tipo: string;
    tipo_label: string;
    motivo: string;
    desde: string;
    vigente: boolean;
};
