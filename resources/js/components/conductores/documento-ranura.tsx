import { router } from '@inertiajs/react';
import {
    actualizarVencimiento,
    destroy,
} from '@/actions/App/Http/Controllers/ConductorDocumentoController';
import { AgregarDocumentoConductorDialog } from '@/components/conductores/agregar-documento-dialog';
import { DocumentoTarjeta } from '@/components/documento-tarjeta';
import type { EnumOption, RanuraDocumental } from '@/types/fleet';

type Props = {
    ranura: RanuraDocumental;
    conductorId: number;
    tipos: EnumOption[];
    puedeEditar: boolean;
};

/**
 * Una ranura del expediente del conductor. Comparte la tarjeta con el
 * expediente del vehículo: lo único propio son las rutas de carga y borrado.
 */
export function DocumentoRanuraConductor({
    ranura,
    conductorId,
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
                    : actualizarVencimiento([conductorId, ranura.documento.id])
                          .url
            }
            onEliminar={() => {
                if (ranura.documento === null) {
                    return;
                }

                router.delete(destroy([conductorId, ranura.documento.id]).url, {
                    preserveScroll: true,
                });
            }}
            renderCargar={(trigger) => (
                <AgregarDocumentoConductorDialog
                    conductorId={conductorId}
                    tipos={tipos}
                    tipoInicial={ranura.tipo}
                    trigger={trigger}
                />
            )}
        />
    );
}
