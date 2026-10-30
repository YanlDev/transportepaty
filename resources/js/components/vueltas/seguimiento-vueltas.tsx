import { Link } from '@inertiajs/react';
import { ChevronRight, CircleAlert, CircleDot, Truck } from 'lucide-react';
import { Fragment } from 'react';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import { Placa } from '@/components/placa';
import { IconoTaller } from '@/components/taller/estado-taller';
import { EtapaBadge } from '@/components/vueltas/etapa-badge';
import { ETAPAS } from '@/lib/etapas';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ParadaVuelta, TarjetaVuelta } from '@/types/vueltas';

const DIA_MS = 86_400_000;

/** Un lugar de la ruta y el tramo que sale de él hacia el siguiente. */
type Punto = {
    lugar: string;
    /** El tramo hacia el punto siguiente; null en el último. */
    tramo: {
        sentido: 'ida' | 'regreso';
        carga: string;
        vacio: boolean;
        /** Ya tiene GR: se dibuja lleno; si no, punteado. */
        hecho: boolean;
    } | null;
};

/**
 * Dónde está el camión: en un punto (llegó) o a mitad de un tramo (en
 * camino). La GR dice cuándo salió; llega el día de la GR más lo que tarda
 * la ruta.
 */
type Posicion = { punto: number } | { tramo: number };

function hoy(): number {
    const ahora = new Date();

    return new Date(
        ahora.getFullYear(),
        ahora.getMonth(),
        ahora.getDate(),
    ).getTime();
}

/** La ruta del ticket como lugares en fila, uniendo paradas que se tocan. */
function rutaDe(vuelta: TarjetaVuelta): {
    puntos: Punto[];
    /** Índice del tramo de cada parada, para ubicar el camión. */
    tramoDeParada: Map<number, number>;
} {
    const paradas: ParadaVuelta[] = [...vuelta.ida, ...vuelta.regreso];
    const puntos: Punto[] = [];
    const tramoDeParada = new Map<number, number>();

    paradas.forEach((parada) => {
        const desde = parada.origen ?? '¿?';
        const anterior = puntos.at(-1);

        // Si no arranca donde terminó la anterior, se agrega el lugar.
        if (anterior === undefined || anterior.lugar !== desde) {
            puntos.push({ lugar: desde, tramo: null });
        }

        puntos[puntos.length - 1].tramo = {
            sentido: parada.sentido,
            carga:
                parada.tipo_carga === 'vacio'
                    ? 'vacía'
                    : parada.tipo_carga_label,
            vacio: parada.tipo_carga === 'vacio',
            hecho: parada.guias.length > 0,
        };
        tramoDeParada.set(parada.id, puntos.length - 1);
        puntos.push({ lugar: parada.destino ?? '¿?', tramo: null });
    });

    return { puntos, tramoDeParada };
}

function posicionDe(
    vuelta: TarjetaVuelta,
    tramoDeParada: Map<number, number>,
    totalPuntos: number,
): Posicion {
    if (vuelta.etapa === 'programada' || totalPuntos === 0) {
        return { punto: 0 };
    }

    if (vuelta.etapa === 'en_base') {
        return { punto: totalPuntos - 1 };
    }

    const paradas = [...vuelta.ida, ...vuelta.regreso];
    const ultima = [...paradas]
        .reverse()
        .find((parada) => parada.guias.length > 0);

    if (!ultima?.fecha) {
        return { punto: 0 };
    }

    const tramo = tramoDeParada.get(ultima.id) ?? 0;
    const llegada =
        new Date(`${ultima.fecha}T00:00:00`).getTime() +
        ultima.dias_viaje * DIA_MS;

    return llegada > hoy() ? { tramo } : { punto: tramo + 1 };
}

const COLOR_TRAMO = {
    ida: { linea: 'bg-amber-500', borde: 'border-amber-500' },
    regreso: { linea: 'bg-violet-500', borde: 'border-violet-500' },
} as const;

/**
 * La ruta como una línea de metro: los lugares en fila, los tramos con su
 * color (ida ámbar, vuelta violeta), llenos si ya tienen GR y punteados si
 * todavía no, y el camión donde va.
 */
function Ruta({ vuelta }: { vuelta: TarjetaVuelta }) {
    const { puntos, tramoDeParada } = rutaDe(vuelta);
    const posicion = posicionDe(vuelta, tramoDeParada, puntos.length);
    const porDefinir =
        vuelta.regreso.length === 0 && vuelta.etapa !== 'en_base';

    if (puntos.length === 0) {
        return <p className="text-xs text-muted-foreground">Sin recorrido.</p>;
    }

    return (
        <div className="-mx-1 overflow-x-auto px-1 pb-1">
            <ol className="flex min-w-max items-start">
                {puntos.map((punto, indice) => {
                    const camionAqui =
                        'punto' in posicion && posicion.punto === indice;
                    const camionEnTramo =
                        'tramo' in posicion && posicion.tramo === indice;

                    return (
                        <Fragment key={indice}>
                            <li className="flex w-20 flex-col items-center gap-1 text-center sm:w-24">
                                <span className="flex h-6 items-center">
                                    {camionAqui ? (
                                        <span className="flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground shadow">
                                            <Truck className="size-3.5" />
                                        </span>
                                    ) : (
                                        <span className="size-3 rounded-full border-2 border-foreground/60 bg-background" />
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'line-clamp-2 text-[11px] leading-tight',
                                        camionAqui
                                            ? 'font-semibold'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {punto.lugar}
                                </span>
                            </li>
                            {punto.tramo && (
                                <li className="flex min-w-16 flex-1 flex-col items-center gap-1 pt-[11px] sm:min-w-24">
                                    <span className="relative flex w-full items-center">
                                        <span
                                            className={cn(
                                                'h-0.5 w-full',
                                                punto.tramo.hecho &&
                                                    !punto.tramo.vacio
                                                    ? COLOR_TRAMO[
                                                          punto.tramo.sentido
                                                      ].linea
                                                    : cn(
                                                          'border-t-2 border-dashed',
                                                          COLOR_TRAMO[
                                                              punto.tramo
                                                                  .sentido
                                                          ].borde,
                                                      ),
                                            )}
                                        />
                                        {camionEnTramo && (
                                            <span className="absolute left-1/2 flex size-6 -translate-x-1/2 items-center justify-center rounded-full bg-primary text-primary-foreground shadow">
                                                <Truck className="size-3.5" />
                                            </span>
                                        )}
                                    </span>
                                    <span className="pt-1 text-[10px] text-muted-foreground">
                                        {punto.tramo.carga}
                                    </span>
                                </li>
                            )}
                        </Fragment>
                    );
                })}
                {porDefinir && (
                    <>
                        <li className="flex min-w-16 flex-1 flex-col items-center gap-1 pt-[11px] sm:min-w-24">
                            <span className="w-full border-t-2 border-dashed border-muted-foreground/40" />
                            <span className="pt-1 text-[10px] text-muted-foreground">
                                vuelta por definir
                            </span>
                        </li>
                        <li className="flex w-20 flex-col items-center gap-1 text-center sm:w-24">
                            <span className="flex h-6 items-center">
                                <span className="size-3 rounded-full border-2 border-dashed border-muted-foreground/60" />
                            </span>
                            <span className="text-[11px] text-muted-foreground">
                                ?
                            </span>
                        </li>
                    </>
                )}
            </ol>
        </div>
    );
}

/** Cuánto lleva: el día del viaje, o los días esperando en la base. */
function tiempo(vuelta: TarjetaVuelta): string | null {
    if (vuelta.etapa === 'programada') {
        return vuelta.inicio ? `sale ${formatearFecha(vuelta.inicio)}` : null;
    }

    if (vuelta.dias_en_base !== null) {
        return `en base ${vuelta.dias_en_base} d`;
    }

    return vuelta.dias_en_ruta !== null ? `día ${vuelta.dias_en_ruta}` : null;
}

function Fila({
    vuelta,
    onAbrir,
}: {
    vuelta: TarjetaVuelta;
    onAbrir: (vuelta: TarjetaVuelta) => void;
}) {
    const siguiente = vuelta.siguiente;

    return (
        <article className="flex flex-col gap-3 rounded-xl border bg-card p-4 transition hover:border-foreground/30">
            <button
                type="button"
                onClick={() => onAbrir(vuelta)}
                className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-left"
            >
                <Placa placa={vuelta.placa} />
                <span className="font-mono text-xs text-muted-foreground">
                    {vuelta.codigo}
                </span>
                <EtapaBadge etapa={vuelta.etapa} />
                <IconoTaller revision={vuelta.taller} />
                {tiempo(vuelta) && (
                    <span className="text-xs text-muted-foreground">
                        {tiempo(vuelta)}
                    </span>
                )}
                <span className="ml-auto truncate text-sm text-muted-foreground max-sm:w-full">
                    {vuelta.conductor ?? 'Sin conductor'}
                </span>
            </button>

            <button
                type="button"
                onClick={() => onAbrir(vuelta)}
                className="text-left"
                aria-label={`Ver el recorrido de ${vuelta.placa}`}
            >
                <Ruta vuelta={vuelta} />
            </button>

            <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-2.5 text-xs">
                {siguiente ? (
                    <span
                        className={cn(
                            'flex items-center gap-1.5',
                            siguiente.estado === 'alerta'
                                ? 'text-red-700 dark:text-red-400'
                                : siguiente.estado === 'parcial'
                                  ? 'text-amber-700 dark:text-amber-400'
                                  : 'text-muted-foreground',
                        )}
                    >
                        {siguiente.estado === 'alerta' ? (
                            <CircleAlert className="size-3.5" />
                        ) : (
                            <CircleDot className="size-3.5" />
                        )}
                        <span className="font-medium">
                            Sigue: {siguiente.titulo}
                        </span>
                        {siguiente.detalle && (
                            <span className="opacity-80">
                                · {siguiente.detalle}
                            </span>
                        )}
                    </span>
                ) : (
                    <span className="text-muted-foreground">
                        Ticket cerrado.
                    </span>
                )}
                <Link
                    href={vueltasRutas.show(vuelta.id).url}
                    className="flex items-center gap-0.5 font-medium text-primary hover:underline"
                >
                    Ver ticket
                    <ChevronRight className="size-3.5" />
                </Link>
            </div>
        </article>
    );
}

/**
 * El seguimiento de la programación: una fila por unidad, como rastrear un
 * paquete. Se ve la ruta del ticket con el camión donde va y lo que le
 * falta. Se lee igual en la computadora y en el celular. Ordenado por etapa
 * y, dentro de cada una, por placa.
 */
export function SeguimientoVueltas({
    vueltas,
    onAbrir,
}: {
    vueltas: TarjetaVuelta[];
    onAbrir: (vuelta: TarjetaVuelta) => void;
}) {
    const orden = (vuelta: TarjetaVuelta) =>
        ETAPAS.findIndex((etapa) => etapa.etapa === vuelta.etapa);
    const ordenadas = [...vueltas].sort(
        (a, b) => orden(a) - orden(b) || a.placa.localeCompare(b.placa, 'es'),
    );

    if (ordenadas.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                No hay tickets para mostrar.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            {ordenadas.map((vuelta) => (
                <Fila key={vuelta.id} vuelta={vuelta} onAbrir={onAbrir} />
            ))}
        </div>
    );
}
