/**
 * Los datos del almacén tal como los manda el backend. Las cifras de plata
 * llegan en null para quien no tiene `almacen.ver_costos`.
 */

export type Opcion = { id: number; nombre: string };

export type ArticuloStock = {
    id: number;
    codigo: string;
    nombre: string;
    numero_parte: string | null;
    marca: string | null;
    categoria: string | null;
    unidad: string;
    ubicacion: string | null;
    stock: number;
    stock_minimo: number | null;
    bajo_minimo: boolean;
    activo: boolean;
    costo_promedio: number | null;
    valor: number | null;
};

/** Un artículo para elegir en un ingreso, un vale o un conteo. */
export type ArticuloElegible = {
    id: number;
    codigo: string;
    nombre: string;
    numero_parte: string | null;
    marca: string | null;
    unidad: string;
    unidad_compra: string | null;
    factor_compra: number;
    ubicacion_id: number | null;
    /** Ya tiene movimientos: un conteo ya no es su saldo inicial. */
    con_historial: boolean;
    stock: number;
    costo_promedio: number | null;
};

export type ArticuloFicha = {
    id: number;
    codigo: string;
    nombre: string;
    numero_parte: string | null;
    marca: string | null;
    categoria: string | null;
    unidad: string;
    unidad_label: string;
    unidad_compra: string | null;
    factor_compra: number;
    ubicacion: string | null;
    marcas_compatibles: string[];
    observacion: string | null;
    activo: boolean;
    stock: number;
    stock_minimo: number | null;
    bajo_minimo: boolean;
    costo_promedio: number | null;
    valor: number | null;
};

export type ArticuloEditable = {
    id: number;
    codigo: string;
    nombre: string;
    numero_parte: string | null;
    marca: string | null;
    categoria_articulo_id: number | null;
    unidad: string;
    unidad_compra: string | null;
    factor_compra: number;
    stock_minimo: number | null;
    ubicacion_almacen_id: number | null;
    marcas_compatibles: string[];
    observacion: string | null;
    activo: boolean;
    tiene_movimientos: boolean;
};

export type TipoMovimiento =
    | 'saldo_inicial'
    | 'compra'
    | 'devolucion'
    | 'ajuste_entrada'
    | 'salida'
    | 'ajuste_salida';

export type MovimientoKardex = {
    id: number;
    fecha: string;
    tipo: TipoMovimiento;
    tipo_label: string;
    documento: string;
    enlace: { tipo: 'ingreso' | 'salida' | 'inventario'; id: number } | null;
    detalle: string | null;
    entrada: number;
    salida: number;
    costo_unitario: number | null;
    costo_total: number | null;
    saldo_cantidad: number;
    saldo_costo_unitario: number | null;
    saldo_valor: number | null;
};

export type Proveedor = {
    id: number;
    ruc: string | null;
    razon_social: string;
    nombre_comercial: string | null;
    contacto: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    condicion_pago: string | null;
    notas: string | null;
    activo: boolean;
    compras: number;
    comprado: number | null;
    comprado_usd: number | null;
    ultima_compra: string | null;
};

export type ProveedorElegible = {
    id: number;
    nombre: string;
    ruc: string | null;
};

export type IngresoCabecera = {
    id: number;
    numero: string;
    tipo: 'compra' | 'devolucion';
    tipo_label: string;
    fecha: string;
    proveedor: string | null;
    proveedor_id: number | null;
    comprobante_tipo: string | null;
    serie_numero: string | null;
    documento: string;
    moneda: string;
    simbolo: string;
    total: number | null;
    costo_total: number | null;
    salida: string | null;
    usuario: string | null;
    registrado: string | null;
    anulado: boolean;
    anulado_at: string | null;
    lineas?: number;
};

export type IngresoDetalle = IngresoCabecera & {
    tipo_cambio: number | null;
    precios_con_igv: boolean;
    fecha_emision: string | null;
    descuento: number | null;
    flete: number | null;
    subtotal: number | null;
    igv: number | null;
    observacion: string | null;
    anulado_por: string | null;
    motivo_anulacion: string | null;
    comprobante: {
        nombre: string;
        url: string;
        es_pdf: boolean;
        peso_kb: number;
    } | null;
    proveedor_detalle: { ruc: string | null; razon_social: string } | null;
    salida_id: number | null;
    detalles: {
        id: number;
        articulo_id: number;
        codigo: string;
        nombre: string;
        unidad: string;
        unidad_compra: string | null;
        bonificacion: boolean;
        vence_el: string | null;
        cantidad: number;
        cantidad_base: number;
        precio_unitario: number | null;
        costo_unitario: number | null;
        costo_total: number | null;
    }[];
};

export type SalidaCabecera = {
    id: number;
    numero: string;
    fecha: string;
    destino: 'orden_trabajo' | 'unidad' | 'consumo';
    destino_label: string;
    placa: string | null;
    vehiculo_id: number | null;
    orden_trabajo_id: number | null;
    entregado_a: string | null;
    costo_total: number | null;
    usuario: string | null;
    registrado: string | null;
    anulado: boolean;
    anulado_at: string | null;
    lineas?: number;
};

export type SalidaDetalle = SalidaCabecera & {
    observacion: string | null;
    anulado_por: string | null;
    motivo_anulacion: string | null;
    orden: { id: number; trabajo: string; estado: string } | null;
    detalles: {
        id: number;
        articulo_id: number;
        codigo: string;
        nombre: string;
        unidad: string;
        cantidad: number;
        devuelto: number;
        costo_unitario: number | null;
        costo_total: number | null;
    }[];
    devoluciones: {
        id: number;
        numero: string;
        fecha: string;
        costo_total: number | null;
        anulado: boolean;
    }[];
};

export type VehiculoElegible = {
    id: number;
    placa: string;
    tipo: string;
    marca: string | null;
};

export type OrdenElegible = {
    id: number;
    etiqueta: string;
    vehiculo_id: number;
    placa: string;
};

export type EstadoInventario = 'abierto' | 'cerrado' | 'anulado';

export type InventarioFila = {
    id: number;
    numero: string;
    fecha: string;
    estado: EstadoInventario;
    estado_label: string;
    observacion: string | null;
    lineas: number;
    usuario: string | null;
    cerrado_por: string | null;
    cerrado_at: string | null;
};

export type InventarioDetalle = Omit<InventarioFila, 'lineas'> & {
    anulado_por: string | null;
    motivo_anulacion: string | null;
    detalles: {
        id: number;
        articulo_id: number;
        codigo: string;
        nombre: string;
        ubicacion: string | null;
        unidad: string;
        cantidad_contada: number;
        stock_sistema: number;
        diferencia: number;
        costo_unitario: number | null;
        costo_estimado: boolean;
        costo_promedio: number | null;
        observacion: string | null;
    }[];
};
