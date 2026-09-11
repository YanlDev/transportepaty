import { IdCard } from 'lucide-react';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Conductor } from '@/types/fleet';

export function ConductorLicencia({ conductor }: { conductor: Conductor }) {
    const vencida = conductor.licencia_vence
        ? new Date(`${conductor.licencia_vence}T00:00:00`) < new Date()
        : false;

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="flex items-center justify-between gap-2 border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <IdCard className="size-4 text-muted-foreground" />
                    Licencia de conducir
                </h2>
                {conductor.licencia_vence && (
                    <StatusBadge
                        label={vencida ? 'Vencida' : 'Vigente'}
                        tone={vencida ? 'danger' : 'success'}
                        dot={false}
                    />
                )}
            </div>

            <div className="grid grid-cols-2 gap-4 p-4">
                <div>
                    <p className="text-xs text-muted-foreground">
                        N.º de licencia
                    </p>
                    <p className="mt-0.5 font-mono text-sm">
                        {conductor.licencia ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Categoría</p>
                    <p className="mt-0.5 text-sm">
                        {conductor.categoria_licencia ?? '—'}
                    </p>
                </div>
                <div className="col-span-2">
                    <p className="text-xs text-muted-foreground">
                        Revalidación
                    </p>
                    <p
                        className={cn(
                            'mt-0.5 text-sm tabular-nums',
                            vencida && 'text-red-700 dark:text-red-400',
                        )}
                    >
                        {conductor.licencia_vence
                            ? formatearFecha(conductor.licencia_vence)
                            : '—'}
                    </p>
                </div>
            </div>
        </section>
    );
}
