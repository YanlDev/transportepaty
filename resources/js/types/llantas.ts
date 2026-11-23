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
    /** La placa de la unidad de la que vino, si se volvió a montar acá. */
    viene_de: string | null;
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

/** Una línea de vale de almacén con llantas todavía por montar en la unidad. */
export type ValeDeLlantas = {
    detalle_salida_id: number;
    vale: string;
    fecha: string;
    articulo_id: number;
    nombre: string;
    marca: string | null;
    disponibles: number;
};

/** Una llanta retirada de una unidad, que se puede volver a montar. */
export type LlantaRetirada = {
    id: number;
    descripcion: string;
    placa: string;
    retirada_el: string;
    motivo: string;
};

/** Una llanta con código, sin montar: en el almacén o ya entregada a la unidad. */
export type LlantaEnAlmacen = {
    id: number;
    codigo: string | null;
    /** Marca y medida. */
    descripcion: string;
    /** La recepción con que entró: su lote. */
    lote: string | null;
    /** La entrega con que ya salió para la unidad, si salió. */
    vale: string | null;
};

/** Dónde está una llanta: `almacen`, `entregada`, `montada`, `retirada` o `baja`. */
export type SituacionLlanta =
    | 'almacen'
    | 'entregada'
    | 'montada'
    | 'retirada'
    | 'baja';

/** Una recepción con llantas: el lote. */
export type LoteLlantas = {
    id: number;
    numero: string;
    fecha: string;
    proveedor: string | null;
    documento: string | null;
    lineas: {
        detalle_ingreso_id: number;
        articulo: string;
        marca: string | null;
        cantidad: number;
        por_codificar: number;
        costo: number;
    }[];
    llantas: {
        id: number;
        codigo: string | null;
        articulo: string | null;
        situacion: SituacionLlanta;
        donde: string;
    }[];
};
