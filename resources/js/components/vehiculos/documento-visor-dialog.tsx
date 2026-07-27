import { Download, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatearFecha } from '@/lib/format';
import type { VehiculoDocumentoItem } from '@/types/fleet';

type Props = {
    documento: VehiculoDocumentoItem;
    trigger: React.ReactNode;
};

/**
 * Muestra el archivo del documento dentro de la misma página: los PDF en un
 * visor embebido y las fotos como imagen. Abrir en pestaña nueva y descargar
 * quedan como acciones secundarias.
 */
export function DocumentoVisorDialog({ documento, trigger }: Props) {
    const subtitulo = [
        documento.numero,
        documento.fecha_vencimiento
            ? `Vence: ${formatearFecha(documento.fecha_vencimiento)}`
            : 'Sin vencimiento',
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="flex h-[90vh] w-[95vw] max-w-5xl flex-col gap-4 sm:max-w-5xl">
                <DialogHeader className="pr-10">
                    <DialogTitle>{documento.tipo_label}</DialogTitle>
                    <p className="text-sm text-muted-foreground">{subtitulo}</p>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-hidden rounded-lg border bg-muted">
                    {documento.url === '' ? (
                        <div className="grid h-full place-items-center p-6 text-center text-sm text-muted-foreground">
                            Este documento no tiene archivo adjunto.
                        </div>
                    ) : documento.es_pdf ? (
                        <object
                            data={`${documento.url}#toolbar=1&navpanes=0&view=FitH`}
                            type="application/pdf"
                            className="size-full"
                            aria-label={`Documento ${documento.tipo_label}`}
                        >
                            <div className="grid h-full place-items-center p-6 text-center text-sm text-muted-foreground">
                                Tu navegador no puede mostrar el PDF aquí.
                                <br />
                                Usa &quot;Abrir en pestaña&quot; o descárgalo.
                            </div>
                        </object>
                    ) : (
                        <div className="grid h-full place-items-center overflow-auto p-2">
                            <img
                                src={documento.url}
                                alt={`Documento ${documento.tipo_label}`}
                                className="max-h-full max-w-full object-contain"
                            />
                        </div>
                    )}
                </div>

                {documento.url !== '' && (
                    <div className="flex shrink-0 justify-end gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={documento.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink className="size-4" />
                                Abrir en pestaña
                            </a>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a href={documento.url} download>
                                <Download className="size-4" />
                                Descargar
                            </a>
                        </Button>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
