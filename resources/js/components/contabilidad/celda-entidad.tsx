import { router } from '@inertiajs/react';
import { useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/FacturaController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { avisarError } from '@/lib/aviso-error';
import type { CuentaOpcion } from '@/types/contabilidad';

/**
 * Por dónde entró la plata. Es un desplegable y no texto libre para que el
 * mismo banco no termine escrito de tres formas: sin eso no se puede filtrar
 * ni totalizar por entidad.
 *
 * Solo ofrece cuentas de la misma moneda que la factura: cobrar una factura en
 * dólares contra una cuenta en soles es siempre un error de carga.
 */
export function CeldaEntidad({
    facturaId,
    moneda,
    cuentaId,
    cuenta,
    cuentas,
    editable,
}: {
    facturaId: number;
    moneda: string;
    cuentaId: number | null;
    cuenta: string | null;
    cuentas: CuentaOpcion[];
    editable: boolean;
}) {
    const [guardando, setGuardando] = useState(false);

    if (!editable) {
        return cuenta ? (
            <span>{cuenta}</span>
        ) : (
            <span className="text-muted-foreground/40">—</span>
        );
    }

    const compatibles = cuentas.filter((otra) => otra.moneda === moneda);

    const elegir = (nueva: number | null) => {
        if (nueva === cuentaId) {
            return;
        }

        setGuardando(true);

        router.patch(
            update(facturaId).url,
            { cuenta_bancaria_id: nueva },
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
                aria-label="Elegir la entidad del cobro"
                className="w-full cursor-pointer rounded-sm px-1 py-0.5 text-left hover:bg-accent disabled:animate-pulse disabled:opacity-60"
            >
                {cuenta ?? <span className="text-muted-foreground/40">—</span>}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                {compatibles.length === 0 ? (
                    <p className="px-2 py-1.5 text-xs text-muted-foreground">
                        No hay cuentas en {moneda}. Regístrala en Cuentas.
                    </p>
                ) : (
                    compatibles.map((otra) => (
                        <DropdownMenuItem
                            key={otra.id}
                            onSelect={() => elegir(otra.id)}
                        >
                            {otra.alias}
                            <span className="ml-2 text-xs text-muted-foreground">
                                {otra.numero_cuenta}
                            </span>
                        </DropdownMenuItem>
                    ))
                )}
                {cuentaId !== null && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => elegir(null)}>
                            Quitar
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
