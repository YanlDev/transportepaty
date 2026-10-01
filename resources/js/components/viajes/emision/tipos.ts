/** Tipos y catálogos de la pantalla «Emitir GR» (`pages/viajes/emitir.tsx`). */

export type Placa = { id: number; placa: string };

export type ConductorOpcion = {
    id: number;
    nombre: string;
    documento: string;
    licencia: string | null;
};

export type ClienteOpcion = { ruc: string; alias: string };

export type PropsEmitirGr = {
    tractos: Placa[];
    carretas: Placa[];
    conductores: ConductorOpcion[];
    clientes: ClienteOpcion[];
    sunatConfigurado: boolean;
    puedeEmitir: boolean;
    rucPaty: string;
    ultimos: Ultimos;
    remitentes: Remitente[];
};

/** La última combinación con que salió cada tracto y cada conductor. */
export type Ultimos = {
    porTracto: Record<
        number,
        { carreta: number | null; conductor: number | null }
    >;
    porConductor: Record<number, { tracto: number; carreta: number | null }>;
};

export type CampoUnidad = 'tracto' | 'carreta' | 'conductor';

export type ResultadoEmision =
    | {
          estado: 'emitida';
          numeroGr: string;
          viajeRegistrado: boolean;
          /** Enlace al PDF de SUNAT; null si todavía no se pudo bajar. */
          pdfUrl: string | null;
      }
    | { estado: 'en_duda' | 'rechazada' | 'no_enviada'; mensaje: string };

export type GuiaRemitente = {
    ruc: string;
    serie: string;
    numero: number;
    completa: boolean;
    remitente: string | null;
    destinatario: string | null;
    destinatarioRuc: string | null;
    partida: string | null;
    llegada: string | null;
    peso: number | null;
    unidadPeso: string | null;
    bultos: number | null;
    motivo: string | null;
    fechaTraslado: string | null;
    transportistaRuc: string | null;
    avisos: string[];
};

export type Mtc = {
    placa: string;
    numero: string;
    origen: 'transpaty' | 'mtc' | 'ruc';
    vence: string | null;
    placaEnSunat: boolean;
};

export type VerificacionConductor = {
    dni: { encontrado: boolean; nombre: string | null; coincide: boolean };
    licencia: { encontrada: boolean; mensaje: string | null };
};

export type Consulta<T> =
    | { estado: 'cargando' }
    | { estado: 'ok'; datos: T }
    | { estado: 'error'; mensaje: string };

/** `codigo` es el de SUNAT (parámetro 1024 del formulario de SOL). */
export const PAGADORES = [
    { value: 'remitente', label: 'El remitente', codigo: '01' },
    { value: 'subcontratador', label: 'Un subcontratador', codigo: '02' },
    { value: 'tercero', label: 'Un tercero', codigo: '03' },
] as const;

/**
 * Quien emite GR-remitente para Paty, sacado de las GR del último año, con
 * quién contrata a Paty en esos viajes cuando no es el mismo (Crisar).
 */
export type Remitente = {
    ruc: string;
    nombre: string | null;
    viajes: number;
    contratante: { ruc: string; nombre: string } | null;
};
