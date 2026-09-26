import { router } from '@inertiajs/react';
import { WhatsappLogo } from '@phosphor-icons/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

export type EnvioPendiente = {
    /** A quién va: «Conductor», «Abastecimiento»… */
    etiqueta: string;
    numero: string;
    /** La vista previa: la misma imagen que se va a mandar. */
    imagenUrl: string;
    enviarUrl: string;
};

/**
 * La imagen del aviso tal como va a llegar, con el botón para mandarla por
 * el número de la empresa. Se mira antes de enviar porque, una vez que sale,
 * no hay cómo corregirla.
 */
export function EnviarAvisoDialog({
    envio,
    onCerrar,
}: {
    envio: EnvioPendiente | null;
    onCerrar: () => void;
}) {
    const [enviando, setEnviando] = useState(false);
    const [cargada, setCargada] = useState(false);

    const mandar = () => {
        if (!envio) {
            return;
        }

        router.post(
            envio.enviarUrl,
            { numero: envio.numero },
            {
                preserveScroll: true,
                onStart: () => setEnviando(true),
                onFinish: () => setEnviando(false),
                onSuccess: () => {
                    setCargada(false);
                    onCerrar();
                },
            },
        );
    };

    return (
        <Dialog
            open={envio !== null}
            onOpenChange={(abierto) => {
                if (!abierto && !enviando) {
                    setCargada(false);
                    onCerrar();
                }
            }}
        >
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Enviar a {envio?.etiqueta}</DialogTitle>
                    <DialogDescription className="font-mono">
                        {envio?.numero}
                    </DialogDescription>
                </DialogHeader>

                {envio && (
                    <div className="relative max-h-[60vh] overflow-y-auto rounded-lg border bg-muted">
                        {!cargada && (
                            <div className="aspect-square w-full animate-pulse bg-muted" />
                        )}
                        <img
                            src={envio.imagenUrl}
                            alt={`Vista previa del aviso a ${envio.etiqueta}`}
                            onLoad={() => setCargada(true)}
                            className={cargada ? 'w-full' : 'hidden'}
                        />
                    </div>
                )}

                <DialogFooter>
                    <Button
                        onClick={mandar}
                        disabled={enviando || !cargada}
                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                        {enviando ? (
                            <Spinner />
                        ) : (
                            <WhatsappLogo weight="fill" className="size-4" />
                        )}
                        Enviar por WhatsApp
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
