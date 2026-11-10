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
    /** El día que llegó a la base; null si sigue en ruta. */
    llegada_el: string | null;
    km_llegada: number | null;
    /** El mayor km anotado de la unidad y el día en que se leyó. */
    ultimo_km: { km: number; fecha: string } | null;
    inicio: string | null;
    tracto: { id: number; placa: string };
    conductor: string | null;
    recorrido: { sentido: 'ida' | 'regreso'; texto: string; carga: string }[];
    /** Las rutas del ticket, de los dos tramos. */
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
    /** El costo del combustible: el voucher si llegó, si no lo autorizado por su precio. */
    importe_estimado: number;
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
    /** Se llenó el tanque (Kio): los galones los dice el voucher. */
    tanque_lleno: boolean;
    /** La foto del voucher del grifo. */
    foto_voucher: string | null;
    /** Con qué precio se autorizó (null en los registros viejos). */
    precio_autorizado: number | null;
    /** El voucher si ya llegó; si no, lo autorizado por su precio. */
    costo: number | null;
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

/** Una ruta de un tramo del ticket, con su variante si la necesita. */
export type RutaDelTramo = {
    id: number;
    nombre: string;
    variante: string | null;
    color: string | null;
    es_complemento: boolean;
    variante_pendiente: boolean;
    tope: number | null;
    variantes: { id: number; nombre: string }[];
    /** La variante que corresponde al peso de las GR, si ya hay. */
    sugerida: number | null;
};

/**
 * El combustible de un tramo: el tope de sus rutas, lo autorizado y lo
 * despachado. Sin tope (falta la variante o hay galones por definir), dice
 * por qué en `pendiente`.
 */
export type TramoCombustible = {
    sentido: 'ida' | 'regreso';
    /** Tiene tope: se le puede autorizar combustible. */
    confirmada: boolean;
    rutas: RutaDelTramo[];
    /** La ruta principal del tramo. */
    tarjeta: string | null;
    color: string | null;
    previsto: number | null;
    registrado: number;
    despachado: number;
    falta: number | null;
    puntos: { punto: string; previsto: number | null }[];
    /** El tanque full de sus rutas (Kio): va aparte del tope. */
    tanque_lleno: {
        puntos: { punto_id: number | null; punto: string }[];
        usado: boolean;
        /** Hay un full sin voucher: no se autoriza nada más al tramo hasta que llegue. */
        sin_voucher: boolean;
        /** Lo que entró según los vouchers: descuenta del tope del tramo. */
        galones: number;
    };
    pendiente: string | null;
};

export type RegistroAbastecimiento = {
    id: number;
    fecha: string;
    placa: string;
    conductor: string | null;
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

/** Una fila de la dotación de una ruta del catálogo. */
export type LineaRuta = {
    /** null mientras no se guarda. */
    id: number | null;
    /** La variante por posición en la lista de variantes; null: toda la ruta. */
    variante: number | null;
    tipo: 'combustible' | 'viatico' | 'peaje';
    concepto: string;
    punto_abastecimiento_id: number | null;
    punto?: string | null;
    /** null: galones por definir (o tanque full). */
    galones: number | null;
    /** Se llena el tanque (Kio): sin galones, va aparte del tope. */
    tanque_lleno: boolean;
    monto: number | null;
};

export type VarianteRutaCatalogo = {
    id: number | null;
    nombre: string;
    peso_desde: number | null;
    peso_hasta: number | null;
};

export type ParadaRutaCatalogo = {
    zona_origen: string;
    zona_destino: string | null;
    tipo_carga: string;
    lugar_origen?: string | null;
    lugar_destino?: string | null;
};

/** Una ruta del catálogo: recorrido, dotación y variantes por peso. */
export type RutaCatalogo = {
    id: number;
    nombre: string;
    descripcion: string | null;
    sentido: 'ida' | 'regreso' | null;
    es_complemento: boolean;
    paradas: ParadaRutaCatalogo[];
    pide_destino: boolean;
    dias_viaje: number | null;
    km_estandar: number | null;
    color: string | null;
    nota: string | null;
    activa: boolean;
    variantes: VarianteRutaCatalogo[];
    lineas: LineaRuta[];
    /** Sin variantes: el tope de combustible (null con galones por definir). */
    tope: number | null;
    /** Ya está en algún ticket: no se borra, se desactiva. */
    usada: boolean;
};

export type OpcionesRutas = {
    zonas: Opcion[];
    cargas: Opcion[];
    tiposLinea: Opcion[];
    puntos: Opcion[];
};

/** Un viático del ticket: el de su ruta o un imprevisto. */
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
