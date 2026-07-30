import { Head } from '@inertiajs/react';
import asignaciones, {
    create,
} from '@/actions/App/Http/Controllers/AsignacionController';
import { AsignacionForm } from '@/components/asignaciones/asignacion-form';
import type { Preseleccion } from '@/components/asignaciones/asignacion-form';
import type { ConductorOption, VehiculoOption } from '@/types/fleet';

type Props = {
    conductores: ConductorOption[];
    tractos: VehiculoOption[];
    carretas: VehiculoOption[];
    preseleccion: Preseleccion;
};

export default function AsignacionCreate({
    conductores,
    tractos,
    carretas,
    preseleccion,
}: Props) {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nueva asignación" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Nueva asignación
                </h1>
                <p className="text-sm text-muted-foreground">
                    Arma una unidad juntando conductor, tracto y carreta.
                </p>
            </div>

            <AsignacionForm
                mode="create"
                conductores={conductores}
                tractos={tractos}
                carretas={carretas}
                preseleccion={preseleccion}
            />
        </div>
    );
}

AsignacionCreate.layout = {
    breadcrumbs: [
        { title: 'Asignaciones', href: asignaciones.index().url },
        { title: 'Nueva', href: create().url },
    ],
};
