import { router } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/FacturaController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { avisarError } from '@/lib/aviso-error';

/**
 * Aparece al marcar filas y hace lo único que tiene sentido sobre una
 * selección: cerrarlas en una sola factura. Es la forma de decir «estas dos GR
 * fueron un viaje y se cobran una vez». El número se escribe acá mismo, sin
 * abrir nada: el resto de los campos se llenan después en la fila.
 */
export function BarraSeleccion({
    viajeIds,
    onListo,
}: {
    viajeIds: number[];
    onListo: () => void;
}) {
    const [numero, setNumero] = useState('');
    const [guardando, setGuardando] = useState(false);

    const emitir = () => {
        const limpio = numero.trim();

        if (limpio === '') {
            return;
        }

        setGuardando(true);

        router.post(
            store().url,
            { numero: limpio, viaje_ids: viajeIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNumero('');
                    onListo();
                },
                onError: avisarError,
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
            <p className="text-sm">
                <span className="font-semibold">{viajeIds.length}</span>{' '}
                {viajeIds.length === 1
                    ? 'viaje seleccionado'
                    : 'viajes seleccionados'}{' '}
                <span className="text-muted-foreground">
                    — se cobran en una sola factura.
                </span>
            </p>
            <div className="flex items-center gap-2">
                <Input
                    value={numero}
                    onChange={(evento) =>
                        setNumero(evento.target.value.toUpperCase())
                    }
                    onKeyDown={(evento) => {
                        if (evento.key === 'Enter') {
                            emitir();
                        }
                    }}
                    placeholder="F001-00123"
                    aria-label="N° de la factura"
                    className="h-9 w-40 font-mono"
                />
                <Button
                    size="sm"
                    onClick={emitir}
                    disabled={guardando || numero.trim() === ''}
                >
                    Facturar
                </Button>
                <Button variant="ghost" size="sm" onClick={onListo}>
                    Cancelar
                </Button>
            </div>
        </div>
    );
}
