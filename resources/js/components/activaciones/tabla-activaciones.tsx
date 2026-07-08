import { Power, Trash2, User } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatearFechaHora } from '@/lib/format';
import { cn } from '@/lib/utils';
import { resultadoActivacionInfo } from '@/types/fleet';
import type { Activacion } from '@/types/fleet';

type Props = {
    activaciones: Activacion[];
    puedeGestionar: boolean;
    onEliminar: (activacion: Activacion) => void;
};

export function TablaActivaciones({
    activaciones,
    puedeGestionar,
    onEliminar,
}: Props) {
    return (
        <ul className="flex flex-col gap-3">
            {activaciones.map((activacion) => {
                const resultado = resultadoActivacionInfo(activacion.resultado);

                return (
                    <li
                        key={activacion.id}
                        className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div className="flex min-w-0 flex-1 items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <Power className="size-5" />
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-medium text-foreground">
                                        {formatearFechaHora(activacion.fecha)}
                                    </span>
                                    <span
                                        className={cn(
                                            'rounded-full border px-2 py-0.5 text-xs font-medium',
                                            resultado.badgeClass,
                                        )}
                                    >
                                        {resultado.label}
                                    </span>
                                </div>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    {activacion.kilometraje !== null
                                        ? `${activacion.kilometraje.toLocaleString('es-PE')} km`
                                        : 'Sin odómetro'}
                                </p>

                                {activacion.observaciones && (
                                    <p className="mt-1 text-sm whitespace-pre-line text-muted-foreground">
                                        {activacion.observaciones}
                                    </p>
                                )}

                                <p className="mt-1 flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                    <User className="size-3" />
                                    {activacion.conductor ??
                                        activacion.registrada_por ??
                                        'Responsable no registrado'}
                                </p>
                            </div>
                        </div>

                        {puedeGestionar && (
                            <div className="flex shrink-0 items-center gap-2 self-end sm:self-auto">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 text-muted-foreground hover:text-destructive"
                                    title="Eliminar activación"
                                    onClick={() => onEliminar(activacion)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
