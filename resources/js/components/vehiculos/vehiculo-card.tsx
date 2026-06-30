import { Link } from '@inertiajs/react';
import { Building2, Car, Gauge, User } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { tipoLabels } from '@/types/fleet';
import type { VehiculoListItem } from '@/types/fleet';

export function VehiculoCard({ vehiculo }: { vehiculo: VehiculoListItem }) {
    const conductor = vehiculo.conductor
        ? `${vehiculo.conductor.nombres} ${vehiculo.conductor.apellidos}`
        : 'Sin asignar';

    return (
        <Link
            href={show(vehiculo.id)}
            prefetch
            className="group flex flex-col overflow-hidden rounded-xl border border-border bg-card transition-all hover:border-emerald-500/40 hover:shadow-md"
        >
            <div className="flex items-center gap-3 border-b border-border p-4">
                {vehiculo.foto ? (
                    <img
                        src={vehiculo.foto}
                        alt={`${vehiculo.marca} ${vehiculo.modelo}`}
                        className="size-12 shrink-0 rounded-lg object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="grid size-12 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
                        <Car className="size-6" />
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold text-foreground">
                        {vehiculo.marca} {vehiculo.modelo}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {vehiculo.anio} ·{' '}
                        {tipoLabels[vehiculo.tipo] ?? vehiculo.tipo}
                    </p>
                </div>
                <EstadoBadge estado={vehiculo.estado} />
            </div>

            <div className="flex flex-col gap-2.5 p-4 text-sm">
                <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">Placa</span>
                    <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-xs font-medium tracking-wide text-foreground">
                        {vehiculo.placa}
                    </span>
                </div>
                <Row
                    icon={<Gauge className="size-4" />}
                    label="Kilometraje"
                    value={`${vehiculo.kilometraje.toLocaleString('es-PE')} km`}
                />
                <Row
                    icon={<Building2 className="size-4" />}
                    label="Sucursal"
                    value={vehiculo.sucursal?.nombre ?? '—'}
                />
                <Row
                    icon={<User className="size-4" />}
                    label="Conductor"
                    value={conductor}
                />
            </div>
        </Link>
    );
}

function Row({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="flex items-center gap-2 text-muted-foreground">
                {icon}
                {label}
            </span>
            <span className="truncate text-right font-medium text-foreground">
                {value}
            </span>
        </div>
    );
}
