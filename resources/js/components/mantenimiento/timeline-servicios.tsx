import { Wrench } from 'lucide-react';
import { formatearFechaHora, formatearSoles } from '@/lib/format';
import type { Mantenimiento } from '@/types/fleet';

/**
 * Vertical timeline of completed maintenance visits (newest first).
 */
export function TimelineServicios({
    mantenimientos,
}: {
    mantenimientos: Mantenimiento[];
}) {
    return (
        <ol className="relative flex flex-col gap-5 border-l border-border pl-6">
            {mantenimientos.map((m) => (
                <li key={m.id} className="relative">
                    <span className="absolute -left-[31px] grid size-5 place-items-center rounded-full bg-emerald-100 text-emerald-700 ring-4 ring-background">
                        <Wrench className="size-3" />
                    </span>

                    <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                        <p className="text-sm font-medium text-foreground">
                            {formatearFechaHora(m.fecha_realizado)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {m.odometro.toLocaleString('es-PE')} km
                            {m.proveedor ? ` · ${m.proveedor}` : ''}
                            {m.costo_total != null
                                ? ` · ${formatearSoles(m.costo_total)}`
                                : ''}
                        </p>
                    </div>

                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {m.items.map((i) => i.nombre).join(' · ')}
                    </p>
                </li>
            ))}
        </ol>
    );
}
