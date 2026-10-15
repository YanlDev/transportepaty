import { router } from '@inertiajs/react';
import { Camera, ImagePlus, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { comprimirImagen } from '@/lib/comprimir-imagen';
import type { FotoOrden } from '@/types/mantenimiento';

type Props = {
    titulo: string;
    /** Las nuevas, todavía sin subir: viajan con el formulario. */
    nuevas: File[];
    onNuevas: (fotos: File[]) => void;
    /** Las ya guardadas en la orden, con su ruta para quitarlas. */
    guardadas?: (FotoOrden & { urlBorrar: string })[];
    error?: string;
};

/**
 * Un grupo del panel fotográfico (antes o después). «Tomar foto» abre la
 * cámara de atrás del celular; «Galería» deja elegir varias que ya estaban
 * en el teléfono. Todas se achican antes de sumarse al formulario.
 */
export function SelectorFotos({
    titulo,
    nuevas,
    onNuevas,
    guardadas = [],
    error,
}: Props) {
    const camara = useRef<HTMLInputElement>(null);
    const galeria = useRef<HTMLInputElement>(null);
    const [comprimiendo, setComprimiendo] = useState(false);

    const vistas = useMemo(
        () => nuevas.map((foto) => URL.createObjectURL(foto)),
        [nuevas],
    );

    useEffect(
        () => () => vistas.forEach((url) => URL.revokeObjectURL(url)),
        [vistas],
    );

    const sumar = async (lista: FileList | null) => {
        if (!lista || lista.length === 0) {
            return;
        }

        setComprimiendo(true);
        const comprimidas = await Promise.all(
            Array.from(lista).map((foto) => comprimirImagen(foto)),
        );
        setComprimiendo(false);

        onNuevas([...nuevas, ...comprimidas]);

        for (const entrada of [camara.current, galeria.current]) {
            if (entrada) {
                entrada.value = '';
            }
        }
    };

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">{titulo}</span>

            {(guardadas.length > 0 || nuevas.length > 0) && (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {guardadas.map((foto) => (
                        <Miniatura
                            key={`g-${foto.id}`}
                            url={foto.url}
                            // La guardada se borra del servidor en el acto:
                            // se pregunta antes, a diferencia de una nueva.
                            onQuitar={() => {
                                if (
                                    window.confirm(
                                        '¿Quitar esta foto de la orden?',
                                    )
                                ) {
                                    router.delete(foto.urlBorrar, {
                                        preserveScroll: true,
                                        preserveState: true,
                                    });
                                }
                            }}
                        />
                    ))}
                    {vistas.map((url, indice) => (
                        <Miniatura
                            key={url}
                            url={url}
                            nueva
                            onQuitar={() =>
                                onNuevas(nuevas.filter((_, i) => i !== indice))
                            }
                        />
                    ))}
                </div>
            )}

            <input
                ref={camara}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={(evento) => sumar(evento.target.files)}
            />
            <input
                ref={galeria}
                type="file"
                accept="image/*"
                multiple
                className="hidden"
                onChange={(evento) => sumar(evento.target.files)}
            />

            <div className="grid grid-cols-2 gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="h-12"
                    onClick={() => camara.current?.click()}
                    disabled={comprimiendo}
                >
                    {comprimiendo ? <Spinner /> : <Camera className="size-5" />}
                    Tomar foto
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="h-12"
                    onClick={() => galeria.current?.click()}
                    disabled={comprimiendo}
                >
                    <ImagePlus className="size-5" />
                    Galería
                </Button>
            </div>

            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

function Miniatura({
    url,
    nueva = false,
    onQuitar,
}: {
    url: string;
    nueva?: boolean;
    onQuitar: () => void;
}) {
    return (
        <div className="relative aspect-square overflow-hidden rounded-md border">
            <a href={url} target="_blank" rel="noreferrer">
                <img src={url} alt="" className="size-full object-cover" />
            </a>
            {nueva && (
                <span className="absolute bottom-1 left-1 rounded bg-primary px-1.5 text-[10px] font-medium text-primary-foreground">
                    nueva
                </span>
            )}
            <Button
                type="button"
                size="icon"
                variant="secondary"
                className="absolute top-1 right-1 size-7"
                onClick={onQuitar}
                aria-label="Quitar la foto"
            >
                <X className="size-4" />
            </Button>
        </div>
    );
}
