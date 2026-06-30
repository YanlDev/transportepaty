import { ImageIcon, Pencil, Trash2, Wrench } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatearFecha, formatearSoles } from '@/lib/format';
import type { Mantenimiento } from '@/types/fleet';

type Props = {
    mantenimientos: Mantenimiento[];
    puedeGestionar: boolean;
    onEditar: (m: Mantenimiento) => void;
    onEliminar: (m: Mantenimiento) => void;
};

export function TablaMantenimientos({
    mantenimientos,
    puedeGestionar,
    onEditar,
    onEliminar,
}: Props) {
    return (
        <ul className="flex flex-col gap-3">
            {mantenimientos.map((m) => (
                <li
                    key={m.id}
                    className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="flex min-w-0 flex-1 items-start gap-3">
                        <MiniaturaFotos fotos={m.fotos} />

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium text-foreground">
                                    {formatearFecha(m.fecha_realizado)}
                                </span>
                                <span className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">
                                    {m.odometro.toLocaleString('es-PE')} km
                                </span>
                            </div>

                            <div className="mt-1 flex flex-wrap gap-1.5">
                                {m.items.map((item) => (
                                    <span
                                        key={item.id}
                                        className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                                    >
                                        {item.nombre}
                                        {item.costo !== null && (
                                            <span className="font-medium text-foreground">
                                                {formatearSoles(item.costo)}
                                            </span>
                                        )}
                                    </span>
                                ))}
                            </div>

                            <div className="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                {m.proveedor && <span>{m.proveedor}</span>}
                                {m.factura_numero && (
                                    <span>Fact. {m.factura_numero}</span>
                                )}
                                {m.costo_total !== null && (
                                    <span className="font-medium text-foreground">
                                        Total: {formatearSoles(m.costo_total)}
                                    </span>
                                )}
                            </div>

                            {m.observaciones && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {m.observaciones}
                                </p>
                            )}
                        </div>
                    </div>

                    {puedeGestionar && (
                        <div className="flex shrink-0 items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onEditar(m)}
                            >
                                <Pencil className="size-4" />
                                Editar
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-9 text-destructive hover:text-destructive"
                                title="Eliminar"
                                onClick={() => onEliminar(m)}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    )}
                </li>
            ))}
        </ul>
    );
}

function MiniaturaFotos({
    fotos,
}: {
    fotos: { url: string; thumb: string }[];
}) {
    if (fotos.length === 0) {
        return (
            <span className="grid size-12 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
                <Wrench className="size-4" />
            </span>
        );
    }

    return (
        <div className="flex shrink-0 gap-1.5">
            {fotos.slice(0, 2).map((foto, i) => (
                <a
                    key={i}
                    href={foto.url}
                    target="_blank"
                    rel="noreferrer"
                    className="size-12 overflow-hidden rounded-lg border border-border"
                >
                    <img
                        src={foto.thumb || foto.url}
                        alt={`Foto ${i + 1}`}
                        className="size-full object-cover"
                    />
                </a>
            ))}
            {fotos.length > 2 && (
                <span className="grid size-12 place-items-center rounded-lg bg-muted text-xs text-muted-foreground">
                    <ImageIcon className="size-4" />
                </span>
            )}
        </div>
    );
}
