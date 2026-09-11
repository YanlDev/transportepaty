import { destroy } from '@/actions/App/Http/Controllers/ConductorController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import type { ConductorListItem } from '@/types/fleet';

type Props = {
    conductor: ConductorListItem;
    trigger: React.ReactNode;
};

export function DeleteConductorDialog({ conductor, trigger }: Props) {
    return (
        <ConfirmarBorradoDialog
            url={destroy(conductor.id).url}
            titulo="Eliminar conductor"
            trigger={trigger}
            descripcion={
                <>
                    ¿Seguro que deseas eliminar{' '}
                    <Resaltado>{conductor.nombre_completo}</Resaltado>? Esta
                    acción no se puede deshacer.
                </>
            }
        />
    );
}
