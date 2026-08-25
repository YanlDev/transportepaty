import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * Solo el departamento/región en la celda, que es lo que se necesita de un
 * vistazo; la dirección completa —larga, y la mayoría de las veces no hace
 * falta— vive en el desplegable.
 */
export function DestinoCelda({
    region,
    direccion,
}: {
    region: string;
    direccion: string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="inline-flex cursor-pointer items-center gap-1 whitespace-nowrap hover:underline">
                {region}
                <ChevronDown className="size-3.5 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="max-w-xs">
                <p className="px-2 py-1.5 text-sm text-muted-foreground">
                    {direccion}
                </p>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
