import { StatusBadge } from '@/components/ui/status-badge';
import type { StatusTone } from '@/components/ui/status-badge';
import { cn } from '@/lib/utils';

/**
 * Semáforo: rojo es lo que hay que hacer (facturar), ámbar lo que se espera
 * (el neto), celeste lo que casi se cerró (solo falta la detracción) y verde
 * lo cerrado. Lo que se decidió no cobrar queda en gris, fuera del semáforo.
 */
const TONOS: Record<string, StatusTone> = {
    sin_facturar: 'danger',
    facturado: 'warning',
    falta_detraccion: 'info',
    pagado: 'success',
    no_facturable: 'neutral',
};

/** Los estados en que todavía falta cobrar algo: los únicos que pueden vencer. */
const POR_COBRAR = ['facturado', 'falta_detraccion'];

/** Las siglas de la tabla, donde el estado completo se comía la columna. */
const SIGLAS: Record<string, string> = {
    sin_facturar: 'S/F',
    facturado: 'P/C',
    falta_detraccion: 'DET',
    pagado: 'PAG',
    no_facturable: 'N/F',
};

type Props = {
    estado: string;
    label: string;
    /**
     * Días pasados del vencimiento (30 días después de emitida); negativo
     * mientras falte. Null si ya se pagó o no hay factura.
     */
    diasVencida?: number | null;
    /**
     * Sigla en vez del nombre, para la tabla. El nombre completo queda en el
     * tooltip y en el texto accesible.
     */
    compacto?: boolean;
};

/**
 * El estado del cobro de un viaje. Una factura por cobrar que ya venció se
 * muestra en rojo y con los días de atraso: es la fila sobre la que hay que
 * hacer algo hoy, y en ámbar se perdía entre las demás.
 */
export function EstadoCobranzaBadge({
    estado,
    label,
    diasVencida,
    compacto = false,
}: Props) {
    const vencida =
        POR_COBRAR.includes(estado) &&
        diasVencida !== null &&
        diasVencida !== undefined &&
        diasVencida > 0;

    const completo = vencida ? `Vencida hace ${diasVencida} días` : label;
    const corto = vencida ? `${diasVencida}d` : (SIGLAS[estado] ?? label);

    return (
        <span title={completo} aria-label={completo} className="inline-flex">
            <StatusBadge
                label={
                    compacto
                        ? corto
                        : vencida
                          ? `Vencida ${diasVencida}d`
                          : label
                }
                tone={vencida ? 'danger' : (TONOS[estado] ?? 'neutral')}
                className={cn(compacto && 'gap-1 px-1.5 tabular-nums')}
            />
        </span>
    );
}
