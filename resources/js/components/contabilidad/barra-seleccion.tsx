import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/FacturaController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { avisarError } from '@/lib/aviso-error';
import type { ViajeSeleccionado } from '@/types/contabilidad';

/**
 * Aparece al marcar filas y hace lo único que tiene sentido sobre una
 * selección: cerrarlas en una sola factura. Es la forma de decir «estas dos GR
 * fueron un viaje y se cobran una vez». El número se escribe acá mismo, sin
 * abrir nada: el resto de los campos se llenan después en la fila.
 *
 * Lista las GR elegidas porque la selección puede venir de varias páginas y
 * las de otra página no se ven en la tabla: así se revisa qué entra en la
 * factura antes de emitirla.
 */
export function BarraSeleccion({
    seleccion,
    onQuitar,
    onListo,
}: {
    seleccion: ViajeSeleccionado[];
    onQuitar: (viajeId: number) => void;
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
            { numero: limpio, viaje_ids: seleccion.map((viaje) => viaje.id) },
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
        <div className="flex flex-col gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm">
                    <span className="font-semibold">{seleccion.length}</span>{' '}
                    {seleccion.length === 1
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

            <ul className="flex flex-wrap gap-1.5">
                {seleccion.map((viaje) => (
                    <li
                        key={viaje.id}
                        className="flex items-center gap-1 rounded-md border bg-background py-0.5 pr-1 pl-2 font-mono text-xs"
                    >
                        {viaje.numero_gr}
                        <button
                            type="button"
                            onClick={() => onQuitar(viaje.id)}
                            aria-label={`Quitar ${viaje.numero_gr} de la selección`}
                            className="rounded-sm p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X className="size-3" />
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
