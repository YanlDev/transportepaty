import { Link } from '@inertiajs/react';
import { CalendarCheck, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { asistencia as rutaAsistencia } from '@/actions/App/Http/Controllers/ConductorController';
import { Button } from '@/components/ui/button';
import { estadoConfig } from '@/lib/asistencia';
import { cn } from '@/lib/utils';
import type {
    AsistenciaCalendarioAnual,
    AsistenciaCalendarioMes,
    EstadoAsistencia,
} from '@/types/fleet';

/**
 * La asistencia del conductor en la ficha: solo un mes a la vez, con su
 * resumen al lado. El año completo —doce calendarios, para marcar de
 * corrido— vive en su propia pantalla: desplegado acá empujaba el resto del
 * expediente y dejaba las celdas demasiado apretadas para marcar en ellas.
 */
export function ConductorAsistencia({
    conductorId,
    asistencia,
}: {
    conductorId: number;
    asistencia: AsistenciaCalendarioAnual;
}) {
    // Arranca en el mes en curso si el año mostrado es el actual; si se está
    // mirando otro año, en enero.
    const hoy = new Date();
    const [indiceMes, setIndiceMes] = useState(() =>
        hoy.getFullYear() === asistencia.anio ? hoy.getMonth() : 0,
    );
    const mes = asistencia.calendarios[indiceMes];

    if (!mes) {
        return null;
    }

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <CalendarCheck className="size-4 text-muted-foreground" />
                    Asistencia
                </h2>
            </div>

            <div className="grid gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                <MesCompacto
                    mes={mes}
                    puedeRetroceder={indiceMes > 0}
                    puedeAvanzar={indiceMes < asistencia.calendarios.length - 1}
                    onRetroceder={() => setIndiceMes((i) => i - 1)}
                    onAvanzar={() => setIndiceMes((i) => i + 1)}
                />

                <div className="flex flex-col gap-4">
                    <ResumenMes mes={mes} />

                    <Button asChild variant="outline" className="w-full">
                        <Link href={rutaAsistencia(conductorId)}>
                            Ver detalle de asistencia
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}

/**
 * Un mes en chico, solo para leer: cada día marcado se pinta como un círculo
 * lleno del color de su estado, y el día de hoy va anillado. Marcar y
 * corregir se hace en la vista del año o en el rooster, donde las celdas son
 * grandes y están pensadas para eso.
 */
function MesCompacto({
    mes,
    puedeRetroceder,
    puedeAvanzar,
    onRetroceder,
    onAvanzar,
}: {
    mes: AsistenciaCalendarioMes;
    puedeRetroceder: boolean;
    puedeAvanzar: boolean;
    onRetroceder: () => void;
    onAvanzar: () => void;
}) {
    const hoy = new Date().toISOString().slice(0, 10);
    const nombresDias = mes.dias.slice(0, 7).map((dia) => dia.dia_semana);

    return (
        <div>
            <div className="mb-3 flex items-center justify-between gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    onClick={onRetroceder}
                    disabled={!puedeRetroceder}
                    aria-label="Mes anterior"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <p className="text-sm font-medium text-primary capitalize">
                    {formatearMes(mes.mes)}
                </p>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    onClick={onAvanzar}
                    disabled={!puedeAvanzar}
                    aria-label="Mes siguiente"
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>

            <div className="grid grid-cols-7 justify-items-center gap-y-1.5">
                {nombresDias.map((nombre, indice) => (
                    <span
                        key={indice}
                        className="pb-1 text-[11px] font-medium text-muted-foreground"
                    >
                        {nombre}
                    </span>
                ))}

                {mes.dias.map((dia) => {
                    const marca = mes.marcas[dia.fecha];
                    const info = marca ? estadoConfig[marca.estado] : null;
                    const esHoy = dia.fecha === hoy;

                    return (
                        <span
                            key={dia.fecha}
                            title={`${dia.numero} — ${info ? info.label : 'Sin marcar'}`}
                            className={cn(
                                'grid size-8 place-items-center rounded-full text-xs font-medium tabular-nums',
                                dia.es_relleno && 'text-muted-foreground/30',
                                !dia.es_relleno &&
                                    !info &&
                                    'text-muted-foreground',
                                !dia.es_relleno && info && info.solido,
                                // Hoy sin marcar se pinta igual, en el color de
                                // la app, para ubicarse en el mes de un vistazo.
                                esHoy &&
                                    !info &&
                                    'bg-primary text-primary-foreground',
                                esHoy &&
                                    info &&
                                    'ring-2 ring-primary ring-offset-1 ring-offset-card',
                            )}
                        >
                            {dia.numero}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}

function ResumenMes({ mes }: { mes: AsistenciaCalendarioMes }) {
    const conteos = Object.values(mes.marcas).reduce<
        Record<EstadoAsistencia, number>
    >(
        (acumulado, marca) => {
            acumulado[marca.estado] += 1;

            return acumulado;
        },
        { asistencia: 0, falta: 0, vacaciones: 0, descanso: 0 },
    );

    return (
        <div className="divide-y rounded-lg border border-border">
            {(Object.keys(estadoConfig) as EstadoAsistencia[]).map((estado) => (
                <div
                    key={estado}
                    className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm"
                >
                    <span className="flex items-center gap-2.5">
                        <span
                            className={cn(
                                'size-2.5 shrink-0 rounded-full',
                                estadoConfig[estado].punto,
                            )}
                            aria-hidden
                        />
                        {estadoConfig[estado].labelResumen}
                    </span>
                    <span className="font-semibold tabular-nums">
                        {conteos[estado]}
                    </span>
                </div>
            ))}

            <div className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                <span className="flex items-center gap-2.5 text-muted-foreground">
                    <span
                        className="size-2.5 shrink-0 rounded-full bg-muted-foreground/40"
                        aria-hidden
                    />
                    Balance del mes
                </span>
                <span className="font-semibold tabular-nums">
                    {mes.dias_debidos > 0 && '+'}
                    {mes.dias_debidos}
                </span>
            </div>
        </div>
    );
}

function formatearMes(mes: string): string {
    return new Date(`${mes}T00:00:00`).toLocaleDateString('es-PE', {
        month: 'long',
        year: 'numeric',
    });
}
