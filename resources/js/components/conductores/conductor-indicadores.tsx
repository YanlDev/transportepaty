import {
    CalendarCheck,
    CalendarDays,
    FileText,
    Route as RouteIcon,
} from 'lucide-react';
import { Indicador } from '@/components/ui/indicador';
import { formatearFecha } from '@/lib/format';
import type { ConductorEstadisticas } from '@/types/fleet';

export function ConductorIndicadores({
    estadisticas,
}: {
    estadisticas: ConductorEstadisticas;
}) {
    const documentosCompletos =
        estadisticas.documentos_vigentes === estadisticas.documentos_totales;

    return (
        <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <Indicador
                icono={<RouteIcon className="size-5" />}
                label="Viajes totales"
                valor={estadisticas.viajes_totales}
                pie={
                    estadisticas.ultimo_viaje
                        ? `Último: ${formatearFecha(estadisticas.ultimo_viaje)}`
                        : 'Sin viajes'
                }
                color="bg-blue-500/10 text-blue-600 dark:text-blue-400"
            />
            {estadisticas.dias_trabajados_mes !== null && (
                <Indicador
                    icono={<CalendarCheck className="size-5" />}
                    label="Días trabajados"
                    valor={estadisticas.dias_trabajados_mes}
                    pie="Este mes"
                    color="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                />
            )}
            {estadisticas.dias_descanso_mes !== null && (
                <Indicador
                    icono={<CalendarDays className="size-5" />}
                    label="Días de descanso"
                    valor={estadisticas.dias_descanso_mes}
                    pie={
                        estadisticas.faltas_mes
                            ? `Este mes · ${estadisticas.faltas_mes} falta${estadisticas.faltas_mes === 1 ? '' : 's'}`
                            : 'Este mes'
                    }
                    color="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                />
            )}
            <Indicador
                icono={<FileText className="size-5" />}
                label="Documentos"
                valor={`${estadisticas.documentos_vigentes} / ${estadisticas.documentos_totales}`}
                pie={documentosCompletos ? 'Vigentes' : 'Requieren atención'}
                color={
                    documentosCompletos
                        ? 'bg-violet-500/10 text-violet-600 dark:text-violet-400'
                        : 'bg-red-500/10 text-red-600 dark:text-red-400'
                }
            />
        </div>
    );
}
