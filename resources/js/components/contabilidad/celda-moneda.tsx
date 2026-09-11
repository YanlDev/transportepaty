import { router } from '@inertiajs/react';
import { useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/FacturaController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { avisarError } from '@/lib/aviso-error';
import type { EnumOption } from '@/types/fleet';

/**
 * El símbolo delante del monto, que además es el selector de moneda. Va pegado
 * a la cifra y no en una columna aparte porque casi todo se cobra en soles: una
 * columna entera para la excepción sería ancho perdido en una tabla que ya es
 * larga.
 *
 * Cambiar la moneda suelta la cuenta elegida: una factura en dólares no se
 * cobra contra una cuenta en soles.
 */
export function CeldaMoneda({
    facturaId,
    moneda,
    simbolo,
    monedas,
    editable,
}: {
    facturaId: number;
    moneda: string;
    simbolo: string;
    monedas: EnumOption[];
    editable: boolean;
}) {
    const [guardando, setGuardando] = useState(false);

    if (!editable) {
        return <span className="text-muted-foreground">{simbolo}</span>;
    }

    const elegir = (nueva: string) => {
        if (nueva === moneda) {
            return;
        }

        setGuardando(true);

        router.patch(
            update(facturaId).url,
            { moneda: nueva, cuenta_bancaria_id: null },
            {
                preserveScroll: true,
                preserveState: true,
                onError: avisarError,
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                disabled={guardando}
                aria-label="Cambiar la moneda de la factura"
                className="cursor-pointer rounded-sm px-0.5 text-muted-foreground hover:bg-accent hover:text-foreground disabled:animate-pulse disabled:opacity-60"
            >
                {simbolo}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                {monedas.map((opcion) => (
                    <DropdownMenuItem
                        key={opcion.value}
                        onSelect={() => elegir(opcion.value)}
                    >
                        {opcion.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
