import { router } from '@inertiajs/react';
import {
    actualizarVencimiento,
    destroy,
} from '@/actions/App/Http/Controllers/VehiculoDocumentoController';
import { DocumentoTarjeta } from '@/components/documento-tarjeta';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import type { EnumOption, RanuraDocumental } from '@/types/fleet';

type Props = {
    ranura: RanuraDocumental;
    vehiculoId: number;
    tipos: EnumOption[];
    puedeEditar: boolean;
};

/**
 * Una ranura del expediente del vehículo. Todo lo visual vive en
 * `DocumentoTarjeta`, compartida con conductores; aquí solo se conectan las
 * rutas propias del vehículo.
 */
export function DocumentoRanura({
    ranura,
    vehiculoId,
    tipos,
    puedeEditar,
}: Props) {
    return (
        <DocumentoTarjeta
            ranura={ranura}
            puedeEditar={puedeEditar}
            urlVencimiento={
                ranura.documento === null
                    ? null
                    : actualizarVencimiento([vehiculoId, ranura.documento.id])
                          .url
            }
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
