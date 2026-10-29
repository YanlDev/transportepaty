import type { Opcion, RevisionTaller } from '@/types/vueltas';

/** Galones previstos por la pizarra en un punto, contra lo autorizado. */
export type CombustiblePunto = {
    punto_id: number | null;
    punto: string;
    /** null si es «tanque lleno» sin galones configurados. */
    previsto: number | null;
    lleno: boolean;
    registrado: number;
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
    /** Las tarjetas de la pizarra que le tocaron, una por parada. */
    tarjetas: {
        id: number;
        nombre: string;
        color: string | null;
        parada_id: number;
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
    /** Soles del combustible autorizado (solo lo que tiene precio). */
    importe_registrado: number;
    /** Una barra por tramo: la ida y, si tiene combustible, la vuelta. */
    tramos: TramoCombustible[];
};

/** El combustible de un tramo: lo previsto, lo autorizado y sus puntos. */
export type TramoCombustible = {
    sentido: 'ida' | 'regreso';
    /** null si incluye un tanque lleno sin galones configurados. */
    previsto: number | null;
    registrado: number;
    falta: number | null;
    puntos: { punto: string; previsto: number | null; lleno: boolean }[];
};

export type RegistroAbastecimiento = {
    id: number;
    fecha: string;
    placa: string;
    punto: string | null;
    vuelta: string | null;
    galones: number;
    tanque_lleno: boolean;
    precio_galon: number | null;
    importe: number | null;
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
    vuelta_id: number | null;
    vuelta: string | null;
    /** Por qué su ticket no recibe gastos (sin OK de taller, con candado); null si los recibe. */
    bloqueo: string | null;
};

export type PuntoAbastecimiento = {
    id: number;
    nombre: string;
    zona: string | null;
    zona_label: string | null;
    detalle: string | null;
    activo: boolean;
};

export type LineaRegla = {
    tipo: 'combustible' | 'viatico' | 'peaje';
    concepto: string;
    punto_abastecimiento_id: number | null;
    punto?: string | null;
    galones: number | null;
    tanque_lleno: boolean;
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
