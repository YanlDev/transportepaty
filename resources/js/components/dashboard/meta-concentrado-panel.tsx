import { Target } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { MetaConcentrado } from '@/types/dashboard';

/**
 * El indicador principal del área: cuánto concentrado se movió en el mes
 * elegido contra la meta de 120.
 *
 * En el mes en curso dice a qué ritmo hay que cerrar los días que quedan y
 * cuánto da la proyección; en un mes que ya terminó dice cómo cerró —si se
 * cumplió o por cuánto faltó—, que es lo que se mira al comparar meses.
 */
export function MetaConcentradoPanel({ meta }: { meta: MetaConcentrado }) {
    const porcentaje = Math.min(
        100,
        Math.round((meta.realizados / meta.meta) * 100),
    );
    // En un mes cerrado lo que cuenta es cómo terminó; en curso, hacia dónde va.
    const referencia = meta.cerrado ? meta.realizados : meta.proyeccion;
    const vaBien = referencia >= meta.meta;
    const vaAjustado = !vaBien && referencia >= meta.meta * 0.9;

    const tono = vaBien
        ? 'text-emerald-700 dark:text-emerald-500'
        : vaAjustado
          ? 'text-amber-700 dark:text-amber-500'
          : 'text-red-700 dark:text-red-500';

    const colorBarra = vaBien
        ? 'bg-emerald-500'
        : vaAjustado
          ? 'bg-amber-500'
          : 'bg-red-500';

    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Target className="size-4 text-muted-foreground" />
                    Meta de viajes — {meta.cerrado ? meta.mes : 'mes en curso'}
                </h2>
                <p className={cn('text-xs font-medium', tono)}>
                    {meta.cerrado
                        ? vaBien
                            ? 'Meta cumplida'
                            : 'Mes cerrado'
                        : `Proyección: ${meta.proyeccion} viajes`}
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
                {meta.cerrado ? (
                    vaBien ? (
                        `Se superó la meta por ${meta.realizados - meta.meta} viajes.`
                    ) : (
                        `Faltaron ${meta.faltantes} viajes para la meta.`
                    )
                ) : (
                    <>
                        Faltan {meta.faltantes} viajes · {meta.diasRestantes}{' '}
                        días restantes
                        {meta.ritmoNecesario !== null &&
                            ` · ritmo necesario: ${meta.ritmoNecesario}/día`}
                    </>
                )}
            </p>
        </section>
    );
}
