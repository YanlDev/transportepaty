import { StatusBadge } from '@/components/ui/status-badge';
import type { StatusTone } from '@/components/ui/status-badge';
import type { EstadoEquipamiento } from '@/types/equipamiento';

const tonos: Record<EstadoEquipamiento, StatusTone> = {
    falta: 'danger',
    sin_contar: 'warning',
    contado: 'neutral',
    completo: 'success',
};

export const bordeEquipamiento: Record<EstadoEquipamiento, string> = {
    falta: 'border-l-red-500',
    sin_contar: 'border-l-amber-500',
    contado: 'border-l-zinc-300 dark:border-l-zinc-600',
    completo: 'border-l-emerald-500',
};

export function EstadoEquipamientoBadge({
    estado,
    label,
}: {
    estado: EstadoEquipamiento;
    label: string;
}) {
    return <StatusBadge label={label} tone={tonos[estado]} />;
}
