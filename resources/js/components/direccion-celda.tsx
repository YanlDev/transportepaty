import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * Solo la ciudad (distrito) en la celda, que es lo que se necesita de un
 * vistazo; la dirección completa —larga, y la mayoría de las veces no hace
 * falta— vive en el desplegable. Genérico a propósito: sirve igual para
 * origen que para destino, son la misma forma de dato.
 */
export function DireccionCelda({
    ciudad,
    direccion,
}: {
    ciudad: string;
    direccion: string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="inline-flex max-w-[160px] cursor-pointer items-center gap-1 hover:underline">
                <span className="truncate">{ciudad}</span>
                <ChevronDown className="size-3.5 shrink-0 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="max-w-xs">
                <p className="px-2 py-1.5 text-sm text-muted-foreground">
                    {direccion}
                </p>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
