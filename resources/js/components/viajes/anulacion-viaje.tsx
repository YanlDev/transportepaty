import { router } from '@inertiajs/react';
import { Ban, RotateCcw } from 'lucide-react';
import { reactivar } from '@/actions/App/Http/Controllers/ViajeController';
import { Button } from '@/components/ui/button';
import { AnularViajeDialog } from '@/components/viajes/anular-viaje-dialog';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { AnulacionViaje } from '@/types/fleet';

/**
 * La etiqueta de una GR anulada. Al pasar el mouse dice cuándo, quién y por
 * qué: es lo que explica por qué la fila está en gris y no cuenta.
 */
export function EtiquetaAnulada({ anulacion }: { anulacion: AnulacionViaje }) {
    const detalle = [
        `Anulada el ${formatearFecha(anulacion.fecha)}`,
        anulacion.por && `por ${anulacion.por}`,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <span
            title={
                anulacion.motivo ? `${detalle}. ${anulacion.motivo}` : detalle
            }
            className="inline-flex items-center gap-1 rounded-full border border-muted-foreground/30 bg-muted px-1.5 py-0.5 font-sans text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
        >
            <Ban className="size-3" />
            Anulada
        </span>
    );
}

/**
 * Anular una GR vigente o reactivar una anulada, según cómo esté. Reactivar
 * no pide confirmación: solo devuelve la GR a como estaba.
 */
export function AccionAnulacion({
    viaje,
    grande = false,
}: {
    viaje: { id: number; numero_gr: string; anulacion?: AnulacionViaje | null };
    grande?: boolean;
}) {
    const tamano = grande ? 'size-11' : 'size-8';
    const icono = grande ? 'size-5' : 'size-4';

    if (viaje.anulacion) {
        return (
            <Button
                variant="ghost"
                size="icon"
                className={cn(tamano, 'text-muted-foreground')}
                title="Reactivar la GR: vuelve a contar como viaje"
                aria-label={`Reactivar la GR ${viaje.numero_gr}`}
                onClick={() =>
                    router.delete(reactivar(viaje.id).url, {
                        preserveScroll: true,
                    })
                }
            >
                <RotateCcw className={icono} />
            </Button>
        );
    }

    return (
        <AnularViajeDialog
            viaje={viaje}
            trigger={
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        tamano,
                        'text-muted-foreground hover:text-amber-600',
                    )}
                    title="Marcar como anulada"
                    aria-label={`Anular la GR ${viaje.numero_gr}`}
                >
                    <Ban className={icono} />
                </Button>
            }
        />
    );
}
