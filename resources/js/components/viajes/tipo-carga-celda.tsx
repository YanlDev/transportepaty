import { router } from '@inertiajs/react';
import { useState } from 'react';
import { actualizarTipoCarga } from '@/actions/App/Http/Controllers/ViajeController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { TipoCargaBadge } from '@/components/viajes/tipo-carga-badge';
import type { EnumOption } from '@/types/fleet';

/**
 * El tipo de carga de un viaje, editable en el sitio para quien puede
 * gestionarlo: la GR no siempre trae con qué clasificarlo y se corrige a
 * mano desde el listado. Compartido entre la tabla de escritorio y la
 * tarjeta móvil para que se pueda corregir desde el celular igual.
 */
export function TipoCargaCelda({
    viajeId,
    valor,
    label,
    opciones,
    editable,
}: {
    viajeId: number;
    valor: string;
    label: string;
    opciones: EnumOption[];
    editable: boolean;
}) {
    const [guardando, setGuardando] = useState(false);

    if (!editable) {
        return <TipoCargaBadge valor={valor} label={label} />;
    }

    const seleccionar = (nuevoValor: string) => {
        if (nuevoValor === valor) {
            return;
        }

        setGuardando(true);

        router.patch(
            actualizarTipoCarga(viajeId).url,
            { tipo_carga: nuevoValor },
            { preserveScroll: true, onFinish: () => setGuardando(false) },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                disabled={guardando}
                className="cursor-pointer rounded-md disabled:cursor-wait disabled:opacity-60"
            >
                <TipoCargaBadge
                    valor={valor}
                    label={label}
                    className="hover:bg-accent hover:text-accent-foreground"
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                {opciones.map((opcion) => (
                    <DropdownMenuItem
                        key={opcion.value}
                        onSelect={() => seleccionar(opcion.value)}
                    >
                        {opcion.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
