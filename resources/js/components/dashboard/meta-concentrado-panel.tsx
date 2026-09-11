import { Target } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { MetaConcentrado } from '@/types/dashboard';

/**
 * El indicador principal del área: cuánto lleva el mes en curso de
 * concentrado contra la meta de 120, y a qué ritmo hay que cerrar los días
 * que quedan. Independiente del rango de arriba —siempre es el mes en curso,
 * porque el compromiso es mensual.
 */
export function MetaConcentradoPanel({ meta }: { meta: MetaConcentrado }) {
    const porcentaje = Math.min(
        100,
        Math.round((meta.realizados / meta.meta) * 100),
    );
    const vaBienEncaminado = meta.proyeccion >= meta.meta;
    const vaAjustado = !vaBienEncaminado && meta.proyeccion >= meta.meta * 0.9;

    const tono = vaBienEncaminado
        ? 'text-emerald-700 dark:text-emerald-500'
        : vaAjustado
          ? 'text-amber-700 dark:text-amber-500'
          : 'text-red-700 dark:text-red-500';

    const colorBarra = vaBienEncaminado
        ? 'bg-emerald-500'
        : vaAjustado
          ? 'bg-amber-500'
          : 'bg-red-500';

    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Target className="size-4 text-muted-foreground" />
                    Meta de viajes — mes en curso
                </h2>
                <p className={cn('text-xs font-medium', tono)}>
                    Proyección: {meta.proyeccion} viajes
                </p>
            </div>

            <div className="mt-3 flex items-baseline gap-2">
                <span className="text-3xl font-semibold tabular-nums">
                    {meta.realizados}
                </span>
                <span className="text-sm text-muted-foreground">
                    / {meta.meta} viajes
                </span>
                <span className="ml-auto text-sm text-muted-foreground tabular-nums">
                    {porcentaje}%
                </span>
            </div>

            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={cn('h-full rounded-full', colorBarra)}
                    style={{ width: `${porcentaje}%` }}
                />
            </div>

            <p className="mt-3 text-xs text-muted-foreground">
                Faltan {meta.faltantes} viajes · {meta.diasRestantes} días
                restantes
                {meta.ritmoNecesario !== null &&
                    ` · ritmo necesario: ${meta.ritmoNecesario}/día`}
            </p>
        </section>
    );
}
