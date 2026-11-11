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

/** Las columnas de la fila en la computadora; en el celular es una tarjeta. */
const COLUMNAS =
    'lg:grid lg:grid-cols-[minmax(0,12rem)_5.5rem_6.5rem_minmax(0,13rem)_minmax(0,11rem)_6rem_minmax(0,1fr)_9.5rem] lg:items-center lg:gap-4';

/** Cuánto lleva: cuándo sale, el día del viaje o los días en la base. */
function tiempo(vuelta: TarjetaVuelta): string | null {
    if (vuelta.atrasada && vuelta.llegada_prevista) {
        return `debía llegar ${formatearFecha(vuelta.llegada_prevista)}`;
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
        <span className="flex items-center gap-1.5 text-xs whitespace-nowrap lg:text-sm">
            <span className={cn('size-2 rounded-full', etapa.punto)} />
            {etapa.titulo}
        </span>
    );
}

function Tiempo({ vuelta }: { vuelta: TarjetaVuelta }) {
    const texto = tiempo(vuelta);

    return texto ? (
        <span
            className={cn(
                'text-xs whitespace-nowrap lg:text-sm',
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
            <div className="flex items-start justify-between gap-3">
                <button
                    type="button"
                    onClick={() => onAbrir(vuelta)}
                    className="flex min-w-0 flex-col items-start gap-1 text-left"
                >
                    <Placa placa={vuelta.placa} />
                    <span className="max-w-full truncate text-xs text-muted-foreground">
                        {vuelta.conductor ?? 'Sin conductor'}
                    </span>
                </button>
                {/* En el celular el ticket, la etapa y el tiempo van arriba. */}
                <div className="flex flex-col items-end gap-1 lg:hidden">
                    <span className="font-mono text-xs text-muted-foreground">
                        {vuelta.codigo}
                    </span>
                    <Etapa vuelta={vuelta} />
                    <Tiempo vuelta={vuelta} />
                </div>
            </div>
            <span className="font-mono text-xs text-muted-foreground max-lg:hidden">
                {vuelta.codigo}
            </span>
            <span className="max-lg:hidden">
                <Etapa vuelta={vuelta} />
            </span>
            <div className="flex flex-wrap gap-1">
                {rutas.length > 0 ? (
                    rutas.map((ruta) => (
                        <span
                            key={ruta}
                            className="rounded bg-muted px-2 py-0.5 text-xs whitespace-nowrap text-muted-foreground"
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
            <span className="max-lg:hidden">
                <Tiempo vuelta={vuelta} />
            </span>
            <div className="flex items-center justify-between gap-3 max-lg:border-t max-lg:pt-3 lg:contents">
                <Sigue vuelta={vuelta} />
                <BotonSiguiente
                    vuelta={vuelta}
                    onAbrir={onAbrir}
                    onEditar={onEditar}
                    className="shrink-0 lg:justify-self-end"
                />
            </div>
        </li>
    );
}

/**
 * La programación como lista: un ticket por fila, el más nuevo arriba, con
 * su avance en el circuito, lo que sigue y el botón para hacerlo. En el
 * celular cada fila es una tarjeta.
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
                <span>Ticket</span>
                <span>Etapa</span>
                <span>Ruta</span>
                <span>Avance</span>
                <span>Tiempo</span>
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
