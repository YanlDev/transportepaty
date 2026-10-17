import { cn } from '@/lib/utils';
import type { NivelPlan } from '@/types/mantenimiento';

/**
 * El logo de la marca sobre una placa blanca: los logos son oscuros y en el
 * tema oscuro se perderían sobre el fondo. Sin logo, la inicial.
 */
export function MarcaLogo({
    marca,
    logoUrl,
    className,
}: {
    marca: string;
    logoUrl: string | null;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex h-12 w-16 shrink-0 items-center justify-center rounded-lg bg-white p-1.5 ring-1 ring-black/5',
                className,
            )}
        >
            {logoUrl ? (
                <img
                    src={logoUrl}
                    alt={marca}
                    className="max-h-full max-w-full object-contain"
                    loading="lazy"
                />
            ) : (
                <span className="text-lg font-semibold text-zinc-700">
                    {marca.charAt(0)}
                </span>
            )}
        </span>
    );
}

/** «20 mil», «7.5 mil»: los intervalos se leen mejor así que en km enteros. */
export function kmCorto(km: number): string {
    return `${(km / 1000).toLocaleString('es-PE', { maximumFractionDigits: 1 })} mil`;
}

/** «X 20 mil · S 40 mil · M 80 mil». */
export function resumenNiveles(niveles: NivelPlan[]): string {
    return niveles
        .map((nivel) => `${nivel.codigo} ${kmCorto(nivel.intervalo_km)}`)
        .join(' · ');
}
