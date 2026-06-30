import { Head, setLayoutProps } from '@inertiajs/react';
import conductores, {
    edit,
} from '@/actions/App/Http/Controllers/ConductorController';
import { ConductorForm } from '@/components/conductores/conductor-form';
import type { User } from '@/types/auth';
import type { Conductor, SucursalOption } from '@/types/fleet';

type Props = {
    conductor: Conductor;
    sucursales: SucursalOption[];
    usuarios: User[];
};

export default function ConductorEdit({
    conductor,
    sucursales,
    usuarios,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Conductores', href: conductores.index().url },
            { title: conductor.nombre_completo, href: edit(conductor.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Editar ${conductor.nombre_completo}`} />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Editar conductor
                </h1>
                <p className="text-sm text-muted-foreground">
                    {conductor.nombre_completo} · {conductor.documento}
                </p>
            </div>

            <ConductorForm
                mode="edit"
                conductor={conductor}
                sucursales={sucursales}
                usuarios={usuarios}
            />
        </div>
    );
}
