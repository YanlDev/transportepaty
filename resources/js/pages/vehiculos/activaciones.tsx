import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Power } from 'lucide-react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import { index as vehiculos } from '@/actions/App/Http/Controllers/VehiculoController';
import { EliminarActivacionDialog } from '@/components/activaciones/eliminar-activacion-dialog';
import { RegistrarActivacionDialog } from '@/components/activaciones/registrar-activacion-dialog';
import { TablaActivaciones } from '@/components/activaciones/tabla-activaciones';
import { EmptyState } from '@/components/empty-state';
import type { Activacion, EnumOption } from '@/types/fleet';

type VehiculoProp = {
    id: number;
    placa: string;
    marca: string;
    modelo: string;
    kilometraje: number;
};

type Props = {
    vehiculo: VehiculoProp;
    activaciones: Activacion[];
    resultados: EnumOption[];
    reposoDias: number;
    puede: { registrar: boolean; gestionar: boolean };
};

export default function Activaciones({
    vehiculo,
    activaciones,
    resultados,
    reposoDias,
    puede,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
            { title: 'Activaciones', href: '' },
        ],
    });

    const [eliminando, setEliminando] = useState<Activacion | null>(null);

    return (
        <div className="flex h-full flex-col gap-6 p-4 md:p-6">
            <Head title={`Activaciones · ${vehiculo.placa}`} />

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
                        Activaciones periódicas
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Registro de encendido y recorrido corto de las unidades
                        en reposo (sin movimiento por {reposoDias} días o más),
                        para prevenir el deterioro por inactividad.
                    </p>
                </div>

                {puede.registrar && (
                    <RegistrarActivacionDialog
                        vehiculoId={vehiculo.id}
                        resultados={resultados}
                        kilometrajeSugerido={vehiculo.kilometraje}
                    />
                )}
            </div>

            <div className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Historial de activaciones
                </h2>

                {activaciones.length === 0 ? (
                    <EmptyState
                        icon={<Power className="size-6" />}
                        text="Aún no se registraron activaciones para este vehículo."
                    />
                ) : (
                    <TablaActivaciones
                        activaciones={activaciones}
                        puedeGestionar={puede.gestionar}
                        onEliminar={setEliminando}
                    />
                )}
            </div>

            {eliminando && (
                <EliminarActivacionDialog
                    vehiculoId={vehiculo.id}
                    activacion={eliminando}
                    onClose={() => setEliminando(null)}
                />
            )}
        </div>
    );
}
