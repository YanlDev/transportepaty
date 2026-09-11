import { destroy } from '@/actions/App/Http/Controllers/VehiculoController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import { formatearPlaca } from '@/lib/format';

type Props = {
    vehiculo: { id: number; placa: string };
    trigger: React.ReactNode;
};

export function DeleteVehiculoDialog({ vehiculo, trigger }: Props) {
    return (
        <ConfirmarBorradoDialog
            url={destroy(vehiculo.id).url}
            titulo="Eliminar vehículo"
            trigger={trigger}
            descripcion={
                <>
                    ¿Seguro que deseas eliminar{' '}
                    <Resaltado>{formatearPlaca(vehiculo.placa)}</Resaltado>?
                    Podrás recuperarlo más adelante, pero dejará de aparecer en
                    la lista.
                </>
            }
        />
    );
}
