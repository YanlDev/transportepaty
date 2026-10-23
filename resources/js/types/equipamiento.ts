/** `falta` | `completo` | `contado` | `sin_contar`. */
export type EstadoEquipamiento =
    | 'falta'
    | 'completo'
    | 'contado'
    | 'sin_contar';

/** Un ítem del catálogo frente a lo que tiene la carreta en su último conteo. */
export type ItemCarreta = {
    id: number;
    nombre: string;
    /** Null: se cuenta, pero no avisa faltantes. */
    minimo: number | null;
    /** Null si la carreta nunca se contó. */
    buenas: number | null;
    danadas: number | null;
    faltan: number;
};

export type EquipamientoCarreta = {
    id: number;
    placa: string;
    marca: string | null;
    conteo_fecha: string | null;
    items: ItemCarreta[];
    faltan: number;
    estado: EstadoEquipamiento;
    estado_label: string;
};

export type ItemCatalogo = {
    id: number;
    nombre: string;
    minimo: number | null;
};

export type ConteoEquipamiento = {
    id: number;
    fecha: string;
    observacion: string | null;
    usuario: string | null;
    detalles: { item: string; buenas: number; danadas: number }[];
};
