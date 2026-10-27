import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { IconoTaller } from '@/components/taller/estado-taller';
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
 * Cuánto se dibuja que dura un tramo: lo justo para leerlo, no un
 * pronóstico. Vale también para los que tienen GR: la GR dice cuándo salió,
 * no cuándo llegó, y estirar la barra hasta la GR siguiente pintaba como
 * viaje los días de espera. La vuelta por definir ocupa lo que suele durar
 * un regreso.
 */
const DIAS_ESTIMADOS = { vacio: 1, carga: 2, vuelta_por_definir: 5 };

const DIAS_CORTOS = ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'];

type Segmento = {
    /** El ticket al que pertenece: el clic lo abre. */
    vuelta: TarjetaVuelta;
    desde: number;
    hasta: number;
    /** Para quién trabaja la unidad en el tramo: decide el color. */
    clase: keyof typeof COLORES;
    etiqueta: string;
    vacio: boolean;
    /** Sin GR todavía: lo programado, dibujado claro y punteado. */
    estimado: boolean;
};

/**
 * El color dice para quién trabaja la unidad en ese tramo: todo lo de Minsur
 * (concentrado, metálico, escoria, materiales, sacos, Ransa) en el azul de
 * Minsur, la carga particular en naranja y los tramos vacíos en gris. El
 * tramo y la carga van escritos en la barra.
 */
const COLORES: Record<
    'minsur' | 'particular' | 'vacio' | 'espera' | 'por_definir',
    { nombre: string; lleno: string; claro: string }
> = {
    minsur: {
        nombre: 'Minsur',
        lleno: 'bg-[#1d3c8f] text-white',
        claro: 'border-[#1d3c8f] bg-[#1d3c8f]/15 text-[#1d3c8f] dark:border-blue-400 dark:text-blue-300',
    },
    particular: {
        nombre: 'Particular',
        lleno: 'bg-orange-500 text-white',
        claro: 'border-orange-500 bg-orange-500/15 text-orange-800 dark:text-orange-300',
    },
    vacio: {
        nombre: 'Vacía',
        lleno: 'bg-zinc-400 text-white dark:bg-zinc-600',
        claro: 'border-zinc-400 bg-zinc-400/15 text-zinc-700 dark:text-zinc-300',
    },
    espera: {
        nombre: 'En espera / en base',
        lleno: 'bg-[repeating-linear-gradient(135deg,color-mix(in_oklab,var(--color-muted-foreground)_22%,transparent)_0_5px,transparent_5px_9px)] text-muted-foreground',
        claro: 'bg-[repeating-linear-gradient(135deg,color-mix(in_oklab,var(--color-muted-foreground)_22%,transparent)_0_5px,transparent_5px_9px)] text-muted-foreground',
    },
    por_definir: {
        nombre: 'Vuelta por definir',
        lleno: 'bg-muted text-muted-foreground',
        claro: 'border-muted-foreground/40 bg-muted/50 text-muted-foreground',
    },
};

/** Para quién es una carga: lo que no es particular ni vacío es de Minsur. */
function claseDeCarga(tipoCarga: string): keyof typeof COLORES {
    if (tipoCarga === 'vacio') {
        return 'vacio';
    }

    return tipoCarga === 'particular' ? 'particular' : 'minsur';
}

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

/** «3 d» o «hoy»: cuánto lleva una espera. */
function duracion(desde: number, hasta: number): string {
    const dias = Math.round((hasta - desde) / DIA_MS);

    return dias <= 0 ? 'hoy' : `${dias} d`;
}

/**
 * Las paradas del ticket como tramos en el tiempo. Una parada con GR empieza
 * el día de su GR y dura lo estimado del viaje, sin pisar la GR siguiente; lo
 * que pasa entre una GR y la otra es espera (descargando, esperando carga) y
 * se dibuja aparte, rayado, con el lugar donde está. La parada sin GR va a
 * continuación, clara y punteada.
 *
 * `ultimaDeLaUnidad`: solo el ticket más reciente de la unidad sigue hasta
 * hoy (en ruta, o en base esperando el siguiente).
 */
function segmentosDe(
    vuelta: TarjetaVuelta,
    hoy: number,
    ultimaDeLaUnidad: boolean,
): Segmento[] {
    const paradas: ParadaVuelta[] = [...vuelta.ida, ...vuelta.regreso];
    const segmentos: Segmento[] = [];
    let cursor = vuelta.inicio ? aDia(vuelta.inicio) : hoy;
    let anteriorConGuia: ParadaVuelta | null = null;

    const espera = (desde: number, hasta: number, donde: string) =>
        segmentos.push({
            vuelta,
            desde,
            hasta,
            clase: 'espera',
            etiqueta: `${donde} · ${duracion(desde, hasta)}`,
            vacio: false,
            estimado: false,
        });

    paradas.forEach((parada, indice) => {
        const vacio = parada.tipo_carga === 'vacio';
        const dias =
            (vacio ? DIAS_ESTIMADOS.vacio : DIAS_ESTIMADOS.carga) * DIA_MS;
        let desde = cursor;
        let hasta = cursor + dias;

        if (parada.fecha) {
            desde = aDia(parada.fecha);

            if (anteriorConGuia && desde > cursor) {
                espera(cursor, desde, `en ${anteriorConGuia.destino ?? '¿?'}`);
            }

            const siguiente = paradas
                .slice(indice + 1)
                .find((otra) => otra.fecha !== null);
            hasta = Math.min(
                desde + dias,
                siguiente?.fecha ? aDia(siguiente.fecha) : Infinity,
            );
            anteriorConGuia = parada;
        }

        hasta = Math.max(hasta, desde + DIA_MS / 2);

        segmentos.push({
            vuelta,
            desde,
            hasta,
            clase: claseDeCarga(parada.tipo_carga),
            etiqueta: `${parada.origen ?? '¿?'} → ${parada.destino ?? '¿?'} · ${vacio ? 'vacía' : parada.tipo_carga_label}`,
            vacio,
            estimado: parada.guias.length === 0,
        });
        cursor = Math.max(cursor, hasta);
    });

    const ultima = paradas.at(-1);

    // Lo último que se hizo tiene GR y no hay nada programado después: la
    // unidad espera ahí hasta hoy (en ruta) o está en base.
    if (ultimaDeLaUnidad && ultima?.fecha && cursor < hoy + DIA_MS) {
        espera(
            cursor,
            hoy + DIA_MS,
            vuelta.etapa === 'en_base'
                ? 'en base'
                : `en ${ultima.destino ?? '¿?'}`,
        );
        cursor = hoy + DIA_MS;
    }

    if (vuelta.regreso.length === 0 && vuelta.etapa !== 'en_base') {
        segmentos.push({
            vuelta,
            desde: cursor,
            hasta: cursor + DIAS_ESTIMADOS.vuelta_por_definir * DIA_MS,
            clase: 'por_definir',
            etiqueta: 'vuelta ?',
            vacio: false,
            estimado: true,
        });
    }

    return segmentos;
}

/**
 * La programación como tablero de despacho: una fila por unidad, los días
 * en columnas y sus tickets uno detrás de otro, cada uno partido por parada
 * con el color de para quién trabaja. Lo que ya tiene GR va lleno; lo
 * programado, claro y punteado; la espera entre una GR y la siguiente,
 * rayada. Clic en una barra abre su ticket.
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

    // Una fila por unidad: el ticket que acaba de cerrar y el que arranca
    // van en la misma línea. La fila toma la etapa del más reciente.
    const filas = useMemo(() => {
        const porUnidad = new Map<number, TarjetaVuelta[]>();

        for (const vuelta of vueltas) {
            porUnidad.set(vuelta.tracto_id, [
                ...(porUnidad.get(vuelta.tracto_id) ?? []),
                vuelta,
            ]);
        }

        const orden = (etapa: string) =>
            ETAPAS.findIndex((opcion) => opcion.etapa === etapa);

        return [...porUnidad.values()]
            .map((tickets) => {
                const ordenados = [...tickets].sort(
                    (a, b) =>
                        (a.inicio ?? '').localeCompare(b.inicio ?? '') ||
                        a.id - b.id,
                );
                const actual = ordenados.at(-1) as TarjetaVuelta;

                return {
                    actual,
                    tickets: ordenados,
                    segmentos: ordenados.flatMap((vuelta) =>
                        segmentosDe(vuelta, hoy, vuelta === actual),
                    ),
                };
            })
            .sort(
                (a, b) =>
                    orden(a.actual.etapa) - orden(b.actual.etapa) ||
                    a.actual.placa.localeCompare(b.actual.placa, 'es'),
            );
    }, [vueltas, hoy]);

    // Solo los colores que aparecen en pantalla.
    const enPantalla = new Set(
        filas.flatMap(({ segmentos }) =>
            segmentos.map((segmento) => segmento.clase),
        ),
    );
    const leyenda = (Object.keys(COLORES) as (keyof typeof COLORES)[]).filter(
        (clase) => enPantalla.has(clase),
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
                    {leyenda.map((clase) => (
                        <span key={clase} className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'h-2.5 w-5 rounded-sm',
                                    clase === 'por_definir'
                                        ? 'border border-dashed border-muted-foreground/60'
                                        : COLORES[clase].lleno,
                                )}
                            />
                            {COLORES[clase].nombre}
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

                    {filas.map(({ actual: vuelta, tickets, segmentos }) => (
                        <div
                            key={vuelta.tracto_id}
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
                                    {vuelta.etapa !== 'en_base' && (
                                        <IconoTaller revision={vuelta.taller} />
                                    )}
                                </span>
                                <span className="truncate font-mono text-[10px] text-muted-foreground">
                                    {tickets
                                        .map((ticket) => ticket.codigo)
                                        .join(' · ')}
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
                                    const estilo = COLORES[segmento.clase];

                                    return (
                                        <button
                                            key={indice}
                                            type="button"
                                            onClick={() =>
                                                onAbrir(segmento.vuelta)
                                            }
                                            title={`${segmento.vuelta.codigo ?? ''} · ${segmento.etiqueta}`}
                                            className={cn(
                                                'absolute top-2 bottom-2 overflow-hidden rounded-md px-1.5 text-left text-[11px] leading-7 font-medium whitespace-nowrap transition hover:brightness-110',
                                                segmento.clase === 'espera'
                                                    ? cn(
                                                          'top-3.5 bottom-3.5 rounded-sm leading-4 font-normal',
                                                          estilo.lleno,
                                                      )
                                                    : segmento.estimado
                                                      ? cn(
                                                            'border border-dashed',
                                                            estilo.claro,
                                                        )
                                                      : estilo.lleno,
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
