import { Head } from '@inertiajs/react';
import viajes, { create } from '@/actions/App/Http/Controllers/ViajeController';
import { ViajeManualForm } from '@/components/viajes/viaje-manual-form';
import type {
    ConductorOption,
    EnumOption,
    VehiculoOption,
} from '@/types/fleet';

type Props = {
    tractos: VehiculoOption[];
    carretas: VehiculoOption[];
    conductores: ConductorOption[];
    tiposCarga: EnumOption[];
};

export default function ViajeCreate({
    tractos,
    carretas,
    conductores,
    tiposCarga,
}: Props) {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Registrar viaje manualmente" />

            <div>
                <p className="text-sm text-muted-foreground">
                    Para viajes cuya GRE-Remitente ya trae vehículo y conductor,
                    así que SUNAT no exige una GRE-Transportista aparte y no hay
                    PDF que subir.
                </p>
            </div>

            <ViajeManualForm
                tractos={tractos}
                carretas={carretas}
                conductores={conductores}
                tiposCarga={tiposCarga}
            />
        </div>
    );
}

ViajeCreate.layout = {
    breadcrumbs: [
        { title: 'Viajes', href: viajes.index().url },
        { title: 'Registrar manualmente', href: create().url },
    ],
};
