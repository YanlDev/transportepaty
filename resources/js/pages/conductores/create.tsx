import { Head } from '@inertiajs/react';
import conductores, {
    create,
} from '@/actions/App/Http/Controllers/ConductorController';
import { ConductorForm } from '@/components/conductores/conductor-form';
import type { User } from '@/types/auth';

type Props = {
    usuarios: User[];
};

export default function ConductorCreate({ usuarios }: Props) {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nuevo conductor" />

            <div>
                <p className="text-sm text-muted-foreground">
                    Registra un conductor en el padrón.
                </p>
            </div>

            <ConductorForm mode="create" usuarios={usuarios} />
        </div>
    );
}

ConductorCreate.layout = {
    breadcrumbs: [
        { title: 'Conductores', href: conductores.index().url },
        { title: 'Nuevo', href: create().url },
    ],
};
