import { Plus } from 'lucide-react';
import { cn } from '@/lib/utils';
import type {
    EstadoLlanta,
    PosicionLlanta,
    UmbralesLlanta,
} from '@/types/llantas';

/** El color de la llanta en el esquema, con el semáforo de siempre. */
export const colorLlanta: Record<EstadoLlanta, string> = {
    cambiar: 'border-red-600 bg-red-500 text-white',
    revisar: 'border-amber-500 bg-amber-400 text-amber-950',
    bien: 'border-emerald-600 bg-emerald-500 text-white',
    sin_medir: 'border-zinc-500 bg-zinc-400 text-white dark:bg-zinc-600',
};

export function formatearMm(mm: number | null | undefined): string {
    return mm === null || mm === undefined
        ? '—'
        : `${mm.toLocaleString('es-PE', { maximumFractionDigits: 1 })} mm`;
}

/**
 * La unidad vista desde arriba, con el frente hacia arriba: cada llanta en su
 * posición y con el color de su última medida. Tocar una la elige.
 * La izquierda del dibujo es la izquierda de la unidad.
 */
export function EsquemaLlantas({
    posiciones,
    seleccionada,
    onElegir,
}: {
    posiciones: PosicionLlanta[];
    seleccionada: string | null;
    onElegir: (codigo: string) => void;
}) {
    const ejes = [
        ...new Set(posiciones.filter((p) => p.eje > 0).map((p) => p.eje)),
    ];
    const repuestos = posiciones.filter((p) => p.eje === 0);

    return (
        <div className="flex flex-col items-center gap-4">
            <span className="text-[11px] tracking-wider text-muted-foreground uppercase">
                Frente
            </span>
            <div className="relative flex flex-col gap-6 px-2">
                {/* El chasis, por detrás de los ejes. */}
                <span
                    aria-hidden
                    className="absolute inset-y-[-0.5rem] left-1/2 w-10 -translate-x-1/2 rounded-md bg-muted"
                />
                {ejes.map((eje) => {
                    const delEje = posiciones.filter((p) => p.eje === eje);
                    const mitad = Math.ceil(delEje.length / 2);

                    return (
                        <div
                            key={eje}
                            className="relative flex items-center justify-center gap-14"
                        >
                            <span
                                aria-hidden
                                className="absolute inset-x-6 top-1/2 h-1 -translate-y-1/2 rounded bg-muted-foreground/30"
                            />
                            <div className="relative flex gap-1">
                                {delEje.slice(0, mitad).map((posicion) => (
                                    <Llanta
                                        key={posicion.codigo}
                                        posicion={posicion}
                                        activa={
                                            seleccionada === posicion.codigo
                                        }
                                        onClick={() =>
                                            onElegir(posicion.codigo)
                                        }
                                    />
                                ))}
                            </div>
                            <div className="relative flex gap-1">
                                {delEje.slice(mitad).map((posicion) => (
                                    <Llanta
                                        key={posicion.codigo}
                                        posicion={posicion}
                                        activa={
                                            seleccionada === posicion.codigo
                                        }
                                        onClick={() =>
                                            onElegir(posicion.codigo)
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>

            {repuestos.length > 0 && (
                <div className="flex flex-col items-center gap-1.5">
                    <span className="text-[11px] tracking-wider text-muted-foreground uppercase">
                        Repuesto
                    </span>
                    <div className="flex gap-2">
                        {repuestos.map((posicion) => (
                            <Llanta
                                key={posicion.codigo}
                                posicion={posicion}
                                activa={seleccionada === posicion.codigo}
                                onClick={() => onElegir(posicion.codigo)}
                            />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function Llanta({
    posicion,
    activa,
    onClick,
}: {
    posicion: PosicionLlanta;
    activa: boolean;
    onClick: () => void;
}) {
    const mm = posicion.llanta?.profundidad_mm ?? null;

    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activa}
            aria-label={`Posición ${posicion.codigo}: ${posicion.nombre}, ${posicion.estado_label}`}
            title={posicion.nombre}
            className={cn(
                'flex h-[4.5rem] w-11 flex-col items-center justify-center rounded-md border-2 text-center transition-transform active:scale-95',
                posicion.estado === null
                    ? 'border-dashed border-muted-foreground/40 bg-background text-muted-foreground'
                    : colorLlanta[posicion.estado],
                activa &&
                    'ring-2 ring-primary ring-offset-2 ring-offset-background',
            )}
        >
            <span className="text-sm leading-none font-bold">
                {posicion.codigo}
            </span>
            {posicion.estado === null ? (
                <Plus className="mt-1 size-3.5" />
            ) : (
                <span className="mt-1 text-[10px] leading-none tabular-nums">
                    {mm === null
                        ? 's/m'
                        : mm.toLocaleString('es-PE', {
                              maximumFractionDigits: 1,
                          })}
                </span>
            )}
        </button>
    );
}

/** Qué quiere decir cada color, con los mm de corte. */
export function LeyendaLlantas({ umbrales }: { umbrales: UmbralesLlanta }) {
    const items: { estado: EstadoLlanta; texto: string }[] = [
        { estado: 'bien', texto: `Más de ${formatearMm(umbrales.revisar_mm)}` },
        {
            estado: 'revisar',
            texto: `Hasta ${formatearMm(umbrales.revisar_mm)}`,
        },
        {
            estado: 'cambiar',
            texto: `Hasta ${formatearMm(umbrales.cambiar_mm)}`,
        },
        { estado: 'sin_medir', texto: 'Sin medir' },
    ];

    return (
        <div className="flex flex-wrap justify-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
            {items.map((item) => (
                <span key={item.estado} className="flex items-center gap-1.5">
                    <span
                        className={cn(
                            'size-3 rounded-sm border',
                            colorLlanta[item.estado],
                        )}
                    />
                    {item.texto}
                </span>
            ))}
            <span className="flex items-center gap-1.5">
                <span className="size-3 rounded-sm border border-dashed border-muted-foreground/50" />
                Vacía
            </span>
        </div>
    );
}
