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
    telefono_alterno: string | null;
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
    username: string;
    email: string | null;
    email_verified_at: string | null;
    roles: { id: number; name: string }[];
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
 * vence dentro de 15 días. Rojo: falta algo o ya venció.
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
    /** Certificado de habilitación vehicular, obligatorio para emitir la GRE. */
    tuc: string | null;
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
    /** false si renunció —igual aparece en el ciclo donde tiene marcas, pero atenuado. */
    activo: boolean;
    marcas: Record<string, AsistenciaMarca>;
};

/** Un día de la grilla del calendario individual: puede ser relleno del mes vecino. */
export type AsistenciaCalendarioDia = AsistenciaDia & {
    es_relleno: boolean;
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

/** La pestaña de Asistencia en la ficha del conductor: null si el usuario no puede verla (visor). */
export type AsistenciaCalendarioAnual = {
    anio: number;
    calendarios: AsistenciaCalendarioMes[];
};

/** Un cliente del padrón, tal como se lista en `/clientes`. */
export type ClienteListItem = {
    id: number;
    ruc: string;
    razon_social: string;
    /** Nombre corto para tablas y gráficos. */
    alias: string;
    contacto: string | null;
    telefono: string | null;
    recurrente: boolean;
    activo: boolean;
    viajes_count: number;
    /** Fecha del viaje más reciente; null si nunca se le movió nada. */
    ultimo_viaje: string | null;
};

/** El cliente completo, para su ficha y su formulario. */
export type Cliente = {
    id: number;
    ruc: string;
    razon_social: string;
    alias: string;
    /** Con el que se lo conoce en la calle, cuando no es la razón social. */
    nombre_comercial: string | null;
    contacto: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    recurrente: boolean;
    activo: boolean;
    notas: string | null;
};

/** Un viaje en el historial de la ficha del cliente. */
export type ClienteViajeItem = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
    placa_tracto: string;
    placa_carreta: string | null;
    conductor_nombre: string;
    /** Null cuando el DNI de la GR no matcheó contra el padrón. */
    conductor_id: number | null;
    origen_ciudad: string;
    destino_ciudad: string;
    tipo_carga: string;
    tipo_carga_label: string;
    peso: number;
    unidad_peso: string;
    archivo_url: string | null;
};

/**
 * Los números de cabecera de la ficha del conductor. Los días del mes salen
 * de las marcas de asistencia: llegan en `null` para quien no puede verlas
 * (visor), y esas tarjetas no se muestran.
 */
/**
 * Los números de cabecera de la ficha del cliente. `variacion_mes` llega en
 * null cuando no hay mes anterior contra el cual comparar, que es distinto de
 * una variación de 0%.
 */
export type ClienteEstadisticas = {
    viajes_totales: number;
    viajes_mes: number;
    variacion_mes: number | null;
    ultimo_viaje: string | null;
    primer_viaje: string | null;
    tipos_carga: number;
    carga_principal: string | null;
    ruta_frecuente: {
        origen: string;
        destino: string;
        viajes: number;
    } | null;
};

export type ConductorEstadisticas = {
    viajes_totales: number;
    /** Fecha del viaje más reciente, o null si nunca manejó uno. */
    ultimo_viaje: string | null;
    dias_trabajados_mes: number | null;
    dias_descanso_mes: number | null;
    faltas_mes: number | null;
    documentos_vigentes: number;
    documentos_totales: number;
};

/**
 * Un viaje en el historial de la ficha del conductor. Es un subconjunto chico
 * de `ViajeListItem`: acá solo interesa repasar qué manejó —fecha, unidad,
 * cliente, carga y GR—, no auditar el viaje (para eso está `/viajes`).
 */
export type ConductorViajeItem = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
    cliente: string;
    tipo_carga: string;
    tipo_carga_label: string;
    placa_tracto: string;
    placa_carreta: string | null;
    /** Null si por alguna razón el PDF de la GR no quedó adjunto. */
    archivo_url: string | null;
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

/**
 * Un lugar de partida o llegada del catálogo. Existe por la guía de remisión
 * electrónica, que exige el ubigeo INEI de ambos extremos del traslado.
 */
export type PuntoTraslado = {
    id: number;
    /** Nombre corto con el que se lo elige: «Mina San Rafael». */
    nombre: string;
    /** Los 6 dígitos del catálogo del INEI. */
    ubigeo: string;
    direccion: string;
    /** RUC del contribuyente dueño del local, si se declara como anexo. */
    ruc: string | null;
    /** Código de establecimiento anexo, de 4 dígitos. */
    cod_local: string | null;
    activo: boolean;
};

/** El punto en la tabla del catálogo, con cuánto se usa. */
export type PuntoTrasladoListItem = PuntoTraslado & {
    /** Viajes que lo usan como partida o como llegada. */
    usos: number;
};

/**
 * El contexto que comparten todos los costos fijos: cuántas unidades hay y
 * cuántos días al año cada una está realmente disponible para vender.
 */
export type ParametroFlota = {
    tamano_flota: number;
    dias_ano: number;
    dias_mantenimiento: number;
    dias_certificaciones: number;
    dias_sincronizacion: number;
    /** En tanto por uno: 0.18 es 18%. */
    igv_pct: number;
    /** En tanto por uno: 0.12 es 12%. */
    margen_pct_default: number;
    /** Los días del año menos los que se pierden. Lo calcula el servidor. */
    dias_disponibles: number;
};

/** Un paso intermedio de la cuenta que deriva la tasa de un componente. */
export type PasoDerivacion = {
    etiqueta: string;
    valor: number;
    formato: 'moneda' | 'numero' | 'porcentaje' | 'dias' | 'km';
};

/**
 * Lo que puede valer una entrada de un método: un número, un texto, un sí/no,
 * o una lista de filas (las localidades donde se abastece la flota, las vidas
 * de un juego de neumáticos).
 */
export type FilaEntrada = Record<string, string | number | boolean>;

export type ValorEntrada = string | number | boolean | FilaEntrada[];

export type EntradasComponente = Record<string, ValorEntrada>;

/** Una línea del tarifario de la casa. */
export type ComponenteCosto = {
    id: number;
    nombre: string;
    tipo: 'fijo_dia' | 'variable_km';
    /** Cómo se lee la tasa: «S/ por día» o «S/ por km». */
    unidad: string;
    metodo: string;
    metodo_label: string;
    entradas: EntradasComponente;
    activo: boolean;
    /** La tasa con la que se cotiza: se escribe a mano. */
    tasa: number;
    /** Si hay una cuenta detrás (planilla, diésel) que sugiera la tasa. */
    tiene_calculadora: boolean;
    /** Lo que sugiere la calculadora con sus entradas guardadas. */
    tasa_calculada: number;
    pasos: PasoDerivacion[];
};

/** Lo que suma el tarifario vigente, por día tomado y por km rodado. */
export type TotalesCosto = {
    fijo_dia: number;
    variable_km: number;
};

/** Una línea del tarifario tal como entra a la cuenta de una tarifa. */
export type LineaTarifa = {
    nombre: string;
    /** `fijo_dia` se multiplica por días; cualquier otro, por km. */
    tipo: string;
    naturaleza: string;
    tasa: number;
};

/** Una línea del desglose de una cotización, ya con su importe. */
export type LineaDesglose = LineaTarifa & { importe: number };

/** La tarifa de una ruta, con la misma forma que la guarda el servidor. */
export type ResultadoTarifa = {
    desglose: { componentes: LineaDesglose[] };
    margen_pct: number;
    total_fijo: number;
    total_variable: number;
    costo_operativo: number;
    margen: number;
    /** La tarifa sin IGV. */
    subtotal: number;
    igv: number;
    total: number;
};

export type CotizacionListItem = {
    id: number;
    numero: string;
    fecha: string;
    cliente_nombre: string;
    origen: string;
    destino: string;
    km: number;
    dias: number;
    costo_por_km: number;
    total: number;
    estado: string;
    estado_label: string;
};

/** La cotización completa, para su ficha y su formulario. */
export type Cotizacion = ResultadoTarifa & {
    id: number;
    numero: string;
    fecha: string;
    valido_hasta: string;
    cliente_id: number | null;
    cliente_nombre: string;
    cliente_ruc: string | null;
    punto_partida_id: number | null;
    punto_llegada_id: number | null;
    origen: string;
    destino: string;
    material: string | null;
    km: number;
    dias: number;
    margen_pct: number;
    costo_por_km: number;
    estado: string;
    estado_label: string;
    notas: string | null;
};
