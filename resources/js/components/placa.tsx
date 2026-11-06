import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';

const TAMANOS = {
    sm: 'px-1.5 text-xs border',
    md: 'px-2 py-0.5 text-sm border-2',
    lg: 'px-2.5 py-0.5 text-lg border-2',
} as const;

/**
 * La placa como se ve en la unidad: blanca, con borde oscuro y la franja de
 * arriba. Es lo que la gente reconoce de lejos, así que va igual en todas
 * las pantallas (también en modo oscuro: una placa es blanca).
 */
export function Placa({
    placa,
    tamano = 'md',
    className,
}: {
    placa: string;
    tamano?: keyof typeof TAMANOS;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-[5px] border-zinc-900 border-t-[3px] border-t-sky-700 bg-white font-mono leading-tight font-bold tracking-wider whitespace-nowrap text-zinc-900 shadow-xs',
                TAMANOS[tamano],
                className,
            )}
        >
            {formatearPlaca(placa)}
        </span>
    );
}
