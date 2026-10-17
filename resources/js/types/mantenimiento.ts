/** `vencido` | `proximo` | `al_dia` | `sin_datos`. */
export type EstadoMantenimiento =
    | 'vencido'
    | 'proximo'
    | 'al_dia'
    | 'sin_datos';

/** Un servicio del plan frente a la unidad: cuándo se hizo y cuándo toca. */
export type ServicioUnidad = {
    id: number;
    codigo: string;
    nombre: string;
    nivel: number;
    intervalo_km: number;
    intervalo_meses: number | null;
    /** Km de la última preventiva de este nivel o mayor; null si nunca. */
    ultimo_km: number | null;
    ultima_fecha: string | null;
    proximo_km: number | null;
    /** Negativo si ya se pasó. Null sin km o sin historial. */
    faltan_km: number | null;
    /** Cuando el servicio vence también por meses. */
    vence_el: string | null;
    estado: EstadoMantenimiento;
    estado_label: string;
};

/** Una fila del semáforo de la flota. */
export type UnidadMantenimiento = {
    id: number;
    placa: string;
    marca: string | null;
    /** El plan de su marca. */
    plan_id: number | null;
    modelo: string | null;
    anio: number | null;
    km_actual: number | null;
    /** Cuándo se anotó el último km. */
    km_fecha: string | null;
    /** El último km es viejo: el semáforo sale de un dato desactualizado. */
    km_desactualizado: boolean;
    estado: EstadoMantenimiento;
    estado_label: string;
    proximo: ServicioUnidad | null;
};

export type ResumenMantenimiento = {
    estado: EstadoMantenimiento;
    label: string;
    cantidad: number;
}[];

/** Un tracto para el selector de «Registrar km». */
export type TractoKm = {
    id: number;
    placa: string;
    marca: string | null;
    km_actual: number | null;
};

export type RepuestoAPreparar = {
    /** El código del paquete al que pertenece (X, S…). */
    servicio: string;
    descripcion: string;
    cantidad: number | null;
    unidad: string | null;
};

export type OrdenResumen = {
    id: number;
    fecha: string;
    tipo: string;
    tipo_label: string;
    /** El código del servicio del plan, en las preventivas. */
    servicio: string | null;
    estado: string;
    estado_label: string;
    kilometraje: number | null;
    trabajo: string;
    tecnico: string | null;
    fotos: number;
};

export type LecturaResumen = {
    id: number;
    kilometraje: number;
    leido_en: string;
    usuario: string | null;
    observacion: string | null;
    foto_url: string | null;
};

/** Un servicio del plan, para elegirlo en una orden preventiva. */
export type ServicioPlan = {
    id: number;
    codigo: string;
    nombre: string;
    intervalo_km: number;
};

export type FotoOrden = {
    id: number;
    coleccion: 'antes' | 'despues';
    url: string;
};

/** Los km (y meses) de un nivel X · S · M · L de una marca. */
export type NivelPlan = {
    codigo: string;
    intervalo_km: number;
    intervalo_meses: number | null;
};

/** Una marca con plan, para las tarjetas del tablero. */
export type MarcaMantenimiento = {
    id: number;
    marca: string;
    nombre: string;
    logo_url: string | null;
    niveles: NivelPlan[];
};

export type RepuestoPlan = {
    descripcion: string;
    codigo: string | null;
    cantidad: number | null;
    unidad: string | null;
};

/** Un nivel del plan tal como se edita. */
export type ServicioEditable = NivelPlan & {
    nombre: string;
    /** Las órdenes ya registradas con este nivel: si hay, no se puede quitar. */
    ordenes: number;
    repuestos: RepuestoPlan[];
};

export type PlanEditable = {
    id: number;
    marca: string;
    nombre: string;
    logo_url: string | null;
    /** Los tractos de la marca que siguen este plan. */
    unidades: number;
    servicios: ServicioEditable[];
};
