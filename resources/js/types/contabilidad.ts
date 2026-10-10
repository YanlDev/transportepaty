import type { ViajeListItem } from '@/types/fleet';

/**
 * La cobranza. Una factura puede cubrir varios viajes (dos GR de una misma
 * salida, o la factura quincenal de un cliente), así que la misma factura
 * aparece repetida en varias filas de la tabla — `viajes_count` es lo que
 * permite decirlo en pantalla y no leer el monto como si fuera de esa sola fila.
 * A la inversa, un viaje puede tener varias facturas (flete y estadía).
 */
export type FacturaResumen = {
    id: number;
    numero: string;
    fecha_emision: string;
    /**
     * El valor del flete, sin IGV. Null mientras no se cargue: la factura
     * puede registrarse solo con su número.
     */
    monto: number | null;
    /** IGV, total, detracción y neto: los calcula el backend a partir del valor (o del total). */
    igv: number | null;
    total: number | null;
    detraccion: number | null;
    /** Lo que el cliente deposita en la cuenta de la empresa: total menos detracción. */
    neto: number | null;
    moneda: string;
    /** `S/` o `$`, ya resuelto en el backend. */
    simbolo: string;
    /** `facturado` | `falta_detraccion` | `pagado`. */
    estado: string;
    /** Emisión más el plazo de crédito (30 días). */
    fecha_vencimiento: string;
    /** Cuándo entró el neto. Null mientras esté por cobrar. */
    fecha_pago: string | null;
    /**
     * Días pasados del vencimiento: positivo si ya venció, negativo mientras
     * falte. Null si ya se cobró todo.
     */
    dias_vencida: number | null;
    cuenta_bancaria_id: number | null;
    /** Alias de la cuenta por la que entró el neto. Null si aún no se cobró. */
    cuenta: string | null;
    /** Cuándo depositó el cliente la detracción en el Banco de la Nación. */
    fecha_detraccion: string | null;
    constancia_detraccion: string | null;
    observacion: string | null;
    /** El PDF de la factura, si se subió. */
    archivo_url: string | null;
    /** Cuántos viajes cubre esta factura. */
    viajes_count: number;
    /** Todos los viajes que cubre, aunque caigan en otra página del listado. */
    viaje_ids: number[];
};

/**
 * La fila de la cobranza: exactamente las mismas columnas de operación que
 * `/viajes` —se factura contra el viaje entero, no contra un resumen— más el
 * estado del cobro y su factura.
 */
export type ViajeContable = ViajeListItem & {
    /** `sin_facturar` | `facturado` | `falta_detraccion` | `pagado` | `no_facturable`. */
    estado: string;
    estado_label: string;
    /** Por qué se decidió no cobrar esta GR; null si no se dijo o sí se cobra. */
    motivo_no_facturable: string | null;
    /**
     * Vacío cuando el viaje todavía no se facturó; casi siempre una, pero el
     * flete y la estadía pueden ir en facturas distintas. Ordenadas por emisión.
     */
    facturas: FacturaResumen[];
    /** Cuándo llegó el papel de la GR a la oficina; null si todavía no. */
    gr_fisica_recibida_at: string | null;
};

/**
 * Un viaje marcado para facturar. Lleva el N° de GR además del id porque la
 * selección cruza páginas: los que quedaron en otra página no están en la
 * tabla y la barra los tiene que poder nombrar igual. `facturado` permite
 * avisar que la factura nueva se suma a una que ya tiene.
 */
export type ViajeSeleccionado = Pick<ViajeContable, 'id' | 'numero_gr'> & {
    facturado: boolean;
};

/** Totales por moneda: sumar soles con dólares daría un número sin sentido. */
export type ResumenMoneda = {
    moneda: string;
    simbolo: string;
    /** El neto que falta que entre a las cuentas de la empresa. */
    por_cobrar: number;
    /** La detracción que falta que el cliente deposite en el Banco de la Nación. */
    detraccion_por_cobrar: number;
    /** Lo que ya entró, neto y detracción. */
    cobrado: number;
};

/** Lo que falta facturar de un mes, ya con su etiqueta lista para mostrar. */
export type MesPorFacturar = {
    /** `YYYY-MM`, el mismo valor que acepta el filtro de mes. */
    mes: string;
    label: string;
    viajes: number;
};

export type ResumenCobranza = {
    montos: ResumenMoneda[];
    /** Viajes sin factura, desglosados por mes y del más reciente al más antiguo. */
    por_facturar: MesPorFacturar[];
    /** Facturas distintas alcanzadas por los filtros. */
    facturas: number;
    /** Facturas registradas cuyo monto todavía no se cargó. */
    sin_monto: number;
};

export type FiltrosContabilidad = {
    buscar: string | null;
    cliente: string | null;
    estado: string | null;
    /** Un mes completo, en formato `YYYY-MM`. Excluyente con `desde`/`hasta`. */
    mes: string | null;
    desde: string | null;
    hasta: string | null;
};

export type CuentaOpcion = {
    id: number;
    alias: string;
    banco: string;
    numero_cuenta: string;
    moneda: string;
};

export type CuentaBancaria = {
    id: number;
    banco: string;
    alias: string;
    numero_cuenta: string;
    cci: string | null;
    moneda: string;
    moneda_label: string;
    activa: boolean;
    notas: string | null;
    /** Cuántas facturas se cobraron por acá; con una o más, la cuenta no se borra. */
    facturas_count: number;
};

/** Una GR que se puede asociar a mano a una factura de la bandeja. */
export type GrCandidata = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
    destino: string | null;
    /** Cae dentro del período que cobra la factura: viene marcada. */
    sugerida: boolean;
};

/**
 * Una factura que no cubre ninguna GR: se subió en PDF y no se pudo asociar
 * sola, o se quedó sin viajes. Espera en la bandeja «Facturas por asociar».
 */
export type FacturaPorAsociar = {
    id: number;
    numero: string;
    fecha_emision: string;
    cliente: string | null;
    cliente_ruc: string | null;
    neto: number | null;
    simbolo: string;
    archivo_url: string | null;
    /** Las GR que cita el PDF (las que no estaban en el sistema). */
    gr_citadas: string[];
    periodo_desde: string | null;
    periodo_hasta: string | null;
    /** Por qué no se asoció sola, listo para mostrar. */
    motivo: string;
    candidatas: GrCandidata[];
};

/** Lo que pasó con cada PDF de una subida de facturas. */
export type ResultadoImportacionFactura = {
    archivo: string;
    reconocida: boolean;
    numero: string | null;
    /** False si la factura ya existía y se actualizó con el PDF. */
    nueva: boolean;
    asociadas: string[];
    alertas: string[];
};
