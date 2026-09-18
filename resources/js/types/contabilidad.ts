import type { ViajeListItem } from '@/types/fleet';

/**
 * La cobranza. Una factura puede cubrir varios viajes (dos GR de una misma
 * salida, o la factura quincenal de un cliente), así que la misma factura
 * aparece repetida en varias filas de la tabla — `viajes_count` es lo que
 * permite decirlo en pantalla y no leer el monto como si fuera de esa sola fila.
 */
export type FacturaResumen = {
    id: number;
    numero: string;
    fecha_emision: string;
    /** Null mientras no se cargue: la factura puede registrarse solo con su número. */
    monto: number | null;
    moneda: string;
    /** `S/` o `$`, ya resuelto en el backend. */
    simbolo: string;
    /** Null mientras esté por cobrar. */
    fecha_pago: string | null;
    /** Días desde la emisión sin cobrar. Null si ya se pagó. */
    dias_vencida: number | null;
    cuenta_bancaria_id: number | null;
    /** Alias de la cuenta por la que entró la plata. Null si aún no se cobró. */
    cuenta: string | null;
    observacion: string | null;
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
    /** `sin_facturar` | `facturado` | `pagado`. */
    estado: string;
    estado_label: string;
    /** Null cuando el viaje todavía no se facturó. */
    factura: FacturaResumen | null;
    /** Cuándo llegó el papel de la GR a la oficina; null si todavía no. */
    gr_fisica_recibida_at: string | null;
};

/**
 * Un viaje marcado para facturar. Lleva el N° de GR además del id porque la
 * selección cruza páginas: los que quedaron en otra página no están en la
 * tabla y la barra los tiene que poder nombrar igual.
 */
export type ViajeSeleccionado = Pick<ViajeContable, 'id' | 'numero_gr'>;

/** Totales por moneda: sumar soles con dólares daría un número sin sentido. */
export type ResumenMoneda = {
    moneda: string;
    simbolo: string;
    por_cobrar: number;
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
