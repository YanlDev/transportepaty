import { Link } from '@inertiajs/react';
import { ChevronRight, CircleAlert, CircleDot, Truck } from 'lucide-react';
import { Fragment } from 'react';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import { Placa } from '@/components/placa';
import { IconoTaller } from '@/components/taller/estado-taller';
import { IconoTipoCarga } from '@/components/viajes/tipo-carga-badge';
import { EtapaBadge } from '@/components/vueltas/etapa-badge';
import { claseDe, claseDeCarga, clasesDeTicket } from '@/lib/clase-carga';
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
        /** El primer tramo de su sentido: ahí se rotula «Ida» o «Vuelta». */
        abre: boolean;
        tipo: string;
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

        const sentidoAnterior = puntos
            .map((punto) => punto.tramo?.sentido)
            .filter(Boolean)
            .at(-1);

        puntos[puntos.length - 1].tramo = {
            sentido: parada.sentido,
            abre: sentidoAnterior !== parada.sentido,
            tipo: parada.tipo_carga,
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
    ida: {
        linea: 'bg-amber-500',
        borde: 'border-amber-500',
        rotulo: 'text-amber-700 dark:text-amber-400',
        titulo: 'Ida',
    },
    regreso: {
        linea: 'bg-violet-500',
        borde: 'border-violet-500',
        rotulo: 'text-violet-700 dark:text-violet-400',
        titulo: 'Vuelta',
    },
} as const;

/** La carga del tramo: chip del color de su clase (mina o particular). */
function CargaDelTramo({ tipo, carga }: { tipo: string; carga: string }) {
    const clase = claseDeCarga(tipo);

    if (clase === null) {
        return (
            <span className="text-[10px] text-muted-foreground italic">
                {carga}
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full border px-1.5 py-px text-[10px] font-medium whitespace-nowrap [&_svg]:size-3',
                claseDe(clase).suave,
            )}
        >
            <IconoTipoCarga valor={tipo} />
            {carga}
        </span>
    );
}

/**
 * La ruta como una línea de metro: los lugares en fila, los tramos con su
 * color (ida ámbar, vuelta violeta) y rotulados donde empieza cada uno,
 * llenos si ya tienen GR y punteados si todavía no, la carga con el color
 * de su clase (mina o particular) y el camión donde va.
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
            <ol className="flex min-w-max items-start pt-4">
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
                                <li className="relative flex min-w-16 flex-1 flex-col items-center gap-1 pt-[10px] sm:min-w-24">
                                    {punto.tramo.abre && (
                                        <span
                                            className={cn(
                                                'absolute -top-3.5 left-0 text-[10px] leading-none font-semibold tracking-wide uppercase',
                                                COLOR_TRAMO[punto.tramo.sentido]
                                                    .rotulo,
                                            )}
                                        >
                                            {
                                                COLOR_TRAMO[punto.tramo.sentido]
                                                    .titulo
                                            }
                                        </span>
                                    )}
                                    <span className="relative flex w-full items-center">
                                        <span
                                            className={cn(
                                                'w-full',
                                                punto.tramo.hecho &&
                                                    !punto.tramo.vacio
                                                    ? cn(
                                                          'h-1 rounded-full',
                                                          COLOR_TRAMO[
                                                              punto.tramo
                                                                  .sentido
                                                          ].linea,
                                                      )
                                                    : cn(
                                                          'border-t-[3px] border-dashed',
                                                          COLOR_TRAMO[
                                                              punto.tramo
                                                                  .sentido
                                                          ].borde,
                                                          punto.tramo.vacio &&
                                                              'opacity-50',
                                                      ),
                                            )}
                                        />
                                        {camionEnTramo && (
                                            <span className="absolute left-1/2 flex size-6 -translate-x-1/2 items-center justify-center rounded-full bg-primary text-primary-foreground shadow">
                                                <Truck className="size-3.5" />
                                            </span>
                                        )}
                                    </span>
                                    <span className="pt-1">
                                        <CargaDelTramo
                                            tipo={punto.tramo.tipo}
                                            carga={punto.tramo.carga}
                                        />
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
        <article className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 transition hover:border-foreground/30">
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
                {clasesDeTicket([...vuelta.ida, ...vuelta.regreso]).map(
                    (clase) => (
                        <span
                            key={clase}
                            className={cn(
                                'rounded-full border px-2 py-0.5 text-[11px] font-medium',
                                claseDe(clase).suave,
                            )}
                        >
                            {claseDe(clase).titulo}
                        </span>
                    ),
                )}
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

            {/* `min-w-0`: sin esto la ruta larga empuja la tarjeta fuera de
                la pantalla en vez de deslizarse dentro de ella. */}
            <button
                type="button"
                onClick={() => onAbrir(vuelta)}
                className="w-full min-w-0 text-left"
                aria-label={`Ver el recorrido de ${vuelta.placa}`}
            >
                <Ruta vuelta={vuelta} />
            </button>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-2.5 text-xs [&>a]:ml-auto">
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
                {/* Lo que quedó atrás sin hacer: la unidad siguió igual. */}
                {(vuelta.saltados ?? []).length > 0 && (
                    <span
                        className="flex items-center gap-1 text-amber-700 dark:text-amber-400"
                        title="Pasos que quedaron atrás sin hacer"
                    >
                        <CircleAlert className="size-3.5" />
                        Sin: {(vuelta.saltados ?? []).join(' · ')}
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
 * La fecha con que se ordena: la de la última GR si ya va en ruta; si no,
 * el día en que sale.
 */
const fechaDe = (vuelta: TarjetaVuelta): string =>
    vuelta.actual?.fecha ?? vuelta.inicio ?? '';

/**
 * El seguimiento de la programación: una fila por unidad, como rastrear un
 * paquete. Se ve la ruta del ticket con el camión donde va y lo que le
 * falta. Se lee igual en la computadora y en el celular. Ordenado por etapa
 * y, dentro de cada una, por fecha (lo que sale antes, arriba); a igual
 * fecha, por ticket.
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
        (a, b) =>
            orden(a) - orden(b) ||
            fechaDe(a).localeCompare(fechaDe(b)) ||
            (a.codigo ?? '').localeCompare(b.codigo ?? '', 'es'),
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
