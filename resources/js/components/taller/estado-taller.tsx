import { Wrench } from 'lucide-react';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatearFechaHora } from '@/lib/format';
import type { RevisionTaller } from '@/types/vueltas';

/** `Y-m-d H:i` del servidor a `dd/mm/yyyy hh:mm`. */
export function fechaHoraTaller(valor: string): string {
    return formatearFechaHora(valor.replace(' ', 'T'));
}

/**
 * En qué quedó el ticket con taller: por revisar, apto, o no apto con cuándo
 * estará lista la unidad.
 */
export function EstadoTallerBadge({
    revision,
    corto = false,
}: {
    revision: RevisionTaller | null;
    corto?: boolean;
}) {
    if (revision === null) {
        return <StatusBadge label="Por revisar en taller" tone="warning" />;
    }

    if (revision.resultado === 'apto') {
        return <StatusBadge label="Apto de taller" tone="success" />;
    }

    return (
        <StatusBadge
            label={
                corto || !revision.disponible_estimado
                    ? 'No apto'
                    : `No apto · lista ${fechaHoraTaller(revision.disponible_estimado)}`
            }
            tone="danger"
        />
    );
}

/**
 * Una llave junto a la placa mientras el ticket espera a taller: ámbar si
 * nadie lo revisó, roja si no es apto. Con el apto no se muestra nada.
 */
export function IconoTaller({ revision }: { revision: RevisionTaller | null }) {
    if (revision?.resultado === 'apto') {
        return null;
    }

    const noApta = revision?.resultado === 'no_apto';

    return (
        <Wrench
            className={
                noApta ? 'size-3.5 text-red-600' : 'size-3.5 text-amber-600'
            }
            aria-label={noApta ? 'No apto de taller' : 'Por revisar en taller'}
        >
            <title>
                {noApta
                    ? `No apto${revision?.disponible_estimado ? ` · lista ${fechaHoraTaller(revision.disponible_estimado)}` : ''}`
                    : 'Por revisar en taller'}
            </title>
        </Wrench>
    );
}
