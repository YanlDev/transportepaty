import { Head, setLayoutProps } from '@inertiajs/react';
import vehiculos, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { VehiculoForm } from '@/components/vehiculos/vehiculo-form';
import type { EnumOption, Vehiculo } from '@/types/fleet';

type Props = {
    vehiculo: Vehiculo;
    tipos: EnumOption[];
    cajas: EnumOption[];
    estados: EnumOption[];
};

export default function VehiculoEdit({ vehiculo, ...props }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos.index().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
            { title: 'Editar', href: edit(vehiculo.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Editar ${vehiculo.placa}`} />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Editar vehículo
                </h1>
                <p className="text-sm text-muted-foreground">
                    {vehiculo.marca} {vehiculo.modelo} · {vehiculo.placa}
                </p>
            </div>

            <VehiculoForm mode="edit" vehiculo={vehiculo} {...props} />
        </div>
    );
}
