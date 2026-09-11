import { Link2Off, Trash2 } from 'lucide-react';
import {
    desvincular,
    destroy,
} from '@/actions/App/Http/Controllers/FacturaController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import { Button } from '@/components/ui/button';
import type { ViajeContable } from '@/types/contabilidad';

/**
 * Lo que se le puede hacer a la factura de esta fila: sacarle este viaje, o
 * anularla entera. Ver la GR no está acá sino junto al peso (`VerGuia`),
 * porque es leer la operación y no tocar la cobranza.
 *
 * Queda vacío cuando el viaje no se facturó todavía o el usuario no factura.
 */
export function AccionesFila({
    viaje,
    puedeFacturar,
}: {
    viaje: ViajeContable;
    puedeFacturar: boolean;
}) {
    const factura = viaje.factura;

    return (
        <div className="flex items-center justify-end gap-1">
            {/* Desvincular solo tiene sentido en una factura de varias GR: si
                cobra una sola, sacarla la dejaría vacía y eso es anularla. */}
            {factura !== null && puedeFacturar && factura.viajes_count > 1 && (
                <ConfirmarBorradoDialog
                    url={desvincular(viaje.id).url}
                    titulo="Sacar el viaje de la factura"
                    etiquetaAccion="Sacar de la factura"
                    descripcion={
                        <>
                            La GR <Resaltado>{viaje.numero_gr}</Resaltado> deja
                            de estar cubierta por la factura{' '}
                            <Resaltado>{factura.numero}</Resaltado> y vuelve a
                            quedar sin facturar. La factura sigue existiendo con
                            los otros viajes.
                        </>
                    }
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground"
                            aria-label={`Sacar la GR ${viaje.numero_gr} de la factura ${factura.numero}`}
                            title="Sacar este viaje de la factura"
                        >
                            <Link2Off className="size-4" />
                        </Button>
                    }
                />
            )}

            {factura !== null && puedeFacturar && (
                <ConfirmarBorradoDialog
                    url={destroy(factura.id).url}
                    titulo="Anular la factura"
                    etiquetaAccion="Anular"
                    descripcion={
                        <>
                            Se anula la factura{' '}
                            <Resaltado>{factura.numero}</Resaltado> y sus{' '}
                            {factura.viajes_count}{' '}
                            {factura.viajes_count === 1 ? 'viaje' : 'viajes'}{' '}
                            vuelven a quedar sin facturar.
                        </>
                    }
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-destructive"
                            aria-label={`Anular la factura ${factura.numero}`}
                            title="Anular la factura"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    }
                />
            )}
        </div>
    );
}
