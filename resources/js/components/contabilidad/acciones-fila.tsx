import { Eye, Link2Off, Trash2 } from 'lucide-react';
import {
    desvincular,
    destroy,
} from '@/actions/App/Http/Controllers/FacturaController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import { Button } from '@/components/ui/button';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { formatearFecha } from '@/lib/format';
import type { ViajeContable } from '@/types/contabilidad';

/**
 * Ver el PDF de la GR, sacar este viaje de su factura, o anular la factura
 * entera. El visor va acá y no en `/viajes` solamente porque es contra ese
 * documento que se factura: quien cobra tiene que poder mirarlo sin cambiar de
 * pantalla.
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
            <DocumentoVisorDialog
                url={viaje.archivo_url ?? ''}
                esPdf
                titulo={`GR ${viaje.numero_gr}`}
                detalle={`${viaje.cliente} · ${formatearFecha(viaje.fecha_traslado)}`}
                trigger={
                    <Button
                        variant="ghost"
                        size="icon"
                        disabled={!viaje.archivo_url}
                        className="size-8 text-muted-foreground"
                        aria-label="Vista rápida de la GR"
                    >
                        <Eye className="size-4" />
                    </Button>
                }
            />

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
