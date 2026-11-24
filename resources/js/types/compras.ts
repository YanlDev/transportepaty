import type { StatusTone } from '@/components/ui/status-badge';

/** Cómo está un pedido de compra hoy. */
export type SituacionVale =
    | 'por_aprobar'
    | 'vigente'
    | 'parcial'
    | 'usado'
    | 'vencido'
    | 'rechazado'
    | 'anulado';

export const TONO_SITUACION: Record<SituacionVale, StatusTone> = {
    por_aprobar: 'warning',
    vigente: 'success',
    parcial: 'info',
    usado: 'neutral',
    vencido: 'danger',
    rechazado: 'danger',
    anulado: 'danger',
};

export type ValeCabecera = {
    id: number;
    numero: string;
    tipo: 'normal' | 'emergencia';
    tipo_label: string;
    estado: 'solicitado' | 'aprobado' | 'rechazado';
    situacion: SituacionVale;
    situacion_label: string;
    proveedor: string;
    proveedor_ruc: string | null;
    moneda: string;
    simbolo: string;
    total: number;
    placa: string | null;
    orden_trabajo_id: number | null;
    solicitado_por: string | null;
    solicitado: string | null;
    vence_el: string | null;
    /** «Contado» o «Crédito a 30 días». */
    forma_pago: string;
};

/** Lo que se le pagó al proveedor por un pedido aprobado y lo que falta. */
export type PagoDelPedido = {
    pagado: number;
    por_pagar: number;
    vence_el: string | null;
    vencido: boolean;
    /** A qué cuenta se le paga; null si el proveedor no la tiene anotada. */
    cuenta_proveedor: string | null;
    pagos: {
        id: number;
        fecha: string;
        monto: number;
        medio: string;
        cuenta: string | null;
        numero_operacion: string | null;
        voucher_url: string | null;
        registro: string | null;
        anulado: boolean;
        motivo_anulacion: string | null;
    }[];
};

/** Un pedido aprobado con saldo por pagar. */
export type PedidoPorPagar = {
    id: number;
    numero: string;
    proveedor: string;
    simbolo: string;
    total: number;
    por_pagar: number;
    forma_pago: string;
    vence_el: string | null;
    vencido: boolean;
};

export type LineaVale = {
    id: number;
    articulo_id: number | null;
    codigo: string | null;
    descripcion: string;
    es_servicio: boolean;
    unidad: string;
    cantidad: number;
    precio_unitario: number;
    total: number;
    /** Solo servicios: cuándo se confirmó que el tercero lo hizo. */
    atendido_at: string | null;
};

export type ValeDetalle = ValeCabecera & {
    motivo: string;
    sustento_precio: string | null;
    codigo_verificacion: string;
    url_verificacion: string;
    /** Null mientras no está aprobado. */
    firma_valida: boolean | null;
    aprobado_por: string | null;
    aprobado_at: string | null;
    rechazado_por: string | null;
    motivo_rechazo: string | null;
    anulado_por: string | null;
    motivo_anulacion: string | null;
    recibido: number;
    saldo: number;
    /** Null para quien no puede ver las compras en partes. */
    permite_parciales: boolean | null;
    orden: { id: number; numero: string; trabajo: string } | null;
    detalles: LineaVale[];
    cotizaciones: { id: number; nombre: string; url: string }[];
    ingresos: {
        id: number;
        numero: string;
        fecha: string;
        documento: string;
        total: number;
        anulado: boolean;
    }[];
    consultas: number;
    /** Null mientras el pedido no está aprobado. */
    pago: PagoDelPedido | null;
    /** Emergencia ya instalada: al aprobarse entra y sale a la unidad sola. */
    directo_a_unidad: boolean;
    /** Lo que falta recibir y se puede recibir ya, sin comprobante. */
    por_recibir: LineaPorRecibir[];
};

/** Una línea del pedido que todavía no llegó entera. */
export type LineaPorRecibir = {
    articulo_id: number;
    descripcion: string;
    unidad: string;
    autorizado: number;
    recibido: number;
    pendiente: number;
};

/** Un pedido aprobado con que todavía se puede comprar, para el ingreso. */
export type ValeParaIngreso = {
    id: number;
    numero: string;
    proveedor_id: number;
    proveedor: string;
    moneda: string;
    total: number;
    vence_el: string | null;
    detalles: {
        articulo_id: number;
        cantidad: number;
        precio_unitario: number;
    }[];
};

/** Un servicio de un tercero pedido para una orden de trabajo. */
export type ServicioTercero = {
    id: number;
    orden_trabajo_id: number;
    vale_id: number;
    vale: string;
    descripcion: string;
    proveedor: string;
    total: number | null;
    atendido: boolean;
};

/** Lo que ve quien verifica un pedido sin cuenta en el sistema. */
export type ValePublico = {
    numero: string;
    codigo_verificacion: string;
    tipo_label: string;
    situacion: SituacionVale;
    situacion_label: string;
    /** En qué paso del circuito está el pedido. */
    etapa:
        | 'por_aprobar'
        | 'por_pagar'
        | 'por_recibir'
        | 'recibido_por_pagar'
        | 'completo'
        | 'modificado'
        | 'anulado'
        | 'rechazado';
    valido: boolean;
    aprobado: boolean;
    firma_valida: boolean | null;
    proveedor: string;
    proveedor_ruc: string | null;
    simbolo: string;
    total: number;
    saldo: number;
    recibido: number;
    /** Lo ya pagado al proveedor (pagos sin anular). */
    pagado: number;
    por_pagar: number;
    pagado_completo: boolean;
    ultimo_pago_el: string | null;
    placa: string | null;
    solicitado_por: string | null;
    aprobado_por: string | null;
    aprobado_at: string | null;
    detalles: {
        descripcion: string;
        unidad: string;
        cantidad: number;
        precio_unitario: number;
        total: number;
    }[];
};
