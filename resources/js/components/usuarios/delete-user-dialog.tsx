import { destroy } from '@/actions/App/Http/Controllers/UserController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import type { UserListItem } from '@/types/fleet';

type Props = {
    usuario: UserListItem;
    trigger: React.ReactNode;
};

export function DeleteUserDialog({ usuario, trigger }: Props) {
    return (
        <ConfirmarBorradoDialog
            url={destroy(usuario.id).url}
            titulo="Eliminar usuario"
            trigger={trigger}
            descripcion={
                <>
                    ¿Seguro que deseas eliminar la cuenta de{' '}
                    <Resaltado>{usuario.name}</Resaltado>? Esta acción no se
                    puede deshacer.
                </>
            }
        />
    );
}
