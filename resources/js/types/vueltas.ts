import type { ResumenEnvio } from '@/types/programacion';

export type GuiaParada = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
};

export type Sentido = 'ida' | 'regreso';

/** Un trayecto de la ida o del regreso; un vacío no tiene GR. */
export type ParadaVuelta = {
    id: number;
    sentido: Sentido;
    /** El lugar real (distrito de la GR) o, si todavía no hay GR, la zona. */
    origen: string | null;
    destino: string | null;
    zona_origen: string | null;
    zona_destino: string | null;
    tipo_carga: string;
    tipo_carga_label: string;
    cliente_id: number | null;
    cliente: string | null;
    fecha: string | null;
    /** Programada y todavía sin GR. */
    pendiente: boolean;
    /** Llegó una GR que no estaba en lo programado. */
    fuera_de_programa: boolean;
    guias: GuiaParada[];
};

export type EstadoVuelta = 'programada' | 'en_curso' | 'cerrada' | 'anulada';

/** El ticket del viaje: la ida (baja desde la mina o Juliaca) y el regreso (sube) de una unidad. */
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
    tracto_id: number;
    placa: string;
    carreta_id: number | null;
    conductor_id: number | null;
    carreta: string | null;
    conductor: string | null;
    editable: boolean;
    quitable: boolean;
    ida: ParadaVuelta[];
    regreso: ParadaVuelta[];
};

export type Opcion = { value: string; label: string };

export type PlantillaVuelta = Opcion & {
    paradas: {
        sentido: Sentido;
        zona_origen: string;
        zona_destino: string;
        tipo_carga: string;
    }[];
};

/** Lo que necesita el formulario de programar una vuelta. */
export type OpcionesVuelta = {
    plantillas: PlantillaVuelta[];
    zonas: Opcion[];
    cargas: Opcion[];
    tractos: {
        id: number;
        placa: string;
        carreta_id: number | null;
        conductor_id: number | null;
        /** Dónde la dejó su última GR. */
        zona_actual: string | null;
    }[];
    carretas: { id: number; placa: string }[];
    conductores: { id: number; nombre: string }[];
    clientes: { id: number; alias: string; ruc: string | null }[];
};

/** Los avisos de una vuelta ya armados y hasta dónde llegó cada uno. */
export type AvisoVuelta = {
    /** El texto para el conductor; lo arma el servidor para que sea uno solo. */
    mensaje: string;
    destinatarios: { etiqueta: string; numero: string }[];
    areas: { id: number; area: string; numero: string; mensaje: string }[];
    envios: {
        conductor: ResumenEnvio | null;
        /** Por id del área. */
        areas: Record<number, ResumenEnvio>;
    };
};

/** Una vuelta que sale el día elegido, con sus avisos armados. */
export type SalidaDelDia = Vuelta & { aviso: AvisoVuelta };

export type EtapaVuelta = 'programada' | 'bajando' | 'subiendo' | 'cerrada';

/** Una tarjeta del kanban de programación. */
export type TarjetaVuelta = Vuelta & {
    etapa: EtapaVuelta;
    /** La última parada con GR o, si todavía no salió, la primera del plan. */
    actual: {
        texto: string;
        carga: string;
        fecha: string | null;
        hecha: boolean;
    } | null;
    dias_en_ruta: number | null;
    aviso: AvisoVuelta;
};

export type DiaDeSemana = { fecha: string; programadas: number };
