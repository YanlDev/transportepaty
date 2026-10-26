import {
    ArrowDownRight,
    ArrowUpRight,
    CalendarClock,
    CheckCheck,
} from 'lucide-react';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EtapaVuelta, TarjetaVuelta } from '@/types/vueltas';

const COLUMNAS: {
    etapa: EtapaVuelta;
    titulo: string;
    ayuda: string;
    acento: string;
}[] = [
    {
        etapa: 'programada',
        titulo: 'Programada',
        ayuda: 'Todavía no sale',
        acento: 'bg-sky-500',
    },
    {
        etapa: 'bajando',
        titulo: 'Bajando',
        ayuda: 'En la ida, hacia la costa',
        acento: 'bg-amber-500',
    },
    {
        etapa: 'subiendo',
        titulo: 'Subiendo',
        ayuda: 'En el regreso, a la sierra',
        acento: 'bg-violet-500',
    },
    {
        etapa: 'en_base',
        titulo: 'En base',
        ayuda: 'Disponible para programar',
        acento: 'bg-emerald-500',
    },
];

/**
 * Una unidad en su columna: placa, ticket, dónde va y cuántos días lleva. El
 * ícono de aviso dice si al conductor ya le llegó el aviso de la vuelta.
 */
function Tarjeta({
    vuelta,
    onAbrir,
}: {
    vuelta: TarjetaVuelta;
    onAbrir: () => void;
}) {
    const avisado = vuelta.aviso.envios.conductor;

    return (
        <button
            type="button"
            onClick={onAbrir}
            className="flex w-full flex-col gap-1.5 rounded-lg border bg-card p-3 text-left shadow-xs transition hover:border-primary/50 hover:shadow-sm"
        >
            <div className="flex items-center justify-between gap-2">
                <span className="font-mono text-sm font-semibold">
                    {formatearPlaca(vuelta.placa)}
                </span>
                <span className="font-mono text-[11px] text-muted-foreground">
                    {vuelta.codigo}
                </span>
            </div>

            {vuelta.actual && (
                <div className="flex items-start gap-1 text-xs">
                    {vuelta.etapa === 'subiendo' ||
                    vuelta.etapa === 'en_base' ? (
                        <ArrowUpRight className="mt-0.5 size-3 shrink-0 text-violet-500" />
                    ) : (
                        <ArrowDownRight className="mt-0.5 size-3 shrink-0 text-amber-500" />
                    )}
                    <span className="min-w-0">
                        <span className="block truncate">
                            {vuelta.actual.texto}
                        </span>
                        <span className="text-muted-foreground">
                            {vuelta.actual.carga}
                            {vuelta.actual.hecha &&
                                vuelta.actual.fecha &&
                                ` · ${formatearFecha(vuelta.actual.fecha)}`}
                        </span>
                    </span>
                </div>
            )}

            <div className="flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
                <span className="truncate">
                    {vuelta.conductor ?? 'Sin conductor'}
                </span>
                <span className="flex shrink-0 items-center gap-1.5">
                    {vuelta.etapa === 'programada' && vuelta.inicio && (
                        <span className="flex items-center gap-0.5">
                            <CalendarClock className="size-3" />
                            {formatearFecha(vuelta.inicio).slice(0, 5)}
                        </span>
                    )}
                    {vuelta.dias_en_ruta !== null && (
                        <span>
                            {vuelta.dias_en_ruta}{' '}
                            {vuelta.dias_en_ruta === 1 ? 'día' : 'días'}
                        </span>
                    )}
                    {vuelta.dias_en_base !== null && (
                        <span
                            className={cn(
                                vuelta.dias_en_base >= 3 &&
                                    'font-medium text-amber-700 dark:text-amber-400',
                            )}
                        >
                            {vuelta.dias_en_base === 0
                                ? 'llegó hoy'
                                : `hace ${vuelta.dias_en_base} ${vuelta.dias_en_base === 1 ? 'día' : 'días'}`}
                        </span>
                    )}
                    {avisado && (
                        <CheckCheck
                            aria-label={`Aviso: ${avisado.estado_label}`}
                            className={cn(
                                'size-3.5',
                                avisado.estado === 'leido'
                                    ? 'text-sky-500'
                                    : avisado.estado === 'fallido'
                                      ? 'text-destructive'
                                      : 'text-muted-foreground',
                            )}
                        />
                    )}
                </span>
            </div>
        </button>
    );
}

/**
 * El kanban de la programación: las vueltas por etapa. Pasan solas de
 * columna a medida que llegan sus GR.
 */
export function KanbanVueltas({
    vueltas,
    onAbrir,
}: {
    vueltas: TarjetaVuelta[];
    onAbrir: (vuelta: TarjetaVuelta) => void;
}) {
    return (
        <div className="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            {COLUMNAS.map((columna) => {
                const deLaColumna = vueltas.filter(
                    (vuelta) => vuelta.etapa === columna.etapa,
                );

                return (
                    <section
                        key={columna.etapa}
                        className="flex min-h-40 flex-col gap-2 rounded-xl bg-muted/40 p-2"
                    >
                        <header className="flex items-center gap-2 px-1 pt-1">
                            <span
                                className={cn(
                                    'size-2 rounded-full',
                                    columna.acento,
                                )}
                            />
                            <h2 className="text-sm font-semibold">
                                {columna.titulo}
                            </h2>
                            <span className="rounded-full bg-background px-1.5 text-xs text-muted-foreground tabular-nums">
                                {deLaColumna.length}
                            </span>
                            <span className="ml-auto text-[11px] text-muted-foreground">
                                {columna.ayuda}
                            </span>
                        </header>

                        <div className="flex flex-col gap-2 overflow-y-auto xl:max-h-[calc(100dvh-16rem)]">
                            {deLaColumna.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                                    Ninguna
                                </p>
                            ) : (
                                deLaColumna.map((vuelta) => (
                                    <Tarjeta
                                        key={vuelta.id}
                                        vuelta={vuelta}
                                        onAbrir={() => onAbrir(vuelta)}
                                    />
                                ))
                            )}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}
