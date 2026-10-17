/** `cambiar` | `revisar` | `bien` | `sin_medir`; null si la posición está vacía. */
export type EstadoLlanta = 'cambiar' | 'revisar' | 'bien' | 'sin_medir';

export type LlantaMontada = {
    id: number;
    marca: string | null;
    medida: string | null;
    codigo: string | null;
    condicion: string;
    condicion_label: string;
    instalada_el: string;
    /** Solo en tractos. */
    km_instalacion: number | null;
    /** La de su última medición. */
    profundidad_mm: number | null;
    medida_el: string | null;
};

/** Una posición del esquema de la unidad. */
export type PosicionLlanta = {
    /** «1» a «10» en el tracto; «1» a «12», «R1» y «R2» en la carreta. */
    codigo: string;
    /** 0 para los repuestos. */
    eje: number;
    nombre: string;
    llanta: LlantaMontada | null;
    estado: EstadoLlanta | null;
    estado_label: string;
};

export type MovimientoLlanta = {
    id: number;
    llanta_id: number;
    tipo: string;
    tipo_label: string;
    fecha: string;
    posicion: string;
    posicion_destino: string | null;
    kilometraje: number | null;
    profundidad_mm: number | null;
    observacion: string | null;
    /** «Michelin 295/80R22.5». */
    llanta: string | null;
    usuario: string | null;
    /** Se puede deshacer sin dejar la unidad en un estado que no existió. */
    eliminable: boolean;
};

/** Una unidad en la lista de llantas. */
export type UnidadLlantas = {
    id: number;
    placa: string;
    marca: string | null;
    tipo: 'tracto' | 'carreta';
    posiciones: number;
    montadas: number;
    cambiar: number;
    revisar: number;
    sin_medir: number;
};

export type UmbralesLlanta = {
    revisar_mm: number;
    cambiar_mm: number;
};
