import { Head } from '@inertiajs/react';
import vehiculos, {
    create,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { VehiculoForm } from '@/components/vehiculos/vehiculo-form';
import type {
    ConductorOption,
    DispositivoGpsOption,
    EnumOption,
    SucursalOption,
} from '@/types/fleet';

type Props = {
    sucursales: SucursalOption[];
    conductores: ConductorOption[];
    tipos: EnumOption[];
    combustibles: EnumOption[];
    estados: EnumOption[];
    dispositivosGps: DispositivoGpsOption[];
};

export default function VehiculoCreate(props: Props) {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nuevo vehículo" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Nuevo vehículo
                </h1>
                <p className="text-sm text-muted-foreground">
                    Completa los datos para registrar un vehículo en la flota.
                </p>
            </div>

            <VehiculoForm mode="create" {...props} />
        </div>
    );
}

VehiculoCreate.layout = {
    breadcrumbs: [
        { title: 'Vehículos', href: vehiculos.index().url },
        { title: 'Nuevo', href: create().url },
    ],
};
