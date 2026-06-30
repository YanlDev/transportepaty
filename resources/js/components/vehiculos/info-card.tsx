type InfoCardProps = {
    title: string;
    children: React.ReactNode;
};

/**
 * Bordered card with a section title, used across the vehicle detail view.
 */
export function InfoCard({ title, children }: InfoCardProps) {
    return (
        <section className="h-full rounded-xl border border-border bg-card p-5">
            <h2 className="mb-4 text-sm font-semibold text-foreground">{title}</h2>
            {children}
        </section>
    );
}

type DatoProps = {
    icon?: React.ReactNode;
    label: string;
    value: string;
    full?: boolean;
};

/**
 * A single label/value pair inside an {@link InfoCard}.
 */
export function Dato({ icon, label, value, full }: DatoProps) {
    return (
        <div className={full ? 'flex items-center justify-between gap-3' : ''}>
            <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {icon}
                {label}
            </dt>
            <dd
                className={
                    full
                        ? 'text-sm font-medium text-foreground'
                        : 'mt-0.5 text-sm font-medium text-foreground'
                }
            >
                {value}
            </dd>
        </div>
    );
}
