import { StatusBadge } from '@/components/ui/status-badge';
import { estadoInfo } from '@/types/fleet';

export function EstadoBadge({
    estado,
    className,
}: {
    estado: string;
    className?: string;
}) {
    const info = estadoInfo(estado);

    return (
        <StatusBadge
            label={info.label}
            tone={info.tone}
            className={className}
        />
    );
}
