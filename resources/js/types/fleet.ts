export type EnumOption = {
    value: string;
    label: string;
};

export type SucursalOption = {
    id: number;
    nombre: string;
};

export type Sucursal = {
    id: number;
    nombre: string;
    codigo: string;
    direccion: string | null;
    ciudad: string | null;
    telefono: string | null;
    activa: boolean;
};

export type SucursalListItem = Sucursal & {
    vehiculos_count: number;
    conductores_count: number;
};

export type Conductor = {
    id: number;
    sucursal_id: number;
    user_id: number | null;
    nombres: string;
    apellidos: string;
    documento: string;
    licencia: string | null;
    categoria_licencia: string | null;
    licencia_vence: string | null;
    telefono: string | null;
    email: string | null;
    activo: boolean;
    sucursal?: { id: number; nombre: string } | null;
    nombre_completo: string;
};

export type ConductorListItem = {
    id: number;
    nombres: string;
    apellidos: string;
    nombre_completo: string;
    documento: string;
    telefono: string | null;
    email: string | null;
    activo: boolean;
    sucursal_id: number;
    sucursal: { id: number; nombre: string } | null;
    vehiculos_count: number;
};

export type ConductorOption = {
    id: number;
    nombre_completo: string;
    sucursal_id: number;
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

export type VehiculoListItem = {
    id: number;
    placa: string;
    marca: string;
    modelo: string;
    anio: number;
    tipo: string;
    estado: string;
    kilometraje: number;
    sucursal_id: number;
    conductor_id: number | null;
    sucursal: { id: number; nombre: string } | null;
    conductor: { id: number; nombres: string; apellidos: string } | null;
    foto: string | null;
};

export type Vehiculo = {
    id: number;
    sucursal_id: number;
    conductor_id: number | null;
    placa: string;
    marca: string;
    modelo: string;
    anio: number;
    color: string | null;
    numero_serie: string | null;
    numero_motor: string | null;
    imei: string | null;
    gps_km_base: number | null;
    km_calibrado_en: string | null;
    tipo: string;
    combustible: string;
    estado: string;
    kilometraje: number;
    fecha_adquisicion: string | null;
    observaciones: string | null;
    created_at: string;
    updated_at: string;
    sucursal?: { id: number; nombre: string; ciudad?: string | null } | null;
    conductor?: {
        id: number;
        nombres: string;
        apellidos: string;
        telefono?: string | null;
    } | null;
};

export type VehiculoFotoItem = {
    id: number;
    posicion: string;
    posicion_label: string;
    url: string;
    thumb: string;
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
    auto: 'Auto',
    camioneta: 'Camioneta',
    suv: 'SUV',
    camion: 'Camión',
    bus: 'Bus',
    minivan: 'Minivan',
    moto: 'Motocicleta',
    maquinaria: 'Maquinaria',
};

export const combustibleLabels: Record<string, string> = {
    gasolina: 'Gasolina',
    diesel: 'Diésel',
    glp: 'GLP',
    gnv: 'GNV',
    electrico: 'Eléctrico',
    hibrido: 'Híbrido',
};

/** One item within a maintenance record. */
export type MantenimientoItem = {
    id: number;
    plantilla_id: number | null;
    nombre: string;
    tipo_mantenimiento: string;
    costo: number | null;
};

/** A maintenance template the user can pick when registering a service item. */
export type PlantillaOption = {
    id: number;
    nombre: string;
    tipo_mantenimiento: string;
};

/** A maintenance template as managed in the admin maintainer screen. */
export type PlantillaMantenimiento = {
    id: number;
    nombre: string;
    tipo_mantenimiento: string;
    marca: string | null;
    modelo: string | null;
    tipo_vehiculo: string | null;
    intervalo_km: number | null;
    intervalo_meses: number | null;
    descripcion: string | null;
    costo_estimado: number | null;
    orden: number;
    activo: boolean;
};

/** A completed maintenance record for a vehicle. */
export type Mantenimiento = {
    id: number;
    fecha_realizado: string;
    odometro: number;
    proveedor: string | null;
    factura_numero: string | null;
    costo_total: number | null;
    observaciones: string | null;
    registrado_por: string | null;
    comprobante_url: string | null;
    fotos: { url: string; thumb: string }[];
    items: MantenimientoItem[];
};

/** An upcoming / planned maintenance from the schedule. */
export type PlanMantenimiento = {
    plantilla_id: number;
    nombre: string;
    tipo_mantenimiento: string;
    periodicidad_km: number | null;
    periodicidad_dias: number | null;
    proximo_km: number | null;
    ultimo_km: number | null;
    ultimo_realizado: string | null;
    km_restantes: number | null;
    dias_restantes: number | null;
    vencido: boolean;
    progreso: number;
};

/** Yearly cost-of-ownership breakdown for a vehicle (fuel + maintenance). */
export type CostosAnio = {
    anio: number;
    total: number;
    categorias: { clave: string; label: string; monto: number }[];
};

/** Aggregated maintenance stats for a vehicle. */
export type MantenimientoEstadisticas = {
    total_mantenimientos: number;
    total_gastado: number;
    ultimo_costo: number | null;
    costo_promedio: number | null;
    mas_comun: string | null;
};

/** A Tracksolid / JIMI GPS device as shown on the integrations screen. */
export type DispositivoGps = {
    imei: string;
    modelo: string | null;
    placa: string | null;
    vin: string | null;
    marca: string | null;
    modelo_vehiculo: string | null;
    conductor: string | null;
    kilometraje: number | null;
    activo: boolean;
    es_dashcam: boolean;
    vehiculo: {
        id: number;
        placa: string;
        marca: string;
        modelo: string;
    } | null;
    vehiculo_sugerido_id: number | null;
};

export type VehiculoDisponible = {
    id: number;
    placa: string;
    descripcion: string;
};

/** Suggested GPS device for the vehicle form's IMEI field. */
export type DispositivoGpsOption = {
    imei: string;
    label: string;
};

/** One fuel load, enriched with computed efficiency (null when pending or anomalous). */
export type CargaCombustible = {
    id: number;
    fecha_carga: string;
    odometro: number | null;
    galones: number | null;
    costo_total: number | null;
    precio_por_galon: number | null;
    observaciones: string | null;
    procesada: boolean;
    registrada_por: string | null;
    comprobante_url: string | null;
    comprobante_thumb: string | null;
    odometro_foto_url: string | null;
    odometro_foto_thumb: string | null;
    km_recorridos: number | null;
    rendimiento: number | null;
    anomalia: boolean;
    motivo_anomalia: string | null;
};

/** Period summary of a vehicle's fuel consumption. */
export type ResumenCombustible = {
    total_cargas: number;
    total_galones: number;
    total_costo: number;
    km_total: number;
    rendimiento_promedio: number | null;
    rendimiento_ultimo: number | null;
    costo_por_km: number | null;
};

/** One GPS point of a vehicle's route. */
export type PuntoRecorrido = {
    lat: number;
    lng: number;
    hora: string | null;
    velocidad: number;
    rumbo: number;
};

/** A vehicle's route for a day: points + computed stats. */
export type Recorrido = {
    puntos: PuntoRecorrido[];
    stats: {
        distancia_km: number;
        duracion_min: number;
        velocidad_prom: number;
        velocidad_max: number;
        puntos: number;
        con_movimiento: boolean;
    };
};

/** Last known GPS position of a device (Tracksolid location). */
export type Ubicacion = {
    imei: string;
    lat: number | null;
    lng: number | null;
    velocidad: number;
    rumbo: number;
    encendido: boolean;
    estado: string;
    fecha_gps: string | null;
    fecha_reporte: string | null;
};

/** A vehicle plotted on the fleet map (its data joined with its position). */
export type MarcadorVehiculo = Ubicacion & {
    id: number;
    placa: string;
    marca: string;
    modelo: string;
    estado_vehiculo: string;
    sucursal: string | null;
};

/** Visual config per GPS movement state. */
export const estadoGpsConfig: Record<
    string,
    { label: string; color: string; badge: string }
> = {
    en_movimiento: {
        label: 'En movimiento',
        color: '#10b981',
        badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
    },
    detenido: {
        label: 'Detenido',
        color: '#f59e0b',
        badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
    },
    apagado: {
        label: 'Apagado',
        color: '#71717a',
        badge: 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-500/20',
    },
};

export function estadoGpsInfo(estado: string) {
    return estadoGpsConfig[estado] ?? estadoGpsConfig.apagado;
}
