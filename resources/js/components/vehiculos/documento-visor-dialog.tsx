import { Download, ExternalLink, X } from 'lucide-react';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatearFecha } from '@/lib/format';
import type { VehiculoDocumentoItem } from '@/types/fleet';

type Props = {
    documento: VehiculoDocumentoItem;
    titulo: string;
    trigger: React.ReactNode;
};

/**
 * Visor en ventana contenida, con la proporción de una hoja vertical, que es el
 * formato de casi todos estos papeles. Deja ver la página detrás para no perder
 * el contexto; para un escaneo apaisado o un plano grande está «Abrir en pestaña
 * nueva», que sí usa toda la pantalla.
 *
 * Se cierra con Escape, con la X o haciendo clic fuera. Ojo: si haces clic
 * dentro del PDF, el visor del navegador se queda con el teclado y Escape deja
 * de llegar; un clic en la barra devuelve el control.
 */
export function DocumentoVisorDialog({ documento, titulo, trigger }: Props) {
    const detalle = [
        documento.numero,
        documento.fecha_vencimiento
            ? `Vence ${formatearFecha(documento.fecha_vencimiento)}`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent
                showCloseButton={false}
                className="flex h-[85vh] max-h-[820px] w-[92vw] flex-col gap-0 overflow-hidden p-0 shadow-2xl sm:max-w-2xl"
            >
                <div className="flex h-11 shrink-0 items-center gap-3 border-b bg-background px-3">
                    <DialogTitle className="truncate text-sm font-medium">
                        {titulo}
                    </DialogTitle>

                    {detalle && (
                        <span className="truncate text-xs text-muted-foreground">
                            {detalle}
                        </span>
                    )}

                    <div className="ml-auto flex shrink-0 items-center">
                        {documento.url !== '' && (
                            <>
                                <AccionBarra
                                    href={documento.url}
                                    etiqueta="Abrir en pestaña nueva"
                                    externa
                                >
                                    <ExternalLink className="size-4" />
                                </AccionBarra>
                                <AccionBarra
                                    href={documento.url}
                                    etiqueta="Descargar"
                                    descargar
                                >
                                    <Download className="size-4" />
                                </AccionBarra>
                            </>
                        )}
                        <CerrarVisor />
                    </div>
                </div>

                <div className="min-h-0 flex-1 bg-muted">
                    {documento.url === '' ? (
                        <div className="grid h-full place-items-center p-6 text-center text-sm text-muted-foreground">
                            Este documento no tiene archivo adjunto.
                        </div>
                    ) : documento.es_pdf ? (
                        <object
                            data={`${documento.url}#toolbar=1&navpanes=0&view=FitH`}
                            type="application/pdf"
                            className="size-full"
                            aria-label={titulo}
                        >
                            <div className="grid h-full place-items-center p-6 text-center text-sm text-muted-foreground">
                                Tu navegador no puede mostrar el PDF aquí. Usa
                                «Abrir en pestaña nueva» o descárgalo.
                            </div>
                        </object>
                    ) : (
                        <div className="grid h-full place-items-center overflow-auto">
                            <img
                                src={documento.url}
                                alt={titulo}
                                className="max-h-full max-w-full object-contain"
                            />
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function CerrarVisor() {
    return (
        <DialogClose
            aria-label="Cerrar"
            title="Cerrar (Esc)"
            className="grid size-8 place-items-center text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
            <X className="size-4" />
        </DialogClose>
    );
}

function AccionBarra({
    href,
    etiqueta,
    externa,
    descargar,
    children,
}: {
    href: string;
    etiqueta: string;
    externa?: boolean;
    descargar?: boolean;
    children: React.ReactNode;
}) {
    return (
        <a
            href={href}
            title={etiqueta}
            aria-label={etiqueta}
            {...(externa ? { target: '_blank', rel: 'noreferrer' } : {})}
            {...(descargar ? { download: true } : {})}
            className="grid size-8 place-items-center text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
            {children}
        </a>
    );
}
