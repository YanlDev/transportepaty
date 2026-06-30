import { router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Image as ImageIcon,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { destroy as destroyFoto } from '@/actions/App/Http/Controllers/VehiculoFotoController';
import { AgregarFotoDialog } from '@/components/vehiculos/agregar-foto-dialog';
import { cn } from '@/lib/utils';
import type { EnumOption, VehiculoFotoItem } from '@/types/fleet';

const MAX_THUMBS = 5;

type Props = {
    vehiculoId: number;
    fotos: VehiculoFotoItem[];
    posiciones: EnumOption[];
    puedeGestionar: boolean;
};

export function FotosCarrusel({
    vehiculoId,
    fotos,
    posiciones,
    puedeGestionar,
}: Props) {
    const [indice, setIndice] = useState(0);
    const actual = Math.min(indice, Math.max(fotos.length - 1, 0));
    const foto = fotos[actual];

    const ir = (siguiente: number) => {
        if (fotos.length === 0) {
            return;
        }

        setIndice((siguiente + fotos.length) % fotos.length);
    };

    const eliminar = (id: number) => {
        router.delete(destroyFoto([vehiculoId, id]).url, {
            preserveScroll: true,
            onSuccess: () => setIndice(0),
        });
    };

    const agregarTrigger = (
        <button
            type="button"
            className="grid size-8 place-items-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
            aria-label="Agregar foto"
        >
            <Plus className="size-4" />
        </button>
    );

    if (!foto) {
        return (
            <section className="flex h-full min-h-[280px] flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border bg-card p-6 text-center text-muted-foreground">
                <div className="grid size-12 place-items-center rounded-full bg-muted">
                    <ImageIcon className="size-6" />
                </div>
                <p className="text-sm">Aún no hay fotos del vehículo.</p>
                {puedeGestionar && (
                    <AgregarFotoDialog
                        vehiculoId={vehiculoId}
                        posiciones={posiciones}
                    />
                )}
            </section>
        );
    }

    const thumbs = fotos.slice(0, MAX_THUMBS);
    const restantes = fotos.length - MAX_THUMBS;

    return (
        <section className="group relative h-full min-h-[280px] overflow-hidden rounded-xl border border-border bg-zinc-100">
            <img
                src={foto.url}
                alt={`Foto ${actual + 1}`}
                loading="lazy"
                className="absolute inset-0 size-full object-cover"
            />

            {/* Contador */}
            <span className="absolute top-3 left-3 rounded-full bg-black/60 px-2.5 py-0.5 text-xs font-medium text-white">
                {actual + 1} / {fotos.length}
            </span>

            {/* Acciones */}
            {puedeGestionar && (
                <div className="absolute top-3 right-3 flex items-center gap-2">
                    <AgregarFotoDialog
                        vehiculoId={vehiculoId}
                        posiciones={posiciones}
                        trigger={agregarTrigger}
                    />
                    <button
                        type="button"
                        onClick={() => eliminar(foto.id)}
                        className="grid size-8 place-items-center rounded-full bg-black/50 text-white transition-colors hover:bg-red-600"
                        aria-label="Eliminar foto"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </div>
            )}

            {/* Flechas */}
            {fotos.length > 1 && (
                <>
                    <CarruselBoton
                        lado="izquierda"
                        onClick={() => ir(actual - 1)}
                    />
                    <CarruselBoton
                        lado="derecha"
                        onClick={() => ir(actual + 1)}
                    />
                </>
            )}

            {/* Degradado para legibilidad de las miniaturas */}
            {fotos.length > 1 && (
                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/55 to-transparent" />
            )}

            {/* Miniaturas dentro de la foto */}
            {fotos.length > 1 && (
                <div className="absolute bottom-3 left-3 flex gap-1.5">
                    {thumbs.map((item, i) => {
                        const esUltima = i === MAX_THUMBS - 1 && restantes > 0;

                        return (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => setIndice(i)}
                                className={cn(
                                    'relative size-12 shrink-0 overflow-hidden rounded-md border-2 transition-colors',
                                    i === actual
                                        ? 'border-white'
                                        : 'border-white/40 hover:border-white',
                                )}
                            >
                                <img
                                    src={item.thumb || item.url}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                                {esUltima && (
                                    <span className="absolute inset-0 grid place-items-center bg-black/60 text-xs font-semibold text-white">
                                        +{restantes + 1}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
        </section>
    );
}

function CarruselBoton({
    lado,
    onClick,
}: {
    lado: 'izquierda' | 'derecha';
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'absolute top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-zinc-700 shadow-md transition-colors hover:bg-white',
                lado === 'izquierda' ? 'left-3' : 'right-3',
            )}
            aria-label={lado === 'izquierda' ? 'Anterior' : 'Siguiente'}
        >
            {lado === 'izquierda' ? (
                <ChevronLeft className="size-5" />
            ) : (
                <ChevronRight className="size-5" />
            )}
        </button>
    );
}
