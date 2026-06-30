import { Link } from '@inertiajs/react';
import { LineChart } from 'lucide-react';

type Props = {
    title: string;
    subtitle?: string;
    vacio?: boolean;
    /** Optional element rendered at the right of the header (e.g. a button). */
    action?: React.ReactNode;
    /** When set, the chart body links to this URL (the header stays clickable separately). */
    href?: string;
    children: React.ReactNode;
};

/** Titled card wrapper for a chart, with an empty state. */
export function ChartCard({
    title,
    subtitle,
    vacio,
    action,
    href,
    children,
}: Props) {
    const cuerpo = vacio ? (
        <div className="flex h-[240px] flex-col items-center justify-center gap-2 text-center text-sm text-muted-foreground">
            <LineChart className="size-6" />
            Aún no hay datos suficientes.
        </div>
    ) : (
        children
    );

    return (
        <div className="rounded-xl border border-border bg-card p-4">
            <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold text-foreground">
                        {title}
                    </h3>
                    {subtitle && (
                        <p className="text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {action}
            </div>

            {href ? (
                <Link href={href} className="block transition hover:opacity-90">
                    {cuerpo}
                </Link>
            ) : (
                cuerpo
            )}
        </div>
    );
}
