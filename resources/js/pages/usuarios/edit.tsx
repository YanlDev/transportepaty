import { Head, setLayoutProps } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import usuarios, { edit } from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import { ResetPasswordDialog } from '@/components/usuarios/reset-password-dialog';
import { UserForm } from '@/components/usuarios/user-form';
import type { ConductorLinkOption } from '@/types/fleet';

type Usuario = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    conductor_id: number | null;
};

type Props = {
    usuario: Usuario;
    roles: string[];
    conductores: ConductorLinkOption[];
};

export default function UsuarioEdit({ usuario, roles, conductores }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Usuarios', href: usuarios.index().url },
            { title: usuario.name, href: edit(usuario.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Editar ${usuario.name}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {usuario.name} · {usuario.email}
                    </p>
                </div>

                <ResetPasswordDialog
                    usuarioId={usuario.id}
                    trigger={
                        <Button variant="outline">
                            <KeyRound className="size-4" />
                            Restablecer contraseña
                        </Button>
                    }
                />
            </div>

            <UserForm
                mode="edit"
                usuario={usuario}
                roles={roles}
                conductores={conductores}
            />
        </div>
    );
}
