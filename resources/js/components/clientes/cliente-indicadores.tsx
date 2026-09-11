import {
    ArrowRight,
    CalendarDays,
    Package,
    Route as RouteIcon,
    TrendingDown,
    TrendingUp,
    Truck,
} from 'lucide-react';
import { Indicador } from '@/components/ui/indicador';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ClienteEstadisticas } from '@/types/fleet';

/** Las cuatro tarjetas de arriba: cómo viene el cliente de un vistazo. */
export function ClienteIndicadores({
    estadisticas,
}: {
    estadisticas: ClienteEstadisticas;
}) {
    const ruta = estadisticas.ruta_frecuente;

    return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Indicador
                icono={<Truck className="size-5" />}
                label="Viajes realizados"
                valor={estadisticas.viajes_totales}
                color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                pie={
                    <Variacion
                        variacion={estadisticas.variacion_mes}
                        viajesMes={estadisticas.viajes_mes}
                    />
                }
            />
            <Indicador
                icono={<CalendarDays className="size-5" />}
                label="Último viaje"
                valor={
                    estadisticas.ultimo_viaje
                        ? formatearFecha(estadisticas.ultimo_viaje)
                        : '—'
                }
                color="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                pie={
                    estadisticas.ultimo_viaje
                        ? haceCuanto(estadisticas.ultimo_viaje)
                        : 'Sin viajes'
                }
            />
            <Indicador
                icono={<Package className="size-5" />}
                label="Tipos de carga"
                valor={estadisticas.tipos_carga}
                color="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                pie={estadisticas.carga_principal ?? 'Sin viajes'}
            />
            <Indicador
                icono={<RouteIcon className="size-5" />}
                label="Ruta más frecuente"
                valor={
                    ruta ? (
                        <span className="flex items-center gap-1.5 text-base">
                            <span className="truncate">{ruta.origen}</span>
                            <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
                            <span className="truncate">{ruta.destino}</span>
                        </span>
                    ) : (
                        '—'
                    )
                }
                color="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                pie={ruta ? `${ruta.viajes} viajes` : 'Sin viajes'}
            />
        </div>
    );
}

/**
 * Cómo viene el mes contra el anterior. Es la única tendencia del sistema que
 * se puede calcular de verdad: los viajes tienen fecha, así que el mes pasado
 * se puede volver a contar cuando haga falta.
 */
function Variacion({
    variacion,
    viajesMes,
}: {
    variacion: number | null;
    viajesMes: number;
}) {
    if (variacion === null) {
        return <>{viajesMes} este mes</>;
    }

    const subio = variacion >= 0;

    return (
        <span className="flex items-center gap-1">
            <span
                className={cn(
                    'inline-flex items-center gap-0.5 font-medium',
                    subio
                        ? 'text-emerald-700 dark:text-emerald-500'
                        : 'text-red-700 dark:text-red-500',
                )}
            >
                {subio ? (
                    <TrendingUp className="size-3" />
                ) : (
                    <TrendingDown className="size-3" />
                )}
                {subio && '+'}
                {variacion}%
            </span>
            vs. mes anterior
        </span>
    );
}

/** «Hace 3 días» a partir de una fecha Y-m-d. */
function haceCuanto(fecha: string): string {
    const dias = Math.round(
        (Date.now() - new Date(`${fecha}T00:00:00`).getTime()) / 86_400_000,
    );

    if (dias <= 0) {
        return 'Hoy';
    }

    if (dias === 1) {
        return 'Ayer';
    }

    return `Hace ${dias} días`;
}
