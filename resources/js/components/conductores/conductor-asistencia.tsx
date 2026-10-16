import { Link } from '@inertiajs/react';
import { CalendarCheck, ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
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
 * La asistencia del conductor en la ficha: una semana a la vez, a lo ancho,
 * con el resumen del mes al lado. El año completo —para marcar de corrido—
 * vive en su propia pantalla; acá solo se lee.
 */
export function ConductorAsistencia({
    conductorId,
    asistencia,
}: {
    conductorId: number;
    asistencia: AsistenciaCalendarioAnual;
}) {
    // Todos los días reales del año con su mes, para poder caminar por
    // semanas sin importar en qué mes caen.
    const dias = useMemo(
        () =>
            asistencia.calendarios.flatMap((mes, indiceMes) =>
                mes.dias
                    .filter((dia) => !dia.es_relleno)
                    .map((dia) => ({ dia, indiceMes })),
            ),
        [asistencia],
    );

    // Arranca en la semana de hoy si el año mostrado es el actual; si no, en
    // la primera semana del año.
    const hoy = fechaLocal(new Date());
    const [lunes, setLunes] = useState(() => {
        const referencia = dias.some(({ dia }) => dia.fecha === hoy)
            ? hoy
            : (dias[0]?.dia.fecha ?? hoy);

        return lunesDe(referencia);
    });

    const semana = Array.from({ length: 7 }, (_, indice) => {
        const fecha = sumarDias(lunes, indice);

        return {
            fecha,
            encontrado: dias.find(({ dia }) => dia.fecha === fecha),
        };
    });

    const indiceMes =
        semana.find((dia) => dia.encontrado)?.encontrado?.indiceMes ?? 0;
    const mes = asistencia.calendarios[indiceMes];
    const primera = dias[0]?.dia.fecha ?? lunes;
    const ultima = dias[dias.length - 1]?.dia.fecha ?? lunes;

    if (!mes) {
        return null;
    }

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-3">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <CalendarCheck className="size-4 text-muted-foreground" />
                    Asistencia
                </h2>
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setLunes(sumarDias(lunes, -7))}
                        disabled={lunes <= primera}
                        aria-label="Semana anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="min-w-40 text-center text-sm font-medium tabular-nums">
                        {rangoSemana(lunes)}
                    </span>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setLunes(sumarDias(lunes, 7))}
                        disabled={sumarDias(lunes, 6) >= ultima}
                        aria-label="Semana siguiente"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="flex flex-col gap-3 p-3">
                <ol className="grid grid-cols-7 gap-1.5">
                    {semana.map(({ fecha, encontrado }) => {
                        const marca = encontrado
                            ? asistencia.calendarios[encontrado.indiceMes]
                                  .marcas[fecha]
                            : undefined;
                        const info = marca ? estadoConfig[marca.estado] : null;
                        const esHoy = fecha === hoy;

                        return (
                            <li
                                key={fecha}
                                title={info ? info.label : 'Sin marcar'}
                                className={cn(
                                    'flex flex-col items-center gap-1.5 rounded-lg border py-2.5',
                                    esHoy
                                        ? 'border-primary bg-primary/5'
                                        : 'border-transparent',
                                    !encontrado && 'opacity-30',
                                )}
                            >
                                <span className="text-[11px] font-medium text-muted-foreground uppercase">
                                    {nombreDia(fecha)}
                                </span>
                                <span
                                    className={cn(
                                        'grid size-9 place-items-center rounded-full text-sm font-semibold tabular-nums',
                                        info
                                            ? info.solido
                                            : 'border border-dashed border-border text-muted-foreground',
                                    )}
                                >
                                    {Number(fecha.slice(8, 10))}
                                </span>
                                <span className="hidden text-[11px] text-muted-foreground sm:block">
                                    {info ? info.letra : '—'}
                                </span>
                            </li>
                        );
                    })}
                </ol>

                <div className="flex flex-col gap-3 border-t pt-3 md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-col gap-1.5">
                        <p className="text-xs font-medium text-muted-foreground capitalize">
                            {formatearMes(mes.mes)}
                        </p>
                        <ResumenMes mes={mes} />
                    </div>

                    <Button asChild variant="outline" size="sm">
                        <Link href={rutaAsistencia(conductorId)}>
                            Ver detalle de asistencia
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}

function fechaLocal(fecha: Date): string {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');

    return `${fecha.getFullYear()}-${mes}-${dia}`;
}

function sumarDias(fecha: string, dias: number): string {
    const fechaObjeto = new Date(`${fecha}T12:00:00`);
    fechaObjeto.setDate(fechaObjeto.getDate() + dias);

    return fechaLocal(fechaObjeto);
}

/** El lunes de la semana de esa fecha (la semana va de lunes a domingo). */
function lunesDe(fecha: string): string {
    const diaSemana = new Date(`${fecha}T12:00:00`).getDay();

    return sumarDias(fecha, diaSemana === 0 ? -6 : 1 - diaSemana);
}

function nombreDia(fecha: string): string {
    return new Date(`${fecha}T12:00:00`)
        .toLocaleDateString('es-PE', { weekday: 'short' })
        .replace('.', '');
}

function rangoSemana(lunes: string): string {
    const formato = (fecha: string) =>
        new Date(`${fecha}T12:00:00`).toLocaleDateString('es-PE', {
            day: 'numeric',
            month: 'short',
        });

    return `${formato(lunes)} – ${formato(sumarDias(lunes, 6))}`;
}

/** El mes en una fila: cuántos días de cada estado y el balance. */
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
        <ul className="flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
            {(Object.keys(estadoConfig) as EstadoAsistencia[]).map((estado) => (
                <li key={estado} className="flex items-center gap-2">
                    <span
                        className={cn(
                            'size-2.5 shrink-0 rounded-full',
                            estadoConfig[estado].punto,
                        )}
                        aria-hidden
                    />
                    <span className="text-muted-foreground">
                        {estadoConfig[estado].labelResumen}
                    </span>
                    <span className="font-semibold tabular-nums">
                        {conteos[estado]}
                    </span>
                </li>
            ))}
            <li className="flex items-center gap-2">
                <span
                    className="size-2.5 shrink-0 rounded-full bg-muted-foreground/40"
                    aria-hidden
                />
                <span className="text-muted-foreground">Balance del mes</span>
                <span className="font-semibold tabular-nums">
                    {mes.dias_debidos > 0 && '+'}
                    {mes.dias_debidos}
                </span>
            </li>
        </ul>
    );
}

function formatearMes(mes: string): string {
    return new Date(`${mes}T00:00:00`).toLocaleDateString('es-PE', {
        month: 'long',
        year: 'numeric',
    });
}
