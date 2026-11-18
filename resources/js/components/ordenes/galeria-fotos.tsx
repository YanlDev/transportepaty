import type { FotoOrden } from '@/types/mantenimiento';

/**
 * Un grupo de fotos de la orden (antes o después), solo para mirar: tocar
 * una la abre entera.
 */
export function GaleriaFotos({
    titulo,
    fotos,
}: {
    titulo: string;
    fotos: FotoOrden[];
}) {
    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">
                {titulo}
                <span className="ml-2 text-xs font-normal text-muted-foreground tabular-nums">
                    {fotos.length}
                </span>
            </span>
            {fotos.length === 0 ? (
                <p className="rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">
                    Sin fotos
                </p>
            ) : (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {fotos.map((foto) => (
                        <a
                            key={foto.id}
                            href={foto.url}
                            target="_blank"
                            rel="noreferrer"
                            className="aspect-square overflow-hidden rounded-md border"
                        >
                            <img
                                src={foto.url}
                                alt={`Foto de ${titulo.toLowerCase()}`}
                                loading="lazy"
                                className="size-full object-cover"
                            />
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
