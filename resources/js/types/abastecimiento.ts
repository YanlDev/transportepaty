import type { Opcion, RevisionTaller } from '@/types/vueltas';

/** Galones de la tarjeta confirmada en un punto, contra lo autorizado y lo despachado. */
export type CombustiblePunto = {
    punto_id: number | null;
    punto: string;
    /** null si el punto no está en la tarjeta o tiene galones por definir. */
    previsto: number | null;
    registrado: number;
    despachado: number;
    falta: number | null;
};

/** Una vuelta abierta en el tablero, con su dotación. */
export type VueltaAbastecimiento = {
    id: number;
    codigo: string | null;
    estado: 'programada' | 'en_curso';
    estado_label: string;
    etapa: 'programada' | 'en_ida' | 'en_vuelta' | 'en_base';
    /** Sin el apto de taller no se le registra nada. */
    taller: RevisionTaller | null;
    /** Por qué no se le puede registrar combustible ni viáticos; null si se puede. */
    bloqueo_gastos: string | null;
    inicio: string | null;
    tracto: { id: number; placa: string };
    conductor: string | null;
    recorrido: { sentido: 'ida' | 'regreso'; texto: string; carga: string }[];
    /** Las tarjetas confirmadas, una por tramo. */
    tarjetas: {
        sentido: 'ida' | 'regreso';
        id: number | null;
        nombre: string;
        color: string | null;
    }[];
    combustible: CombustiblePunto[];
    /** Cada viático de la pizarra con su depósito (o pendiente), y los imprevistos. */
    viaticos: ViaticoTicket[];
    /** Lo de la pizarra que todavía no se depositó. */
    viatico_pendiente: number;
    viatico_depositado: number;
    viaticos_anulados: ViaticoAnulado[];
    /** Lo que se hizo con los viáticos del ticket, del más nuevo al más viejo. */
    movimientos_viaticos: Movimiento[];
    total_previsto: number | null;
    total_registrado: number;
    total_despachado: number;
    /** Combustible autorizado que todavía no tiene su voucher. */
    despachos_pendientes: number;
    /** Soles del combustible despachado (el voucher trae el precio). */
    importe_registrado: number;
    /** Una barra por tramo: la ida y, si tiene vuelta, la vuelta. */
    tramos: TramoCombustible[];
    /** Cada combustible del ticket, también los anulados. */
    abastecimientos: CombustibleDelTicket[];
};

/** Un combustible autorizado al ticket, con su despacho real y su anulación. */
export type CombustibleDelTicket = {
    id: number;
    fecha: string;
    sentido: 'ida' | 'regreso' | null;
    punto_id: number | null;
    punto: string | null;
    fuera_de_lista: boolean;
    galones: number;
    motivo_exceso: string | null;
    observacion: string | null;
    registrado_por: string | null;
    despacho: {
        galones: number;
        precio_galon: number | null;
        importe: number | null;
        kilometraje: number | null;
        fecha: string | null;
        por: string | null;
    } | null;
    anulado: {
        motivo: string | null;
        por: string | null;
        en: string | null;
    } | null;
};

/**
 * El combustible de un tramo: la tarjeta confirmada (su tope), lo autorizado
 * y lo despachado. Sin confirmar, solo la tarjeta sugerida.
 */
export type TramoCombustible = {
    sentido: 'ida' | 'regreso';
    confirmada: boolean;
    tarjeta: string | null;
    color: string | null;
    confirmada_por: string | null;
    confirmada_at: string | null;
    /** El tope; null sin tarjeta confirmada o con galones por definir. */
    previsto: number | null;
    registrado: number;
    despachado: number;
    falta: number | null;
    puntos: { punto: string; previsto: number | null }[];
    sugerida: { id: number; nombre: string } | null;
};

export type RegistroAbastecimiento = {
    id: number;
    fecha: string;
    placa: string;
    punto: string | null;
    /** Un grifo escrito a mano, que no está en la lista de puntos. */
    fuera_de_lista: boolean;
    vuelta: string | null;
    sentido: 'ida' | 'regreso' | null;
    galones: number;
    galones_despachados: number | null;
    precio_galon: number | null;
    importe: number | null;
    anulado: boolean;
    observacion: string | null;
    registrado_por: string | null;
};

/** Un punto para registrar, con el último precio que se pagó ahí. */
export type PuntoRegistro = {
    id: number;
    nombre: string;
    ultimo_precio: number | null;
};

export type TractoAbastecimiento = {
    id: number;
    placa: string;
    /** Sus tickets abiertos: quien registra elige uno. */
    tickets: {
        id: number;
        codigo: string | null;
        etapa: 'programada' | 'en_ida' | 'en_vuelta' | 'en_base';
        /** Por qué no recibe gastos (sin OK de taller, cerrado); null si los recibe. */
        bloqueo: string | null;
        /** Los tramos con tarjeta confirmada. */
        tramos: ('ida' | 'regreso')[];
    }[];
};

/** Una tarjeta de la pizarra para confirmar en un tramo. */
export type ReglaParaConfirmar = {
    id: number;
    nombre: string;
    color: string | null;
    sentido: 'ida' | 'regreso' | null;
    /** null si tiene galones por definir. */
    tope: number | null;
    resumen: string;
};

export type PuntoAbastecimiento = {
    id: number;
    nombre: string;
    zona: string | null;
    zona_label: string | null;
    detalle: string | null;
    activo: boolean;
    /** Lo que se cargó ahí en el mes en curso. */
    galones_mes?: number;
    /** El último precio por galón que se pagó ahí. */
    ultimo_precio?: number | null;
};

export type LineaRegla = {
    tipo: 'combustible' | 'viatico' | 'peaje';
    concepto: string;
    punto_abastecimiento_id: number | null;
    punto?: string | null;
    galones: number | null;
    monto: number | null;
};

/** Una tarjeta de la pizarra. */
export type ReglaAbastecimiento = {
    id: number;
    nombre: string;
    color: string | null;
    sentido: string | null;
    zona_origen: string | null;
    zona_destino: string | null;
    tipo_carga: string | null;
    lugares: string | null;
    peso_desde: number | null;
    peso_hasta: number | null;
    nota: string | null;
    activa: boolean;
    /** Sin ningún criterio es solo de referencia. */
    se_aplica_sola: boolean;
    lineas: LineaRegla[];
};

export type OpcionesConfiguracion = {
    zonas: Opcion[];
    sentidos: Opcion[];
    cargas: Opcion[];
    tiposLinea: Opcion[];
};

/** Un viático del ticket: el de la pizarra o un imprevisto. */
export type ViaticoTicket = {
    /** null si es un imprevisto. */
    linea_id: number | null;
    concepto: string;
    /** Lo que dice la pizarra; null en un imprevisto. */
    previsto: number | null;
    deposito: {
        id: number;
        monto: number;
        fecha: string;
        numero_operacion: string | null;
        observacion: string | null;
        imprevisto: boolean;
        registrado_por: string | null;
    } | null;
};

export type ViaticoAnulado = {
    id: number;
    concepto: string;
    monto: number;
    fecha: string;
    motivo: string | null;
    anulado_por: string | null;
    anulado_at: string | null;
};

/** Un movimiento del historial: quién, cuándo y qué cambió (campo → [antes, después]). */
export type Movimiento = {
    id: number;
    fecha: string;
    usuario: string | null;
    accion: 'creado' | 'editado' | 'anulado' | 'eliminado';
    etiqueta: string | null;
    cambios: Record<string, [unknown, unknown]>;
};
