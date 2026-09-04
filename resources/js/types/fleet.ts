import type { StatusTone } from '@/components/ui/status-badge';

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
    fecha_baja: string | null;
    motivo_baja: string | null;
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
    fecha_baja: string | null;
    motivo_baja: string | null;
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
    tone: StatusTone;
};

/**
 * Visual config per vehicle status (label, status tone).
 */
export const estadoConfig: Record<string, EstadoConfig> = {
    activo: {
        label: 'Operativo',
        tone: 'success',
    },
    en_mantenimiento: {
        label: 'En mantenimiento',
        tone: 'warning',
    },
    inactivo: {
        label: 'Inactivo',
        tone: 'neutral',
    },
    dado_de_baja: {
        label: 'Dado de baja',
        tone: 'danger',
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
    /** GR(s) del remitente (cliente) referidas en la GR-transportista. Vacío si el PDF no traía ninguna. */
    guias_remitente: { numero: string; ruc: string }[] | null;
    /** Misma clave → misma salida física del camión (heurística: fecha + tracto + carreta + conductor). */
    grupo_viaje: string;
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
    /** Dirección completa; para la tabla usa `origen_ciudad`. */
    origen: string;
    /** Ciudad (distrito) de origen, ej. «ANTAUTA» — no el departamento. */
    origen_ciudad: string;
    /** Dirección completa; para la tabla usa `destino_ciudad`. */
    destino: string;
    /** Ciudad (distrito) de destino, ej. «PARACAS» — no el departamento. */
    destino_ciudad: string;
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

/** Un día de la grilla del calendario individual: puede ser relleno del mes vecino. */
export type AsistenciaCalendarioDia = AsistenciaDia & {
    es_relleno: boolean;
};

/** El conductor dueño del calendario individual de asistencia. */
export type AsistenciaConductor = {
    id: number;
    nombre_completo: string;
};

/** Un mes completo del calendario individual, con su propia grilla y marcas. */
export type AsistenciaCalendarioMes = {
    mes: string;
    dias: AsistenciaCalendarioDia[];
    marcas: Record<string, AsistenciaMarca>;
    /** Días de descanso que se le deben al conductor ese mes: lo escribe el admin a mano. */
    dias_debidos: number;
    /** Notas libres del mes —incidencias, acuerdos verbales, etc.—, también a mano. */
    notas: string | null;
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
