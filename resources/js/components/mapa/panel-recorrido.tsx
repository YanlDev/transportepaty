import { ArrowLeft, Clock, Gauge, Route } from 'lucide-react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Recorrido } from '@/types/fleet';

const HOY = new Date().toISOString().slice(0, 10);

const PRESETS: { value: string; label: string }[] = [
    { value: 'hoy', label: 'Hoy' },
    { value: 'ayer', label: 'Ayer' },
    { value: '3dias', label: 'Últimos 3 días' },
    { value: 'semana', label: 'Esta semana' },
    { value: 'semana_pasada', label: 'Semana pasada' },
    { value: 'mes', label: 'Este mes' },
    { value: 'mes_pasado', label: 'Mes pasado' },
    { value: 'personalizado', label: 'Día específico' },
];

type Props = {
    placa: string;
    preset: string;
    fechaDia: string;
    recorrido: Recorrido | null;
    onPreset: (value: string) => void;
    onFechaDia: (value: string) => void;
    onVolver: () => void;
};

export function PanelRecorrido({
    placa,
    preset,
    fechaDia,
    recorrido,
    onPreset,
    onFechaDia,
    onVolver,
}: Props) {
    const hayRuta = recorrido !== null && recorrido.puntos.length > 0;

    return (
        <div className="flex flex-col gap-3 p-3">
            <button
                type="button"
                onClick={onVolver}
                className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft className="size-4" />
                Volver a flota en vivo
            </button>

            <div className="rounded-lg bg-muted px-3 py-2">
                <p className="text-xs text-muted-foreground">Recorrido de</p>
                <p className="font-mono text-sm font-semibold">{placa}</p>
            </div>

            <div className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">
                    Período
                </span>
                <Select value={preset} onValueChange={onPreset}>
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {PRESETS.map((p) => (
                            <SelectItem key={p.value} value={p.value}>
                                {p.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-1.5">
                <span className="text-xs font-medium text-muted-foreground">
                    Día específico
                </span>
                <Input
                    type="date"
                    value={fechaDia}
                    max={HOY}
                    onChange={(e) => onFechaDia(e.target.value)}
                />
            </div>

            {hayRuta && (
                <div className="space-y-3 border-t border-border pt-3">
                    <span
                        className={
                            recorrido.stats.con_movimiento
                                ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20'
                                : 'inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 ring-1 ring-zinc-500/20'
                        }
                    >
                        <span
                            className={`size-1.5 rounded-full ${recorrido.stats.con_movimiento ? 'bg-emerald-500' : 'bg-zinc-400'}`}
                        />
                        {recorrido.stats.con_movimiento
                            ? 'Tuvo movimiento'
                            : 'Sin movimiento en el período'}
                    </span>

                    <dl className="grid grid-cols-2 gap-3">
                        <Stat
                            icon={<Route className="size-4" />}
                            label="Distancia"
                            value={`${recorrido.stats.distancia_km} km`}
                        />
                        <Stat
                            icon={<Clock className="size-4" />}
                            label="Tiempo en mov."
                            value={formatearDuracion(
                                recorrido.stats.duracion_min,
                            )}
                        />
                        <Stat
                            icon={<Gauge className="size-4" />}
                            label="Vel. prom."
                            value={`${recorrido.stats.velocidad_prom} km/h`}
                        />
                        <Stat
                            icon={<Gauge className="size-4" />}
                            label="Vel. máx."
                            value={`${recorrido.stats.velocidad_max} km/h`}
                        />
                    </dl>
                </div>
            )}
        </div>
    );
}

function Stat({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div>
            <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {icon}
                {label}
            </dt>
            <dd className="mt-0.5 font-semibold text-foreground">{value}</dd>
        </div>
    );
}

function formatearDuracion(min: number): string {
    if (min < 60) {
        return `${min} min`;
    }

    return `${Math.floor(min / 60)}h ${min % 60}min`;
}
