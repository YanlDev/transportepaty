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

/** De dónde salió el valor de un campo del estado diario. */
export type OrigenDato = 'importado' | 'deducido' | 'manual';

/** Gravedad de una inconsistencia. Ninguna bloquea el guardado. */
export type NivelAlerta = 'imposible' | 'improbable';

export type AlertaEstado = {
    tipo: string;
    label: string;
    nivel: NivelAlerta;
    nivel_label: string;
    detalle: string | null;
};

export type PuntoRef = {
    id: number;
    nombre: string;
};

/** Una unidad en la disponibilidad de un día, con sus alertas ya calculadas. */
export type DisponibilidadFila = {
    id: number | null;
    tracto: { id: number; placa: string; caja: string | null };
    carreta: { id: number; placa: string } | null;
    conductor: { id: number; nombre_completo: string } | null;
    tipo_carga: string | null;
    tipo_carga_label: string | null;
    estado_carga: string | null;
    estado_carga_label: string | null;
    cliente: string | null;
    cliente_label: string | null;
    fase: string | null;
    fase_label: string | null;
    origen: PuntoRef | null;
    destino: PuntoRef | null;
    ubicacion: PuntoRef | null;
    ubicacion_texto: string | null;
    observaciones: string | null;
    proximas_paradas: string | null;
    fecha_disponible: string | null;
    origenes: Record<string, OrigenDato>;
    asignacion_vigente: {
        conductor: string | null;
        carreta: string | null;
    } | null;
    alertas: AlertaEstado[];
    imposibles: number;
    improbables: number;
};

export type UbicacionOption = EnumOption & { es_zona_base: boolean };

export type DisponibilidadOpciones = {
    carretas: EnumOption[];
    conductores: EnumOption[];
    ubicaciones: UbicacionOption[];
    cargas: EnumOption[];
    estados_carga: EnumOption[];
    clientes: EnumOption[];
    fases: EnumOption[];
    cajas: EnumOption[];
};

export type DisponibilidadResumen = {
    total: number;
    reportadas: number;
    imposibles: number;
    improbables: number;
    sin_resolver: number;
};

export type UnidadEnPunto = {
    id: number;
    placa: string;
    tipo_carga_label: string | null;
    destino: string | null;
    fecha: string;
};

/** Un punto del mapa con todas las unidades reportadas ahí. */
export type PuntoMapa = {
    id: number;
    nombre: string;
    latitud: number;
    longitud: number;
    es_zona_base: boolean;
    unidades: UnidadEnPunto[];
    total: number;
};

export type EstimacionLlegada = {
    dias: number;
    fecha: string;
    label: string;
    kilometros: number;
    /** False mientras la estimación se apoye en la velocidad de referencia. */
    calibrada: boolean;
};

export type DescargaUnidad = {
    estado_id: number;
    tracto_id: number;
    placa: string;
    carreta: string | null;
    conductor: string | null;
    tipo_carga_label: string | null;
    ubicacion: string | null;
    destino_id: number | null;
    destino: string | null;
    reportado: string;
    fecha_estimada: string;
    estimacion: EstimacionLlegada;
};

export type GrupoDescarga = {
    destino_id: number;
    destino: string;
    unidades: DescargaUnidad[];
    total: number;
};

export type ResumenFlota = {
    unidades: number;
    en_base: number;
    sin_posicion: number;
    estimacion_calibrada: boolean;
    kilometros_por_dia: number;
};

export type PasoLineaTiempo = {
    id: number;
    fecha: string;
    ubicacion: string | null;
    tipo_carga_label: string | null;
    fase_label: string | null;
    ruta: { origen: string | null; destino: string | null } | null;
    conductor: string | null;
    carreta: string | null;
    observaciones: string | null;
};

export type GuiaRemision = {
    tipo: 'remitente' | 'transportista';
    label: string;
    /** GRR o GRT. */
    abreviatura: string;
    numero: string | null;
    /** Null mientras no se haya adjuntado el archivo. */
    url: string | null;
    es_pdf: boolean;
};

export type ViajeListItem = {
    id: number;
    tracto: { id: number; placa: string };
    carreta: { id: number; placa: string } | null;
    conductor: string | null;
    tipo_carga_label: string;
    fase_label: string | null;
    origen: string;
    destino: string;
    fecha_salida: string;
    /** Null mientras el viaje sigue en curso. */
    fecha_llegada: string | null;
    en_curso: boolean;
    dias: number;
    guias: GuiaRemision[];
    observaciones: string | null;
};

/** El viaje tal como lo carga el formulario de edición. */
export type ViajeEditable = {
    id: number;
    tracto_id: number;
    carreta_id: number | null;
    conductor_id: number | null;
    tipo_carga: string;
    origen_id: number;
    destino_id: number;
    fecha_salida: string;
    fecha_llegada: string | null;
    numero_guia_remitente: string | null;
    numero_guia_transportista: string | null;
    observaciones: string | null;
    tracto_placa: string;
};

export type ViajeOpciones = {
    tractos: EnumOption[];
    carretas: EnumOption[];
    conductores: EnumOption[];
    ubicaciones: EnumOption[];
    cargas: EnumOption[];
};

/** Una línea de la tabla que se envía a mina. */
export type FilaProgramacion = {
    tracto_id: number;
    /** Null en las cargadas que ya van subiendo: no compiten por cupo. */
    numero: number | null;
    fecha: string;
    hora: string | null;
    empresa: string;
    vehiculo: string;
    conductor: string | null;
    tipo_carga: string | null;
    estado_unidad: string | null;
    observaciones: string | null;
    /** Solo en las no programables. */
    motivo: string | null;
};

export type ResultadoProgramacion = {
    fecha: string;
    cupos: number;
    cupos_libres: number;
    en_transito: FilaProgramacion[];
    titulares: FilaProgramacion[];
    reservas: FilaProgramacion[];
    no_programables: FilaProgramacion[];
    /** En tránsito primero y después los titulares, en su orden definitivo. */
    tabla: FilaProgramacion[];
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

export type ImportacionListItem = {
    id: number;
    fecha: string;
    archivo_original: string;
    usuario: string;
    confirmada: boolean;
    filas_totales: number;
    filas_resueltas: number;
    creada: string;
};

/** Una fila del Excel ya resuelta contra el catálogo, en previsualización. */
export type ImportacionFilaItem = {
    id: number;
    numero_fila: number;
    crudo: Record<string, string>;
    tracto: string | null;
    carreta: string | null;
    conductor: string | null;
    tipo_carga_label: string | null;
    origen: string | null;
    destino: string | null;
    ubicacion: string | null;
    observaciones: string | null;
    problemas: string[];
    incluir: boolean;
    puede_aplicarse: boolean;
};

export type ImportacionDetalle = {
    id: number;
    fecha: string;
    archivo_original: string;
    confirmada: boolean;
    filas_totales: number;
    filas_resueltas: number;
};
