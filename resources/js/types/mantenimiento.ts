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
