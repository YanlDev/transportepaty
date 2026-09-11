import { router } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/VehiculoDocumentoController';
import { DocumentoFila } from '@/components/documento-fila';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import type { EnumOption, RanuraDocumental } from '@/types/fleet';

type Props = {
    ranura: RanuraDocumental;
    vehiculoId: number;
    tipos: EnumOption[];
    puedeEditar: boolean;
};

/**
 * Una fila del expediente del vehículo. Todo lo visual vive en
 * `DocumentoFila`; aquí solo se conectan las rutas propias del vehículo,
 * igual que hace `DocumentoRanura` con la versión en tarjeta.
 */
export function DocumentoFilaVehiculo({
    ranura,
    vehiculoId,
    tipos,
    puedeEditar,
}: Props) {
    return (
        <DocumentoFila
            ranura={ranura}
            puedeEditar={puedeEditar}
            onEliminar={() => {
                if (ranura.documento === null) {
                    return;
                }

                router.delete(destroy([vehiculoId, ranura.documento.id]).url, {
                    preserveScroll: true,
                });
            }}
            renderCargar={(trigger) => (
                <AgregarDocumentoDialog
                    vehiculoId={vehiculoId}
                    tipos={tipos}
                    tipoInicial={ranura.tipo}
                    trigger={trigger}
                />
            )}
        />
    );
}
