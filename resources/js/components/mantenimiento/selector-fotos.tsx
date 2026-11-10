import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { CampoArchivo } from '@/components/campo-archivo';
import { useConfirmar } from '@/components/confirmar';
import { Button } from '@/components/ui/button';
import type { FotoOrden } from '@/types/mantenimiento';

type Props = {
    titulo: string;
    /** Las nuevas, todavía sin subir: viajan con el formulario. */
    nuevas: File[];
    onNuevas: (fotos: File[]) => void;
    /** Las ya guardadas en la orden, con su ruta para quitarlas. */
    guardadas?: (FotoOrden & { urlBorrar: string })[];
    /** Porcentaje de la subida en curso. */
    progreso?: number | null;
    error?: string;
    /** Avisa mientras se comprimen, para bloquear el botón de guardar. */
    onPreparando?: (preparando: boolean) => void;
};

/**
 * Un grupo del panel fotográfico (antes o después): las fotos que la orden
 * ya tiene guardadas y, debajo, el campo compartido para sumar nuevas.
 * «Tomar foto» abre la cámara de atrás del celular; «Galería» deja elegir
 * varias que ya estaban en el teléfono. Todas se achican antes de sumarse
 * al formulario.
 */
export function SelectorFotos({
    titulo,
    nuevas,
    onNuevas,
    guardadas = [],
    progreso = null,
    error,
    onPreparando,
}: Props) {
    const confirmar = useConfirmar();

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">{titulo}</span>

            {guardadas.length > 0 && (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {guardadas.map((foto) => (
                        <div
                            key={foto.id}
                            className="relative aspect-square overflow-hidden rounded-md border"
                        >
                            <a href={foto.url} target="_blank" rel="noreferrer">
                                <img
                                    src={foto.url}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            </a>
                            <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                className="absolute top-1 right-1 size-7"
                                // La guardada se borra del servidor en el
                                // acto: se pregunta antes, a diferencia de
                                // una nueva.
                                onClick={async () => {
                                    if (
                                        await confirmar({
                                            titulo: '¿Quitar esta foto de la orden?',
                                            descripcion:
                                                'Se borra en el acto y no se puede recuperar.',
                                            accion: 'Quitar',
                                        })
                                    ) {
                                        router.delete(foto.urlBorrar, {
                                            preserveScroll: true,
                                            preserveState: true,
                                        });
                                    }
                                }}
                                aria-label="Quitar la foto"
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}

            <CampoArchivo
                acepta="imagen"
                multiple
                archivos={nuevas}
                onArchivos={onNuevas}
                progreso={progreso}
                error={error}
                onPreparando={onPreparando}
            />
        </div>
    );
}
