import { Head, setLayoutProps } from '@inertiajs/react';
import asignaciones, {
    edit,
} from '@/actions/App/Http/Controllers/AsignacionController';
import { AsignacionForm } from '@/components/asignaciones/asignacion-form';
import type {
    Asignacion,
    ConductorOption,
    VehiculoOption,
} from '@/types/fleet';

type Props = {
    asignacion: Asignacion;
    conductores: ConductorOption[];
    tractos: VehiculoOption[];
    carretas: VehiculoOption[];
};

export default function AsignacionEdit({
    asignacion,
    conductores,
    tractos,
    carretas,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asignaciones', href: asignaciones.index().url },
            {
                title: asignacion.tracto_placa,
                href: edit(asignacion.id).url,
            },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Editar asignación ${asignacion.tracto_placa}`} />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Editar asignación
                </h1>
                <p className="text-sm text-muted-foreground">
                    Corrige los datos de la unidad. Si lo que cambió es el
                    conductor, libera la unidad y arma una nueva para conservar
                    el historial.
                </p>
            </div>

            <AsignacionForm
                mode="edit"
                asignacion={asignacion}
                conductores={conductores}
                tractos={tractos}
                carretas={carretas}
            />
        </div>
    );
}
