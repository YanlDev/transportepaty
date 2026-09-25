import { router } from '@inertiajs/react';
import { WhatsappLogo } from '@phosphor-icons/react';
import { useState } from 'react';
import {
    enviar,
    imagen,
} from '@/actions/App/Http/Controllers/AvisoSalidaController';
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
import type { TipoAvisoSalida } from '@/types/programacion';

export type EnvioPendiente = {
    tipo: TipoAvisoSalida;
    /** A quién va: «Conductor», «Abastecimiento»… */
    etiqueta: string;
    numero: string;
};

/**
 * La imagen del aviso tal como va a llegar, con el botón para mandarla por
 * el número de la empresa. Se mira antes de enviar porque, una vez que sale,
 * no hay cómo corregirla.
 */
export function EnviarAvisoDialog({
    programacionId,
    envio,
    onCerrar,
}: {
    programacionId: number;
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
            enviar([programacionId, envio.tipo]).url,
            { numero: envio.numero },
            {
                preserveScroll: true,
                onStart: () => setEnviando(true),
                onFinish: () => setEnviando(false),
                onSuccess: onCerrar,
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
                            src={imagen([programacionId, envio.tipo]).url}
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
