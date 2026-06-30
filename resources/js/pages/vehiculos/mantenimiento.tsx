import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Wrench } from 'lucide-react';
import { useState } from 'react';
import { index as vehiculos } from '@/actions/App/Http/Controllers/VehiculoController';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import { EmptyState } from '@/components/empty-state';
import { ChartCostosAnio } from '@/components/mantenimiento/chart-costos-anio';
import { ChartCostosMantenimiento } from '@/components/mantenimiento/chart-costos-mantenimiento';
import { EliminarMantenimientoDialog } from '@/components/mantenimiento/eliminar-mantenimiento-dialog';
import { EstadoComponentes } from '@/components/mantenimiento/estado-componentes';
import { RegistrarMantenimientoDialog } from '@/components/mantenimiento/registrar-mantenimiento-dialog';
import { TablaMantenimientos } from '@/components/mantenimiento/tabla-mantenimientos';
import { TimelineServicios } from '@/components/mantenimiento/timeline-servicios';
import { formatearSoles } from '@/lib/format';
import type {
    CostosAnio,
    Mantenimiento,
    MantenimientoEstadisticas,
    PlanMantenimiento,
    PlantillaOption,
} from '@/types/fleet';

type VehiculoProp = {
    id: number;
    placa: string;
    marca: string;
    modelo: string;
    tipo: string;
    kilometraje: number;
    odometro_vigente: number;
    tiene_gps: boolean;
};

type Props = {
    vehiculo: VehiculoProp;
    plan: PlanMantenimiento[];
    estado_general: {
        al_dia: number;
        proximo: number;
        vencido: number;
        critico: number;
    };
    mantenimientos: Mantenimiento[];
    estadisticas: MantenimientoEstadisticas;
    costos_anio: CostosAnio;
    plantillas: PlantillaOption[];
    odometro_minimo: number;
    puede: { registrar: boolean; gestionar: boolean };
};

export default function MantenimientoPage({
    vehiculo,
    plan,
    mantenimientos,
    estadisticas,
    costos_anio,
    plantillas,
    odometro_minimo,
    puede,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
            { title: 'Mantenimiento', href: '' },
        ],
    });

    const [editando, setEditando] = useState<Mantenimiento | null>(null);
    const [eliminando, setEliminando] = useState<Mantenimiento | null>(null);

    return (
        <div className="flex h-full flex-col gap-6 p-4 md:p-6">
            <Head title={`Mantenimiento · ${vehiculo.placa}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Link
                        href={show(vehiculo.id)}
                        className="mb-1 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        {vehiculo.marca} {vehiculo.modelo} · {vehiculo.placa}
                    </Link>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Mantenimiento
                    </h1>
                </div>

                {puede.registrar && (
                    <RegistrarMantenimientoDialog
                        vehiculoId={vehiculo.id}
                        odometroMinimo={odometro_minimo}
                        plantillas={plantillas}
                    />
                )}
            </div>

            {/* Stats row */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Total mantenimientos"
                    value={String(estadisticas.total_mantenimientos)}
                />
                <StatCard
                    label="Total gastado"
                    value={formatearSoles(estadisticas.total_gastado)}
                />
                <StatCard
                    label="Último costo"
                    value={formatearSoles(estadisticas.ultimo_costo)}
                />
                <StatCard
                    label="Más frecuente"
                    value={estadisticas.mas_comun ?? '—'}
                />
            </div>

            {/* Estado de componentes */}
            <EstadoComponentes
                plan={plan}
                odometroVigente={vehiculo.odometro_vigente}
            />

            {/* Costos */}
            {costos_anio.total > 0 && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <ChartCostosAnio costos={costos_anio} />
                    <ChartCostosMantenimiento mantenimientos={mantenimientos} />
                </div>
            )}

            {/* History */}
            <div className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Historial de mantenimientos
                </h2>

                {mantenimientos.length === 0 ? (
                    <EmptyState
                        icon={<Wrench className="size-6" />}
                        text="Aún no hay mantenimientos registrados para este vehículo."
                    />
                ) : (
                    <div className="grid gap-6 lg:grid-cols-[1fr_minmax(0,360px)]">
                        <TablaMantenimientos
                            mantenimientos={mantenimientos}
                            puedeGestionar={puede.gestionar}
                            onEditar={setEditando}
                            onEliminar={setEliminando}
                        />
                        <div className="rounded-xl border border-border bg-card p-5">
                            <h3 className="mb-4 text-sm font-semibold text-foreground">
                                Línea de tiempo
                            </h3>
                            <TimelineServicios
                                mantenimientos={mantenimientos}
                            />
                        </div>
                    </div>
                )}
            </div>

            {editando && (
                <RegistrarMantenimientoDialog
                    vehiculoId={vehiculo.id}
                    odometroMinimo={odometro_minimo}
                    plantillas={plantillas}
                    mantenimiento={editando}
                    onClose={() => setEditando(null)}
                />
            )}

            {eliminando && (
                <EliminarMantenimientoDialog
                    vehiculoId={vehiculo.id}
                    mantenimiento={eliminando}
                    onClose={() => setEliminando(null)}
                />
            )}
        </div>
    );
}

function StatCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-border bg-card p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-lg font-semibold tracking-tight text-foreground">
                {value}
            </p>
        </div>
    );
}
