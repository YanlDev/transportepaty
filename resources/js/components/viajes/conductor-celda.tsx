import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { show as mostrarConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { MarcaBf } from '@/components/marca-bf';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * El chofer tal como vino en la GR, enlazado a su ficha si el DNI matcheó
 * contra el padrón. En ámbar cuando no, igual que la placa sin resolver.
 */
export function ConductorCelda({
    nombre,
    conductorId,
    bf,
}: {
    nombre: string;
    conductorId: number | null;
    /** Figura en el registro de bienes fiscalizados: muestra la marca «BF». */
    bf?: boolean;
}) {
    if (conductorId !== null) {
        return (
            <span className="inline-flex items-center gap-1">
                <Link
                    href={mostrarConductor(conductorId)}
                    className="hover:underline"
                >
                    {nombre}
                </Link>
                <MarcaBf inscrito={bf} />
            </span>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger className="inline-flex cursor-help items-center gap-1 text-amber-700 dark:text-amber-500">
                    <AlertTriangle className="size-3.5" />
                    {nombre}
                </TooltipTrigger>
                <TooltipContent>
                    No se encontró este DNI en el padrón de conductores.
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
