import { Head } from '@inertiajs/react';
import usuarios, {
    create,
} from '@/actions/App/Http/Controllers/UserController';
import { UserForm } from '@/components/usuarios/user-form';
import type { ModuloPermisos, RolConPermisos } from '@/types';

type Props = {
    roles: RolConPermisos[];
    modulos: ModuloPermisos[];
};

export default function UsuarioCreate({ roles, modulos }: Props) {
    return (
        <div className="flex w-full max-w-3xl flex-col gap-6">
            <Head title="Nuevo usuario" />

            <div>
                <p className="text-sm text-muted-foreground">
                    Crea una cuenta con su contraseña y rol de acceso.
                </p>
            </div>

            <UserForm mode="create" roles={roles} modulos={modulos} />
        </div>
    );
}

UsuarioCreate.layout = {
    breadcrumbs: [
        { title: 'Usuarios', href: usuarios.index().url },
        { title: 'Nuevo', href: create().url },
    ],
};
