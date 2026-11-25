/** `falta` | `completo` | `contado` | `sin_contar`. */
export type EstadoEquipamiento =
    | 'falta'
    | 'completo'
    | 'contado'
    | 'sin_contar';

/** Una pieza con código propio: este extintor, este botiquín. */
export type PiezaEquipamiento = {
    id: number;
    codigo: string;
    /** Qué es: capacidad, tipo, marca. */
    descripcion: string | null;
    serie: string | null;
    vence_el: string | null;
    vencido: boolean;
    por_vencer: boolean;
};

/** Un ítem del catálogo frente a lo que la unidad tiene asignado. */
export type ItemCarreta = {
    id: number;
    nombre: string;
    /** Su artículo en el almacén; null si todavía no se le creó. */
    articulo_id: number | null;
    lleva_vencimiento: boolean;
    /** Cada una lleva su código propio. */
    por_pieza: boolean;
    /** Las que la unidad tiene, ya con código. */
    piezas: PiezaEquipamiento[];
    /** Las que tiene y todavía no llevan código. */
    sin_codigo: number;
    /** Null: se cuenta, pero no avisa faltantes. */
    minimo: number | null;
    /** Lo que tiene: saldo inicial + vales de almacén ± traslados − bajas. */
    asignado: number;
    /** Null si la unidad no tiene nada cargado ni contado. */
    buenas: number | null;
    danadas: number | null;
    /** Lo que el último conteo no encontró y nadie explicó. */
    sin_explicar: number;
    faltan: number;
    /** Ya tiene movimientos: el conteo solo comprueba, no agrega. */
    con_historial: boolean;
    vence_el: string | null;
    vencido: boolean;
    por_vencer: boolean;
};

export type EquipamientoCarreta = {
    id: number;
    placa: string;
    marca: string | null;
    tipo: 'tracto' | 'carreta';
    conteo_fecha: string | null;
    items: ItemCarreta[];
    faltan: number;
    /** Ítems con la fecha de vencimiento pasada. */
    vencidos: number;
    estado: EstadoEquipamiento;
    estado_label: string;
};

export type ItemCatalogo = {
    id: number;
    nombre: string;
    minimo: number | null;
    aplica_a: 'carreta' | 'tracto' | 'todas';
    lleva_vencimiento: boolean;
    por_pieza: boolean;
    /** El código de su artículo en el almacén. */
    articulo: string | null;
};

/** Una foto tomada en un levantamiento. */
export type FotoEquipamiento = { id: number; url: string };

export type ConteoEquipamiento = {
    id: number;
    fecha: string;
    observacion: string | null;
    usuario: string | null;
    detalles: {
        item: string;
        buenas: number;
        danadas: number;
        nota: string | null;
        fotos: FotoEquipamiento[];
    }[];
};

/** Un saldo inicial, un traslado o una baja de equipamiento de la unidad. */
export type MovimientoEquipamiento = {
    id: number;
    tipo: 'saldo_inicial' | 'traslado' | 'baja';
    tipo_label: string;
    item: string;
    /** El código de la pieza que se movió, si el ítem va por pieza. */
    codigo: string | null;
    cantidad: number;
    fecha: string;
    /** Suma a lo que tiene la unidad (saldo inicial o traslado que llega). */
    entra: boolean;
    /** En un traslado, la otra unidad. */
    otra_placa: string | null;
    motivo: string | null;
    usuario: string | null;
    anulado: boolean;
    motivo_anulacion: string | null;
};
