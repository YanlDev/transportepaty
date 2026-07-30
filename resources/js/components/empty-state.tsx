import { cn } from '@/lib/utils';

type Props = {
    icon: React.ReactNode;
    text: string;
    className?: string;
};

/**
 * Centered placeholder shown when a list or section has no content.
 */
export function EmptyState({ icon, text, className }: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-10 text-center text-muted-foreground',
                className,
            )}
        >
            <div className="grid size-11 place-items-center rounded-none bg-muted">
                {icon}
            </div>
            <p className="max-w-xs text-sm">{text}</p>
        </div>
    );
}
