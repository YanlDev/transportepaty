import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { marcarGrFisica } from '@/actions/App/Http/Controllers/ContabilidadController';
import { avisarError } from '@/lib/aviso-error';
import { formatearFechaHora } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * El aspa verde que dice «el papel de esta GR ya está en la oficina». Un clic
 * la pone y otro la quita; la fecha en que llegó queda en el tooltip.
 */
export function CeldaGrFisica({
    viajeId,
    numeroGr,
    recibidaAt,
    editable,
}: {
    viajeId: number;
    numeroGr: string;
    recibidaAt: string | null;
    editable: boolean;
}) {
    const [guardando, setGuardando] = useState(false);
    const recibida = recibidaAt !== null;

    const titulo = recibida
        ? `GR física en oficina desde el ${formatearFechaHora(recibidaAt)}`
        : 'La GR física todavía no llega a la oficina';

    if (!editable) {
        return recibida ? (
            <X
                className="size-4 text-emerald-600 dark:text-emerald-400"
                strokeWidth={3}
                aria-label={titulo}
            />
        ) : null;
    }

    const alternar = () => {
        setGuardando(true);

        router.patch(
            marcarGrFisica(viajeId).url,
            { recibida: !recibida },
            {
                preserveScroll: true,
                preserveState: true,
                onError: avisarError,
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <button
            type="button"
            onClick={alternar}
            disabled={guardando}
            title={titulo}
            aria-pressed={recibida}
            aria-label={
                recibida
                    ? `Quitar la marca de GR física de ${numeroGr}`
                    : `Marcar la GR física de ${numeroGr} como recibida`
            }
            className={cn(
                'group flex size-6 cursor-pointer items-center justify-center rounded-md border transition-colors disabled:animate-pulse disabled:opacity-60',
                recibida
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-600 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400'
                    : 'border-dashed border-muted-foreground/30 text-transparent hover:border-emerald-400 hover:text-emerald-400/60',
            )}
        >
            <X className="size-4" strokeWidth={3} />
        </button>
    );
}
