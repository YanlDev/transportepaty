import { Head } from '@inertiajs/react';
import conductores, {
    create,
} from '@/actions/App/Http/Controllers/ConductorController';
import { ConductorForm } from '@/components/conductores/conductor-form';
import type { SucursalOption } from '@/types/fleet';
import type { User } from '@/types/auth';

type Props = {
    sucursales: SucursalOption[];
    usuarios: User[];
};

export default function ConductorCreate({ sucursales, usuarios }: Props) {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nuevo conductor" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Nuevo conductor
                </h1>
                <p className="text-sm text-muted-foreground">
                    Registra un conductor para asignarle vehículos.
                </p>
            </div>

            <ConductorForm
                mode="create"
                sucursales={sucursales}
                usuarios={usuarios}
            />
        </div>
    );
}

ConductorCreate.layout = {
    breadcrumbs: [
        { title: 'Conductores', href: conductores.index().url },
        { title: 'Nuevo', href: create().url },
    ],
};
