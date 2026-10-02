import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/FacturaController';
import { avisarError } from '@/lib/aviso-error';

/**
 * La celda «N° factura» de un viaje que todavía no se facturó. Escribir el
 * número acá emite la factura sobre ese viaje y nada más: es el camino de una
 * GR que se cobra sola, que es la mayoría.
 *
 * Para cobrar varias GR en un solo documento —dos que salieron en el mismo
 * camión, o la quincena de un cliente— se marcan las filas y se usa la barra
 * de selección, que es la única forma de decir «estas van juntas».
 *
 * Con `adicional` aparece debajo de una factura ya emitida y le suma otra al
 * mismo viaje: la estadía aparte del flete, o un cobro partido.
 */
export function CeldaNuevaFactura({
    viajeId,
    adicional = false,
}: {
    viajeId: number;
    adicional?: boolean;
}) {
    const [editando, setEditando] = useState(false);
    const [numero, setNumero] = useState('');
    const [guardando, setGuardando] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editando) {
            input.current?.focus();
        }
    }, [editando]);

    const emitir = () => {
        const limpio = numero.trim();

        setEditando(false);
        setNumero('');

        if (limpio === '') {
            return;
        }

        setGuardando(true);

        router.post(
            store().url,
            { numero: limpio, viaje_ids: [viajeId] },
            {
                preserveScroll: true,
                onError: avisarError,
                onFinish: () => setGuardando(false),
            },
        );
    };

    if (editando) {
        return (
            <input
                ref={input}
                value={numero}
                onChange={(evento) =>
                    setNumero(evento.target.value.toUpperCase())
                }
                onBlur={emitir}
                onKeyDown={(evento) => {
                    if (evento.key === 'Enter') {
                        evento.currentTarget.blur();
                    }

                    if (evento.key === 'Escape') {
                        setNumero('');
                        setEditando(false);
                    }
                }}
                placeholder="F001-00123"
                className="w-full min-w-28 rounded-sm border border-primary bg-background px-1 py-0.5 font-mono text-xs outline-none"
            />
        );
    }

    return (
        <button
            type="button"
            onClick={() => setEditando(true)}
            aria-label={
                adicional
                    ? 'Registrar otra factura para este viaje'
                    : 'Registrar la factura de este viaje'
            }
            className={`w-full rounded-sm px-1 py-0.5 text-left text-muted-foreground/40 hover:bg-accent hover:text-foreground ${
                adicional ? 'mt-0.5 font-sans text-[11px]' : ''
            } ${guardando ? 'animate-pulse opacity-60' : ''}`}
        >
            {adicional ? '+ otra factura' : '+ factura'}
        </button>
    );
}
