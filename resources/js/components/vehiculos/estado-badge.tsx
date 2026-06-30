import { cn } from '@/lib/utils';
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
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                info.badge,
                className,
            )}
        >
            <span className={cn('size-1.5 rounded-full', info.dot)} />
            {info.label}
        </span>
    );
}
