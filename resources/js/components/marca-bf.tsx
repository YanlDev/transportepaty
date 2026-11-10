import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * «BF» junto a la placa o al chofer que figura en el Registro de Bienes
 * Fiscalizados de SUNAT: de un vistazo se ve quién puede llevar una carga
 * fiscalizada. Lo que no está inscrito no lleva marca, así la columna no se
 * llena de ruido.
 */
export function MarcaBf({
    inscrito,
    className,
}: {
    inscrito: boolean | null | undefined;
    className?: string;
}) {
    if (!inscrito) {
        return null;
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span
                        className={cn(
                            'inline-flex shrink-0 cursor-help items-center rounded bg-sky-100 px-1 py-px align-middle text-[10px] leading-tight font-semibold tracking-wide text-sky-800 dark:bg-sky-950 dark:text-sky-300',
                            className,
                        )}
                    >
                        BF
                    </span>
                </TooltipTrigger>
                <TooltipContent>
                    Inscrito en el registro IQBF: puede llevar bienes
                    fiscalizados.
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
