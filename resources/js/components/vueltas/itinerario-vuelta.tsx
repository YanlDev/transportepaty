import { Lock } from 'lucide-react';
import { Fragment } from 'react';
import { cn } from '@/lib/utils';

/** Una parada como la dibuja el itinerario. */
export type ParadaItinerario = {
    sentido: 'ida' | 'regreso';
    desde: string;
    hasta: string;
    carga: string;
    vacio: boolean;
    /** Ya tiene GR: pasó. */
    hecha?: boolean;
};

const ACENTO = {
    ida: {
        punto: 'bg-amber-500',
        linea: 'bg-amber-500/60',
        texto: 'text-amber-700 dark:text-amber-400',
    },
    regreso: {
        punto: 'bg-violet-500',
        linea: 'bg-violet-500/60',
        texto: 'text-violet-700 dark:text-violet-400',
    },
} as const;

/**
 * La ruta de la vuelta de punta a punta, como el itinerario de un pasaje:
 * los lugares en fila y, entre uno y otro, lo que lleva la unidad. La ida en
 * ámbar, el regreso en violeta; los tramos vacíos con línea punteada.
 */
export function ItinerarioVuelta({ paradas }: { paradas: ParadaItinerario[] }) {
    if (paradas.length === 0) {
        return (
            <p className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                Elige la ida para ver el recorrido.
            </p>
        );
    }

    return (
        <ol className="flex flex-wrap items-start gap-y-4 overflow-x-auto pb-1">
            {paradas.map((parada, indice) => {
                const acento = ACENTO[parada.sentido];
                const primera = indice === 0;
                // Un lugar nuevo solo si no es donde terminó la anterior.
                const salta =
                    !primera && paradas[indice - 1].hasta !== parada.desde;

                return (
                    <Fragment key={indice}>
                        {(primera || salta) && (
                            <li className="flex min-w-0 flex-col items-center gap-1">
                                <span
                                    className={cn(
                                        'size-3 rounded-full ring-4 ring-background',
                                        acento.punto,
                                    )}
                                />
                                <span className="max-w-24 truncate text-xs font-medium">
                                    {parada.desde}
                                </span>
                            </li>
                        )}
                        <li className="flex min-w-16 flex-1 flex-col items-center gap-1 pt-1">
                            <span
                                className={cn(
                                    'h-0.5 w-full',
                                    parada.vacio
                                        ? 'border-t-2 border-dashed border-muted-foreground/40 bg-transparent'
                                        : acento.linea,
                                )}
                            />
                            <span
                                className={cn(
                                    'flex items-center gap-1 text-[11px] whitespace-nowrap',
                                    parada.vacio
                                        ? 'text-muted-foreground italic'
                                        : acento.texto,
                                )}
                            >
                                {parada.hecha && <Lock className="size-3" />}
                                {parada.carga}
                            </span>
                        </li>
                        <li className="flex min-w-0 flex-col items-center gap-1">
                            <span
                                className={cn(
                                    'size-3 rounded-full ring-4 ring-background',
                                    acento.punto,
                                )}
                            />
                            <span className="max-w-24 truncate text-xs font-medium">
                                {parada.hasta}
                            </span>
                        </li>
                    </Fragment>
                );
            })}
        </ol>
    );
}
