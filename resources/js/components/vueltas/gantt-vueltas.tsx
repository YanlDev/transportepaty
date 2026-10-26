import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { ETAPAS } from '@/components/vueltas/tabla-vueltas';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ParadaVuelta, TarjetaVuelta } from '@/types/vueltas';

const DIA_MS = 86_400_000;

/** Cuántos días se ven: dos semanas atrás y una adelante. */
const DIAS_ATRAS = 14;
const DIAS_ADELANTE = 7;

/**
 * Cuánto se estima que dura una parada que todavía no tiene GR: lo justo
 * para dibujarla, no un pronóstico. Un vacío es más corto que un tramo con
 * carga; la vuelta por definir ocupa lo que suele durar un regreso.
 */
const DIAS_ESTIMADOS = { vacio: 1, carga: 2, vuelta_por_definir: 5 };

const DIAS_CORTOS = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'];

type Segmento = {
    desde: number;
    hasta: number;
    /** El tramo del recorrido (`mina>pisco`), que decide el color. */
    ruta: string;
    /** «Mina → Pisco», para la leyenda. */
    nombreRuta: string;
    etiqueta: string;
    vacio: boolean;
    /** Sin GR todavía: lo programado, dibujado claro y punteado. */
    estimado: boolean;
};

/**
 * Un color por tramo del recorrido, para leer por dónde va cada unidad sin
 * leer los textos: Juliaca → mina celeste, mina → Pisco azul, y así. Lo que
 * no está en la lista va en gris.
 */
const COLORES_RUTA: Record<string, { lleno: string; claro: string }> = {
    'juliaca>mina': {
        lleno: 'bg-sky-400 text-sky-950',
        claro: 'border-sky-400 bg-sky-400/15 text-sky-800 dark:text-sky-300',
    },
    'mina>pisco': {
        lleno: 'bg-blue-600 text-white',
        claro: 'border-blue-600 bg-blue-600/15 text-blue-800 dark:text-blue-300',
    },
    'pisco>lima': {
        lleno: 'bg-indigo-500 text-white',
        claro: 'border-indigo-500 bg-indigo-500/15 text-indigo-800 dark:text-indigo-300',
    },
    'pisco>mina': {
        lleno: 'bg-violet-500 text-white',
        claro: 'border-violet-500 bg-violet-500/15 text-violet-800 dark:text-violet-300',
    },
    'lima>mina': {
        lleno: 'bg-teal-500 text-white',
        claro: 'border-teal-500 bg-teal-500/15 text-teal-800 dark:text-teal-300',
    },
    'lima>juliaca': {
        lleno: 'bg-green-500 text-white',
        claro: 'border-green-500 bg-green-500/15 text-green-800 dark:text-green-300',
    },
    'lima>sur': {
        lleno: 'bg-orange-500 text-white',
        claro: 'border-orange-500 bg-orange-500/15 text-orange-800 dark:text-orange-300',
    },
    'lima>cusco': {
        lleno: 'bg-amber-500 text-amber-950',
        claro: 'border-amber-500 bg-amber-500/15 text-amber-800 dark:text-amber-300',
    },
    'sur>juliaca': {
        lleno: 'bg-lime-500 text-lime-950',
        claro: 'border-lime-500 bg-lime-500/15 text-lime-800 dark:text-lime-300',
    },
    'cusco>juliaca': {
        lleno: 'bg-lime-500 text-lime-950',
        claro: 'border-lime-500 bg-lime-500/15 text-lime-800 dark:text-lime-300',
    },
    'juliaca>lima': {
        lleno: 'bg-emerald-700 text-white',
        claro: 'border-emerald-700 bg-emerald-700/15 text-emerald-800 dark:text-emerald-300',
    },
};

const SIN_COLOR = {
    lleno: 'bg-zinc-400 text-white dark:bg-zinc-600',
    claro: 'border-zinc-400 bg-zinc-400/15 text-zinc-700 dark:text-zinc-300',
};

const NOMBRE_ZONA: Record<string, string> = {
    mina: 'Mina',
    juliaca: 'Juliaca',
    cusco: 'Cusco',
    sur: 'Sur',
    pisco: 'Pisco',
    lima: 'Lima',
};

function aDia(fecha: string): number {
    return new Date(`${fecha}T00:00:00`).getTime();
}

function hoyMs(): number {
    const ahora = new Date();

    return new Date(
        ahora.getFullYear(),
        ahora.getMonth(),
        ahora.getDate(),
    ).getTime();
}

/**
 * Las paradas del ticket como tramos en el tiempo. Una parada con GR empieza
 * el día de su GR y dura hasta que arranca la siguiente; la que no tiene GR
 * se dibuja a continuación con una duración estimada.
 */
function segmentosDe(vuelta: TarjetaVuelta, hoy: number): Segmento[] {
    const paradas: ParadaVuelta[] = [...vuelta.ida, ...vuelta.regreso];
    const inicio = vuelta.inicio ? aDia(vuelta.inicio) : hoy;
    const segmentos: Segmento[] = [];
    let cursor = inicio;

    paradas.forEach((parada, indice) => {
        const vacio = parada.tipo_carga === 'vacio';
        const desde = parada.fecha ? aDia(parada.fecha) : cursor;
        const siguiente = paradas
            .slice(indice + 1)
            .find((otra) => otra.fecha !== null);
        const estimada =
            desde +
            (vacio ? DIAS_ESTIMADOS.vacio : DIAS_ESTIMADOS.carga) * DIA_MS;
        let hasta =
            siguiente?.fecha && indice + 1 === paradas.indexOf(siguiente)
                ? aDia(siguiente.fecha)
                : estimada;

        // La última parada de un ticket en ruta sigue hasta hoy.
        const ultima = indice === paradas.length - 1;

        if (
            ultima &&
            (vuelta.etapa === 'en_ida' || vuelta.etapa === 'en_vuelta')
        ) {
            hasta = Math.max(hasta, hoy + DIA_MS);
        }

        const origen = parada.zona_origen ?? '?';
        const destino = parada.zona_destino ?? '?';

        segmentos.push({
            desde,
            hasta: Math.max(hasta, desde + DIA_MS / 2),
            ruta: `${origen}>${destino}`,
            nombreRuta: `${NOMBRE_ZONA[origen] ?? '¿?'} → ${NOMBRE_ZONA[destino] ?? '¿?'}`,
            etiqueta: `${parada.origen ?? '¿?'} → ${parada.destino ?? '¿?'} · ${vacio ? 'vacía' : parada.tipo_carga_label}`,
            vacio,
            estimado: parada.guias.length === 0,
        });
        cursor = Math.max(cursor, hasta);
    });

    if (vuelta.regreso.length === 0 && vuelta.etapa !== 'en_base') {
        segmentos.push({
            desde: cursor,
            hasta: cursor + DIAS_ESTIMADOS.vuelta_por_definir * DIA_MS,
            ruta: 'por_definir',
            nombreRuta: 'Vuelta por definir',
            etiqueta: 'vuelta ?',
            vacio: false,
            estimado: true,
        });
    }

    return segmentos;
}

/**
 * La programación como tablero de despacho: una fila por unidad, los días
 * en columnas y cada ticket como una barra partida por parada, con un color
 * por tramo del recorrido (Juliaca → mina celeste, mina → Pisco azul…). Lo
 * que ya tiene GR va lleno; lo programado, claro y punteado. Clic en la
 * barra abre el ticket.
 */
export function GanttVueltas({
    vueltas,
    onAbrir,
}: {
    vueltas: TarjetaVuelta[];
    onAbrir: (vuelta: TarjetaVuelta) => void;
}) {
    const hoy = hoyMs();
    const [desplazamiento, setDesplazamiento] = useState(0);
    const desde = hoy - (DIAS_ATRAS - desplazamiento) * DIA_MS;
    const totalDias = DIAS_ATRAS + DIAS_ADELANTE + 1;
    const hasta = desde + totalDias * DIA_MS;

    const dias = Array.from(
        { length: totalDias },
        (_, indice) => new Date(desde + indice * DIA_MS),
    );

    const filas = useMemo(
        () =>
            [...vueltas]
                .sort(
                    (a, b) =>
                        ETAPAS.findIndex((etapa) => etapa.etapa === a.etapa) -
                            ETAPAS.findIndex(
                                (etapa) => etapa.etapa === b.etapa,
                            ) || a.placa.localeCompare(b.placa, 'es'),
                )
                .map((vuelta) => ({
                    vuelta,
                    segmentos: segmentosDe(vuelta, hoy),
                })),
        [vueltas, hoy],
    );

    // Solo los tramos que aparecen en pantalla, en el orden del recorrido.
    const leyenda = Object.entries(
        Object.fromEntries(
            filas.flatMap(({ segmentos }) =>
                segmentos.map((segmento) => [
                    segmento.ruta,
                    segmento.nombreRuta,
                ]),
            ),
        ),
    ).sort(
        ([a], [b]) =>
            ((Object.keys(COLORES_RUTA).indexOf(a) + 100) % 100) -
            ((Object.keys(COLORES_RUTA).indexOf(b) + 100) % 100),
    );

    const posicion = (ms: number) =>
        ((Math.min(Math.max(ms, desde), hasta) - desde) / (hasta - desde)) *
        100;
    const lineaDeHoy = posicion(hoy + DIA_MS / 2);
    const punto = (etapa: string) =>
        ETAPAS.find((opcion) => opcion.etapa === etapa)?.punto;

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    {leyenda.map(([ruta, nombre]) => (
                        <span key={ruta} className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'h-2.5 w-5 rounded-sm',
                                    ruta === 'por_definir'
                                        ? 'border border-dashed border-muted-foreground/60'
                                        : (COLORES_RUTA[ruta] ?? SIN_COLOR)
                                              .lleno,
                                )}
                            />
                            {nombre}
                        </span>
                    ))}
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-5 rounded-sm border border-dashed border-muted-foreground/60 bg-muted/40" />
                        Claro y punteado: programado, sin GR
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        aria-label="Semana anterior"
                        onClick={() => setDesplazamiento((valor) => valor - 7)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8"
                        disabled={desplazamiento === 0}
                        onClick={() => setDesplazamiento(0)}
                    >
                        Hoy
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        aria-label="Semana siguiente"
                        onClick={() => setDesplazamiento((valor) => valor + 7)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-xl border bg-card">
                <div className="min-w-[56rem]">
                    {/* Los días */}
                    <div className="sticky top-0 z-10 flex border-b bg-card">
                        <div className="w-40 shrink-0 border-r px-3 py-2 text-xs font-medium text-muted-foreground">
                            Unidad
                        </div>
                        <div className="relative flex flex-1">
                            {dias.map((dia) => {
                                const esHoy = dia.getTime() === hoy;
                                const finDeSemana =
                                    dia.getDay() === 0 || dia.getDay() === 6;

                                return (
                                    <div
                                        key={dia.getTime()}
                                        className={cn(
                                            'flex flex-1 flex-col items-center border-r py-1 text-[10px] leading-tight last:border-r-0',
                                            finDeSemana && 'bg-muted/40',
                                            esHoy &&
                                                'bg-primary/10 font-semibold text-primary',
                                        )}
                                    >
                                        <span className="text-muted-foreground">
                                            {DIAS_CORTOS[dia.getDay()]}
                                        </span>
                                        <span>{dia.getDate()}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {filas.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No hay tickets para mostrar.
                        </p>
                    )}

                    {filas.map(({ vuelta, segmentos }) => (
                        <div
                            key={vuelta.id}
                            className="group flex border-b last:border-b-0 hover:bg-accent/30"
                        >
                            <button
                                type="button"
                                onClick={() => onAbrir(vuelta)}
                                className="flex w-40 shrink-0 flex-col items-start justify-center border-r px-3 py-1.5 text-left"
                            >
                                <span className="flex items-center gap-1.5">
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            punto(vuelta.etapa),
                                        )}
                                    />
                                    <span className="font-mono text-sm font-semibold">
                                        {formatearPlaca(vuelta.placa)}
                                    </span>
                                </span>
                                <span className="truncate font-mono text-[10px] text-muted-foreground">
                                    {vuelta.codigo}
                                    {vuelta.dias_en_base !== null &&
                                        ` · en base ${vuelta.dias_en_base} d`}
                                </span>
                            </button>
                            <div className="relative h-11 flex-1">
                                {/* Las columnas de los días, de fondo */}
                                <div className="pointer-events-none absolute inset-0 flex">
                                    {dias.map((dia) => (
                                        <div
                                            key={dia.getTime()}
                                            className={cn(
                                                'flex-1 border-r last:border-r-0',
                                                (dia.getDay() === 0 ||
                                                    dia.getDay() === 6) &&
                                                    'bg-muted/30',
                                            )}
                                        />
                                    ))}
                                </div>
                                <div
                                    className="pointer-events-none absolute inset-y-0 w-0.5 bg-primary/60"
                                    style={{ left: `${lineaDeHoy}%` }}
                                />
                                {segmentos.map((segmento, indice) => {
                                    if (
                                        segmento.hasta <= desde ||
                                        segmento.desde >= hasta
                                    ) {
                                        return null;
                                    }

                                    const izquierda = posicion(segmento.desde);
                                    const ancho =
                                        posicion(segmento.hasta) - izquierda;
                                    const estilo =
                                        segmento.ruta === 'por_definir'
                                            ? {
                                                  lleno: 'bg-muted text-muted-foreground',
                                                  claro: 'border-muted-foreground/40 bg-muted/50 text-muted-foreground',
                                              }
                                            : (COLORES_RUTA[segmento.ruta] ??
                                              SIN_COLOR);

                                    return (
                                        <button
                                            key={indice}
                                            type="button"
                                            onClick={() => onAbrir(vuelta)}
                                            title={segmento.etiqueta}
                                            className={cn(
                                                'absolute top-2 bottom-2 overflow-hidden rounded-md px-1.5 text-left text-[11px] leading-7 font-medium whitespace-nowrap transition hover:brightness-110',
                                                segmento.estimado
                                                    ? cn(
                                                          'border border-dashed',
                                                          estilo.claro,
                                                      )
                                                    : estilo.lleno,
                                                segmento.vacio &&
                                                    !segmento.estimado &&
                                                    'opacity-60',
                                            )}
                                            style={{
                                                left: `calc(${izquierda}% + 1px)`,
                                                width: `calc(${ancho}% - 2px)`,
                                            }}
                                        >
                                            {segmento.etiqueta}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
