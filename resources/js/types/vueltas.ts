export type GuiaParada = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
};

/** Un trayecto de la ida o del regreso; un vacío no tiene GR. */
export type ParadaVuelta = {
    id: number;
    origen: string | null;
    destino: string | null;
    zona_origen: string | null;
    zona_destino: string | null;
    tipo_carga: string;
    tipo_carga_label: string;
    cliente: string | null;
    fecha: string | null;
    guias: GuiaParada[];
};

export type EstadoVuelta = 'programada' | 'en_curso' | 'cerrada' | 'anulada';

/** El ticket del viaje: la ida (subida) y el regreso (bajada) de una unidad. */
export type Vuelta = {
    id: number;
    codigo: string | null;
    tipo: 'mina' | 'particular';
    tipo_label: string;
    estado: EstadoVuelta;
    estado_label: string;
    origen: 'historial' | 'programacion';
    inicio: string | null;
    fin: string | null;
    dias: number | null;
    carreta: string | null;
    conductor: string | null;
    ida: ParadaVuelta[];
    regreso: ParadaVuelta[];
};

export type UnidadConVuelta = {
    tracto: { id: number; placa: string };
    vueltas: number;
    vuelta: Vuelta;
};
