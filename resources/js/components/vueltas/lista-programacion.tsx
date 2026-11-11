import { Placa } from '@/components/placa';
import {
    AvanceTicket,
    BotonSiguiente,
    tareaSiguiente,
} from '@/components/vueltas/siguiente-ticket';
import { etapaDe } from '@/lib/etapas';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TarjetaVuelta, Vuelta } from '@/types/vueltas';

/**
 * Las columnas de la fila en pantalla ancha; por debajo es una tarjeta. Las
 * que llevan `minmax(0, …)` ceden espacio cuando la pantalla es angosta
 * (una laptop con el menú abierto); «Sigue» nunca baja de su mínimo: es lo
 * que hay que leer.
 */
const COLUMNAS =
    'xl:grid xl:grid-cols-[minmax(0,12rem)_7.5rem_minmax(0,13rem)_minmax(0,10rem)_minmax(13rem,1fr)_10rem] xl:items-center xl:gap-4';

/** Los días completos que pasaron desde esa fecha (`AAAA-MM-DD`). */
function diasDesde(fecha: string): number {
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map(Number);
    const hoy = new Date();
    const desde = Date.UTC(anio, mes - 1, dia);
    const hasta = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());

    return Math.round((hasta - desde) / 86_400_000);
}

/** Cuánto lleva: cuándo sale, el día del viaje o los días en la base. */
function tiempo(vuelta: TarjetaVuelta): string | null {
    // La fecha ya la dice «Sigue»: acá va cuánto lleva de atraso.
    if (vuelta.atrasada && vuelta.llegada_prevista) {
        const dias = diasDesde(vuelta.llegada_prevista);

        return dias > 0 ? `${dias} d de atraso` : 'atrasada';
    }

    if (vuelta.etapa === 'programada') {
        return vuelta.inicio ? `sale ${formatearFecha(vuelta.inicio)}` : null;
    }

    if (vuelta.dias_en_base !== null) {
        return `en base ${vuelta.dias_en_base} d`;
    }

    return vuelta.dias_en_ruta !== null ? `día ${vuelta.dias_en_ruta}` : null;
}

/** Los nombres de las rutas del ticket, la ida primero y sin repetir. */
function rutasDe(vuelta: TarjetaVuelta): string[] {
    const ordenadas = [...vuelta.rutas].sort(
        (a, b) =>
            Number(a.sentido === 'regreso') - Number(b.sentido === 'regreso'),
    );

    return [...new Set(ordenadas.map((ruta) => ruta.nombre))];
}

function Etapa({ vuelta }: { vuelta: TarjetaVuelta }) {
    const etapa = etapaDe(vuelta.etapa);

    return (
        <span className="flex items-center gap-1.5 text-xs whitespace-nowrap xl:text-sm">
            <span className={cn('size-2 rounded-full', etapa.punto)} />
            {etapa.titulo}
        </span>
    );
}

function Tiempo({ vuelta }: { vuelta: TarjetaVuelta }) {
    const texto = tiempo(vuelta);

    return texto ? (
        <span
            title={
                vuelta.atrasada && vuelta.llegada_prevista
                    ? `Debía llegar el ${formatearFecha(vuelta.llegada_prevista)}`
                    : undefined
            }
            className={cn(
                // Nunca se sale de su columna: si no entra, se corta.
                'block truncate text-xs',
                vuelta.atrasada
                    ? 'font-medium text-red-700 dark:text-red-400'
                    : 'text-muted-foreground',
            )}
        >
            {texto}
        </span>
    ) : null;
}

function Sigue({ vuelta }: { vuelta: TarjetaVuelta }) {
    const siguiente = vuelta.siguiente;
    const saltados = vuelta.saltados ?? [];

    if (!siguiente) {
        return <p className="text-sm text-muted-foreground">Ticket cerrado.</p>;
    }

    return (
        <div className="min-w-0">
            <p
                className={cn(
                    'text-sm font-medium',
                    siguiente.estado === 'alerta' &&
                        'text-red-700 dark:text-red-400',
                )}
            >
                {tareaSiguiente(siguiente)}
            </p>
            {siguiente.detalle && (
                <p className="text-xs text-muted-foreground">
                    {siguiente.detalle}
                </p>
            )}
            {/* Lo que quedó atrás sin hacer: la unidad siguió igual. */}
            {saltados.length > 0 && (
                <p
                    className="text-xs text-amber-700 dark:text-amber-400"
                    title="Pasos que quedaron atrás sin hacer"
                >
                    Sin: {saltados.join(' · ')}
                </p>
            )}
        </div>
    );
}

function Fila({
    vuelta,
    onAbrir,
    onEditar,
}: {
    vuelta: TarjetaVuelta;
    onAbrir: (vuelta: TarjetaVuelta) => void;
    onEditar: (vuelta: Vuelta) => void;
}) {
    const rutas = rutasDe(vuelta);

    return (
        <li
            className={cn(
                'flex flex-col gap-3 border-b px-4 py-3 last:border-b-0',
                COLUMNAS,
            )}
        >
            <div className="flex min-w-0 items-start justify-between gap-3">
                <button
                    type="button"
                    onClick={() => onAbrir(vuelta)}
                    className="flex min-w-0 flex-col items-start gap-1 text-left"
                >
                    <span className="flex max-w-full items-center gap-2">
                        <Placa placa={vuelta.placa} />
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            {vuelta.codigo}
                        </span>
                    </span>
                    <span className="max-w-full truncate text-xs text-muted-foreground">
                        {vuelta.conductor ?? 'Sin conductor'}
                    </span>
                </button>
                {/* En la tarjeta, la etapa y el tiempo van arriba. */}
                <div className="flex flex-col items-end gap-1 xl:hidden">
                    <Etapa vuelta={vuelta} />
                    <Tiempo vuelta={vuelta} />
                </div>
            </div>
            <div className="flex min-w-0 flex-col gap-0.5 max-xl:hidden">
                <Etapa vuelta={vuelta} />
                <Tiempo vuelta={vuelta} />
            </div>
            <div className="flex min-w-0 flex-wrap gap-1">
                {rutas.length > 0 ? (
                    rutas.map((ruta) => (
                        <span
                            key={ruta}
                            title={ruta}
                            className="max-w-full truncate rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {ruta}
                        </span>
                    ))
                ) : (
                    <span className="text-xs text-muted-foreground italic">
                        Sin ruta
                    </span>
                )}
            </div>
            <AvanceTicket vuelta={vuelta} />
            <div className="flex items-center justify-between gap-3 max-xl:border-t max-xl:pt-3 xl:contents">
                <Sigue vuelta={vuelta} />
                <BotonSiguiente
                    vuelta={vuelta}
                    onAbrir={onAbrir}
                    onEditar={onEditar}
                    className="shrink-0 xl:justify-self-end"
                />
            </div>
        </li>
    );
}

/**
 * La programación como lista: un ticket por fila, el más nuevo arriba, con
 * su avance en el circuito, lo que sigue y el botón para hacerlo. En el
 * celular y en pantallas angostas cada fila es una tarjeta.
 */
export function ListaProgramacion({
    vueltas,
    onAbrir,
    onEditar,
}: {
    vueltas: TarjetaVuelta[];
    onAbrir: (vuelta: TarjetaVuelta) => void;
    onEditar: (vuelta: Vuelta) => void;
}) {
    const ordenadas = [...vueltas].sort((a, b) => b.id - a.id);

    if (ordenadas.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                No hay tickets para mostrar.
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border bg-card">
            <div
                className={cn(
                    'hidden border-b px-4 py-2.5 text-xs text-muted-foreground',
                    COLUMNAS,
                )}
            >
                <span>Unidad</span>
                <span>Etapa</span>
                <span>Ruta</span>
                <span>Avance</span>
                <span>Sigue</span>
                <span />
            </div>
            <ul>
                {ordenadas.map((vuelta) => (
                    <Fila
                        key={vuelta.id}
                        vuelta={vuelta}
                        onAbrir={onAbrir}
                        onEditar={onEditar}
                    />
                ))}
            </ul>
        </div>
    );
}
