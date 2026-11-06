import { etapaDe } from '@/lib/etapas';
import { cn } from '@/lib/utils';
import type { EtapaVuelta } from '@/types/vueltas';

/** La etapa del ticket como etiqueta, con su color de siempre. */
export function EtapaBadge({
    etapa,
    className,
}: {
    etapa: EtapaVuelta;
    className?: string;
}) {
    const datos = etapaDe(etapa);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                datos.suave,
                className,
            )}
        >
            <span className={cn('size-2 rounded-full', datos.punto)} />
            {datos.titulo}
        </span>
    );
}
