import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { ViajeContable } from '@/types/contabilidad';

/**
 * Baja el PDF de la guía de un clic, sin abrir visor: quien cobra la necesita
 * como archivo, para adjuntarla a la factura o mandarla al cliente.
 *
 * Va pegado al N° GR —es el documento de ese número— y no entre las acciones
 * del final: bajar la GR es leer el viaje, no tocar la factura. El archivo
 * conserva el nombre con que llegó de SUNAT, el mismo de las carpetas.
 */
export function DescargarGuia({ viaje }: { viaje: ViajeContable }) {
    const etiqueta = `Descargar la GR ${viaje.numero_gr}`;

    if (!viaje.archivo_url) {
        return (
            <Button
                variant="ghost"
                size="icon"
                disabled
                className="size-7 text-muted-foreground"
                aria-label={`La GR ${viaje.numero_gr} no tiene PDF`}
            >
                <Download className="size-4" />
            </Button>
        );
    }

    return (
        <Button
            asChild
            variant="ghost"
            size="icon"
            className="size-7 text-muted-foreground hover:text-foreground"
        >
            <a
                href={viaje.archivo_url}
                download
                title={etiqueta}
                aria-label={etiqueta}
            >
                <Download className="size-4" />
            </a>
        </Button>
    );
}
