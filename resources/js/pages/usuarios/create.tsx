import { Head } from '@inertiajs/react';
import usuarios, {
    create,
} from '@/actions/App/Http/Controllers/UserController';
import { UserForm } from '@/components/usuarios/user-form';
import type { ConductorLinkOption } from '@/types/fleet';

type Props = {
    roles: string[];
    conductores: ConductorLinkOption[];
};

export default function UsuarioCreate({ roles, conductores }: Props) {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nuevo usuario" />

            <div>
                <p className="text-sm text-muted-foreground">
                    Crea una cuenta con su contraseña y rol de acceso.
                </p>
            </div>

            <UserForm mode="create" roles={roles} conductores={conductores} />
        </div>
    );
}

UsuarioCreate.layout = {
    breadcrumbs: [
        { title: 'Usuarios', href: usuarios.index().url },
        { title: 'Nuevo', href: create().url },
    ],
};
