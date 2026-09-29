import { Check, Search } from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import type { Remitente } from '@/components/viajes/emision/tipos';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    remitentes: Remitente[];
    /** El RUC elegido o escrito. */
    ruc: string;
    onRuc: (ruc: string) => void;
};

/**
 * Quién emitió la GR-remitente: se escribe el RUC o parte del nombre y se
 * elige de los que ya aparecen en las GR de Paty, con cuántos viajes tiene y
 * por quién llega (Ajeper «vía Crisar»). Si no está, sirve igual escribir el
 * RUC completo.
 *
 * La lista va en línea, debajo del campo, y no en un desplegable flotante:
 * en el celular el desplegable del Select se salía por los costados de la
 * pantalla con los nombres largos.
 */
export function CampoRemitente({ id, remitentes, ruc, onRuc }: Props) {
    const [texto, setTexto] = useState(ruc);
    const [abierto, setAbierto] = useState(false);

    const elegido = remitentes.find((remitente) => remitente.ruc === ruc);
    const consulta = texto.trim().toLowerCase();
    const sugerencias = remitentes
        .filter(
            (remitente) =>
                consulta === '' ||
                remitente.ruc.includes(consulta) ||
                (remitente.nombre ?? '').toLowerCase().includes(consulta) ||
                (remitente.contratante?.nombre ?? '')
                    .toLowerCase()
                    .includes(consulta),
        )
        .slice(0, 6);

    const escribir = (valor: string) => {
        setTexto(valor);
        setAbierto(true);
        // Un RUC completo escrito a mano vale aunque no esté en la lista.
        const soloDigitos = valor.replace(/\D/g, '');
        onRuc(/^\d{11}$/.test(soloDigitos) ? soloDigitos : '');
    };

    const elegir = (remitente: Remitente) => {
        setTexto(remitente.nombre ?? remitente.ruc);
        setAbierto(false);
        onRuc(remitente.ruc);
    };

    return (
        <div className="grid gap-1.5">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id={id}
                    className="pl-9"
                    placeholder="RUC o nombre del remitente"
                    autoComplete="off"
                    enterKeyHint="next"
                    value={texto}
                    onFocus={() => setAbierto(true)}
                    // Se cierra un instante después: si se cerrara al
                    // perder el foco, el toque en una sugerencia no llegaría.
                    onBlur={() =>
                        window.setTimeout(() => setAbierto(false), 150)
                    }
                    onChange={(e) => escribir(e.target.value)}
                    aria-expanded={abierto}
                    aria-controls={`${id}-sugerencias`}
                />
            </div>

            {elegido && !abierto && (
                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                    <Check className="size-3.5 text-primary" />
                    RUC {elegido.ruc}
                    {elegido.contratante &&
                        ` · suele llegar vía ${elegido.contratante.nombre}`}
                </p>
            )}
            {!elegido && ruc !== '' && !abierto && (
                <p className="text-xs text-muted-foreground">
                    RUC {ruc} (no aparece en las GR anteriores)
                </p>
            )}

            {abierto && sugerencias.length > 0 && (
                <ul
                    id={`${id}-sugerencias`}
                    className="overflow-hidden rounded-lg border bg-card"
                >
                    {sugerencias.map((remitente) => (
                        <li key={remitente.ruc}>
                            <button
                                type="button"
                                onClick={() => elegir(remitente)}
                                className={cn(
                                    'flex w-full min-w-0 flex-col items-start gap-0.5 border-b px-3 py-2.5 text-left last:border-b-0 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                                    remitente.ruc === ruc && 'bg-primary/5',
                                )}
                            >
                                <span className="w-full truncate text-sm font-medium">
                                    {remitente.nombre ?? `RUC ${remitente.ruc}`}
                                </span>
                                <span className="w-full truncate text-xs text-muted-foreground">
                                    {remitente.ruc} · {remitente.viajes}{' '}
                                    {remitente.viajes === 1
                                        ? 'viaje'
                                        : 'viajes'}
                                    {remitente.contratante &&
                                        ` · vía ${remitente.contratante.nombre}`}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
