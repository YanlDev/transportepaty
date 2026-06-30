import { Fuel, Gauge, Route, Wallet } from 'lucide-react';
import { formatearSoles } from '@/lib/format';
import type { ResumenCombustible } from '@/types/fleet';

type Props = {
    resumen: ResumenCombustible;
    esElectrico: boolean;
};

export function ResumenStats({ resumen, esElectrico }: Props) {
    return (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            {!esElectrico && (
                <Stat
                    icon={<Gauge className="size-4" />}
                    label="Rendimiento promedio"
                    value={
                        resumen.rendimiento_promedio !== null
                            ? `${resumen.rendimiento_promedio} km/gal`
                            : '—'
                    }
                    hint={
                        resumen.rendimiento_ultimo !== null
                            ? `Última carga: ${resumen.rendimiento_ultimo} km/gal`
                            : 'Aún sin datos suficientes'
                    }
                    destacado
                />
            )}
            <Stat
                icon={<Fuel className="size-4" />}
                label="Galones totales"
                value={`${resumen.total_galones} gal`}
                hint={`${resumen.total_cargas} carga(s)`}
            />
            <Stat
                icon={<Wallet className="size-4" />}
                label="Costo total"
                value={formatearSoles(resumen.total_costo)}
                hint={
                    resumen.costo_por_km !== null
                        ? `${formatearSoles(resumen.costo_por_km)} / km`
                        : undefined
                }
            />
            <Stat
                icon={<Route className="size-4" />}
                label="Km recorridos"
                value={`${resumen.km_total.toLocaleString('es-PE')} km`}
                hint="Entre cargas procesadas"
            />
        </div>
    );
}

function Stat({
    icon,
    label,
    value,
    hint,
    destacado,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    hint?: string;
    destacado?: boolean;
}) {
    return (
        <div
            className={`rounded-xl border p-4 ${destacado ? 'border-emerald-200 bg-emerald-50/50' : 'border-border bg-card'}`}
        >
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {icon}
                {label}
            </div>
            <p
                className={`mt-1 text-xl font-semibold tabular-nums ${destacado ? 'text-emerald-800' : 'text-foreground'}`}
            >
                {value}
            </p>
            {hint && (
                <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
