import { StatusBadge } from '@/components/ui/status-badge';
import type { StatusTone } from '@/components/ui/status-badge';

/**
 * Días desde la emisión a partir de los cuales una factura por cobrar pasa de
 * ámbar a rojo. 30 es la condición de pago habitual del rubro: pasado eso, ya
 * no es «pendiente», es «vencida».
 */
const DIAS_VENCIDA = 30;

const TONOS: Record<string, StatusTone> = {
    sin_facturar: 'neutral',
    facturado: 'warning',
    pagado: 'success',
};

type Props = {
    estado: string;
    label: string;
    /** Días desde la emisión sin cobrar; null si ya se pagó o no hay factura. */
    diasVencida?: number | null;
};

/**
 * El estado del cobro de un viaje. Una factura por cobrar que ya pasó el mes
 * se muestra en rojo y con los días encima: es la única fila de la tabla sobre
 * la que hay que hacer algo hoy, y en ámbar se perdía entre las demás.
 */
export function EstadoCobranzaBadge({ estado, label, diasVencida }: Props) {
    const vencida =
        estado === 'facturado' &&
        diasVencida !== null &&
        diasVencida !== undefined &&
        diasVencida > DIAS_VENCIDA;

    return (
        <StatusBadge
            label={vencida ? `Vencida ${diasVencida}d` : label}
            tone={vencida ? 'danger' : (TONOS[estado] ?? 'neutral')}
        />
    );
}
