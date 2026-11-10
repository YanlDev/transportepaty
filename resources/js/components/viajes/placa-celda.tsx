import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { show as mostrarVehiculo } from '@/actions/App/Http/Controllers/VehiculoController';
import { MarcaBf } from '@/components/marca-bf';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatearPlaca } from '@/lib/format';

/**
 * Placa tal como vino en la GR. Si matcheó contra el padrón de vehículos,
 * enlaza a su ficha; si no, se marca en ámbar: mismo lenguaje visual que el
 * resto de la app para «esto necesita que alguien lo revise».
 */
export function PlacaCelda({
    placa,
    vehiculoId,
    bf,
}: {
    placa: string;
    vehiculoId: number | null;
    /** Figura en el registro de bienes fiscalizados: muestra la marca «BF». */
    bf?: boolean;
}) {
    if (vehiculoId !== null) {
        return (
            <span className="inline-flex items-center gap-1">
                <Link
                    href={mostrarVehiculo(vehiculoId)}
                    className="hover:underline"
                >
                    {formatearPlaca(placa)}
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
                    {formatearPlaca(placa)}
                </TooltipTrigger>
                <TooltipContent>
                    No se encontró esta placa en el padrón de vehículos.
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
