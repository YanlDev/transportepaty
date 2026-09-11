import { clienteColor } from '@/lib/cliente-color';
import { cn } from '@/lib/utils';

/**
 * Cuántos viajes, y cuánto es eso comparado con el cliente que más mueve. El
 * número solo no dice nada cuando uno concentra el 60% del movimiento; la
 * barra sí.
 */
export function BarraViajes({
    viajes,
    maximo,
    cliente,
}: {
    viajes: number;
    maximo: number;
    cliente: string;
}) {
    const porcentaje = maximo > 0 ? Math.max(2, (viajes / maximo) * 100) : 0;

    return (
        <div className="flex items-center gap-2.5">
            <span className="w-8 shrink-0 text-right font-semibold tabular-nums">
                {viajes}
            </span>
            <span className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                <span
                    className={cn(
                        'block h-full rounded-full',
                        clienteColor(cliente).punto,
                    )}
                    style={{ width: `${porcentaje}%` }}
                />
            </span>
        </div>
    );
}
