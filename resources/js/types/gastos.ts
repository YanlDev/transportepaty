import type { StatusTone } from '@/components/ui/status-badge';

export type EstadoValeGasto =
    | 'solicitado'
    | 'aprobado'
    | 'rechazado'
    | 'anulado';

export const TONO_VALE_GASTO: Record<EstadoValeGasto, StatusTone> = {
    solicitado: 'warning',
    aprobado: 'success',
    rechazado: 'danger',
    anulado: 'neutral',
};

/** Lo que se ve de un vale en el listado. */
export type ValeGastoCabecera = {
    id: number;
    numero: string;
    estado: EstadoValeGasto;
    estado_label: string;
    area: string;
    categoria: string;
    concepto: string;
    monto: number;
    beneficiario: string;
    placa: string | null;
    solicitado_por: string | null;
    solicitado: string | null;
};

export type ValeGastoDetalle = ValeGastoCabecera & {
    motivo: string;
    aprobado_por: string | null;
    aprobado_at: string | null;
    rechazado_por: string | null;
    rechazado_at: string | null;
    motivo_rechazo: string | null;
    anulado_por: string | null;
    anulado_at: string | null;
    motivo_anulacion: string | null;
    sustento: { id: number; nombre: string; url: string }[];
};

export type CategoriaGasto = { id: number; nombre: string };

export type CategoriaGastoAdministrada = CategoriaGasto & {
    activa: boolean;
    /** Cuántos vales la usan. */
    vales: number;
};
