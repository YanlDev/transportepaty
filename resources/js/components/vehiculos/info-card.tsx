type DatoProps = {
    icon?: React.ReactNode;
    label: string;
    value: string;
    full?: boolean;
};

/**
 * Un par etiqueta/valor de la ficha del vehículo.
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
