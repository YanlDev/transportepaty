import { destroy } from '@/actions/App/Http/Controllers/ViajeController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';

type Props = {
    viaje: { id: number; numero_gr: string };
    trigger: React.ReactNode;
};

export function DeleteViajeDialog({ viaje, trigger }: Props) {
    return (
        <ConfirmarBorradoDialog
            url={destroy(viaje.id).url}
            titulo="Eliminar viaje"
            trigger={trigger}
            descripcion={
                <>
                    ¿Seguro que deseas eliminar la GR{' '}
                    <Resaltado>{viaje.numero_gr}</Resaltado>? Esta acción no se
                    puede deshacer; si la vuelves a subir, se registra como un
                    viaje nuevo.
                </>
            }
        />
    );
}
