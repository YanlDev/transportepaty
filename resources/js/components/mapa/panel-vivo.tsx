import { RefreshCw, Route } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { estadoGpsInfo } from '@/types/fleet';
import type { MarcadorVehiculo } from '@/types/fleet';

type Props = {
    marcadores: MarcadorVehiculo[];
    seleccionados: Set<number>;
    onToggle: (id: number) => void;
    onTodos: () => void;
    onNinguno: () => void;
    onRecorrido: (id: number) => void;
};

export function PanelVivo({
    marcadores,
    seleccionados,
    onToggle,
    onTodos,
    onNinguno,
    onRecorrido,
}: Props) {
    return (
        <>
            <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <span className="text-sm font-semibold text-foreground">
                    Vehículos
                </span>
                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <RefreshCw className="size-3 animate-spin [animation-duration:3s]" />
                    Auto
                </span>
            </div>

            <div className="flex gap-2 px-4 py-2.5">
                <Button
                    variant="outline"
                    size="sm"
                    className="h-7 flex-1 text-xs"
                    onClick={onTodos}
                >
                    Todos
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-7 flex-1 text-xs"
                    onClick={onNinguno}
                >
                    Ninguno
                </Button>
            </div>

            <ul className="max-h-72 flex-1 overflow-y-auto px-2 pb-2 lg:max-h-none">
                {marcadores.map((m) => {
                    const info = estadoGpsInfo(m.estado);

                    return (
                        <li
                            key={m.id}
                            className="flex items-center gap-1 rounded-lg pr-1 hover:bg-muted"
                        >
                            <button
                                type="button"
                                onClick={() => onToggle(m.id)}
                                className="flex flex-1 items-center gap-2.5 px-2 py-2 text-left"
                            >
                                <Checkbox
                                    checked={seleccionados.has(m.id)}
                                    tabIndex={-1}
                                />
                                <span
                                    className="size-2.5 shrink-0 rounded-full"
                                    style={{ backgroundColor: info.color }}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-mono text-sm font-medium text-foreground">
                                        {m.placa}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {info.label} · {m.velocidad} km/h
                                    </span>
                                </span>
                            </button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0 text-muted-foreground"
                                title="Ver recorrido"
                                onClick={() => onRecorrido(m.id)}
                            >
                                <Route className="size-4" />
                            </Button>
                        </li>
                    );
                })}
            </ul>

            <div className="flex flex-wrap gap-x-3 gap-y-1 border-t border-border px-4 py-3 text-xs text-muted-foreground">
                {['en_movimiento', 'detenido', 'apagado'].map((estado) => {
                    const info = estadoGpsInfo(estado);

                    return (
                        <span
                            key={estado}
                            className="inline-flex items-center gap-1"
                        >
                            <span
                                className="size-2.5 rounded-full"
                                style={{ backgroundColor: info.color }}
                            />
                            {info.label}
                        </span>
                    );
                })}
            </div>
        </>
    );
}
