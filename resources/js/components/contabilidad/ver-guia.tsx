import { Eye } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DocumentoVisorDialog } from '@/components/vehiculos/documento-visor-dialog';
import { formatearFecha } from '@/lib/format';
import type { ViajeContable } from '@/types/contabilidad';

/**
 * Abre el PDF de la guía sin salir de la cobranza: es contra ese documento que
 * se factura, así que quien cobra tiene que poder mirarlo mientras llena la
 * fila.
 *
 * Va pegado al peso, cerrando el bloque de la operación, y no entre las
 * acciones del final: mirar la GR es leer el viaje, no tocar la factura.
 */
export function VerGuia({ viaje }: { viaje: ViajeContable }) {
    return (
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
                    aria-label={`Vista rápida de la GR ${viaje.numero_gr}`}
                >
                    <Eye className="size-4" />
                </Button>
            }
        />
    );
}
