import { router } from '@inertiajs/react';
import { Ban, Link2Off, Receipt, Trash2 } from 'lucide-react';
import { marcarNoFacturable } from '@/actions/App/Http/Controllers/ContabilidadController';
import {
    desvincular,
    destroy,
} from '@/actions/App/Http/Controllers/FacturaController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import { NoFacturableDialog } from '@/components/contabilidad/no-facturable-dialog';
import { Button } from '@/components/ui/button';
import { avisarError } from '@/lib/aviso-error';
import type { FacturaResumen, ViajeContable } from '@/types/contabilidad';

/**
 * Lo que se le puede hacer a una factura del viaje: sacarle este viaje, o
 * anularla entera. Ver la GR no está acá sino junto al peso (`VerGuia`),
 * porque es leer la operación y no tocar la cobranza.
 *
 * En un viaje sin factura, lo que queda es decidir no cobrarlo (o deshacer
 * esa decisión). Vacío cuando el usuario no factura.
 */
export function AccionesFila({
    viaje,
    factura,
    puedeFacturar,
}: {
    viaje: ViajeContable;
    factura: FacturaResumen | null;
    puedeFacturar: boolean;
}) {
    if (factura === null) {
        return puedeFacturar ? <AccionesSinFactura viaje={viaje} /> : null;
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {/* Desvincular solo tiene sentido en una factura de varias GR: si
                cobra una sola, sacarla la dejaría vacía y eso es anularla. */}
            {puedeFacturar && factura.viajes_count > 1 && (
                <ConfirmarBorradoDialog
                    url={desvincular([factura.id, viaje.id]).url}
                    titulo="Sacar el viaje de la factura"
                    etiquetaAccion="Sacar de la factura"
                    descripcion={
                        <>
                            La GR <Resaltado>{viaje.numero_gr}</Resaltado> deja
                            de estar cubierta por la factura{' '}
                            <Resaltado>{factura.numero}</Resaltado>. La factura
                            sigue existiendo con los otros viajes.
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

            {puedeFacturar && (
                <ConfirmarBorradoDialog
                    url={destroy(factura.id).url}
                    titulo="Anular la factura"
                    etiquetaAccion="Anular"
                    descripcion={
                        <>
                            Se anula la factura{' '}
                            <Resaltado>{factura.numero}</Resaltado> y sale de
                            sus {factura.viajes_count}{' '}
                            {factura.viajes_count === 1 ? 'viaje' : 'viajes'}.
                            Las otras facturas de esos viajes no se tocan.
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

/**
 * Marcar la GR como «no se factura», o devolverla a la cobranza. Devolverla
 * no pide confirmación: no borra nada, solo la pone otra vez por facturar.
 */
function AccionesSinFactura({ viaje }: { viaje: ViajeContable }) {
    if (viaje.estado === 'no_facturable') {
        return (
            <div className="flex items-center justify-end">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-muted-foreground"
                    aria-label={`Volver a facturar la GR ${viaje.numero_gr}`}
                    title="Volver a ponerla por facturar"
                    onClick={() =>
                        router.patch(
                            marcarNoFacturable(viaje.id).url,
                            { no_facturable: false },
                            { preserveScroll: true, onError: avisarError },
                        )
                    }
                >
                    <Receipt className="size-4" />
                </Button>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-end">
            <NoFacturableDialog
                viaje={viaje}
                trigger={
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 text-muted-foreground"
                        aria-label={`No facturar la GR ${viaje.numero_gr}`}
                        title="No se factura"
                    >
                        <Ban className="size-4" />
                    </Button>
                }
            />
        </div>
    );
}
