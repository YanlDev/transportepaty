import type { Opcion } from '@/types/vueltas';

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
    viaticos: { concepto: string; tipo: string; monto: number }[];
    total_previsto: number | null;
    total_registrado: number;
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
    observacion: string | null;
    registrado_por: string | null;
};

export type TractoAbastecimiento = {
    id: number;
    placa: string;
    vuelta_id: number | null;
    vuelta: string | null;
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
