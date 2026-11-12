import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { PesoBfDialog } from '@/components/viajes/peso-bf-dialog';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearPeso } from '@/lib/format';
import type { ViajeListItem } from '@/types/fleet';

/**
 * El peso de la GR. El de una GR de bienes fiscalizados no viene en el PDF:
 * mientras falte sale «Falta peso», y quien sube o corrige GR lo carga o lo
 * corrige ahí mismo. Compartido entre la tabla y la tarjeta móvil.
 */
export function PesoCelda({
    viaje,
}: {
    viaje: Pick<
        ViajeListItem,
        | 'id'
        | 'numero_gr'
        | 'cliente'
        | 'placa_tracto'
        | 'peso'
        | 'unidad_peso'
        | 'bienes_fiscalizados'
        | 'falta_peso'
    >;
}) {
    const [abierto, setAbierto] = useState(false);
    const { puede } = usePermisos();

    if (!viaje.bienes_fiscalizados) {
        return <>{formatearPeso(viaje.peso, viaje.unidad_peso)}</>;
    }

    const editable = puede('viajes.registrar') || puede('viajes.editar');

    if (!editable) {
        return viaje.falta_peso ? (
            <span className="text-muted-foreground">Sin peso</span>
        ) : (
            <>{formatearPeso(viaje.peso, viaje.unidad_peso)}</>
        );
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setAbierto(true)}
                className={
                    viaje.falta_peso
                        ? 'inline-flex cursor-pointer items-center rounded-md bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900 hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-200'
                        : 'group inline-flex cursor-pointer items-center gap-1 rounded-md hover:text-foreground'
                }
                aria-label={
                    viaje.falta_peso
                        ? `Poner el peso de la GR ${viaje.numero_gr}`
                        : `Corregir el peso de la GR ${viaje.numero_gr}`
                }
            >
                {viaje.falta_peso ? (
                    'Falta peso'
                ) : (
                    <>
                        <Pencil className="size-3 text-muted-foreground opacity-0 group-hover:opacity-100" />
                        {formatearPeso(viaje.peso, viaje.unidad_peso)}
                    </>
                )}
            </button>
            <PesoBfDialog
                guias={[
                    {
                        id: viaje.id,
                        numero_gr: viaje.numero_gr,
                        cliente: viaje.cliente,
                        placa_tracto: viaje.placa_tracto,
                        peso: viaje.falta_peso
                            ? null
                            : viaje.unidad_peso === 'KGM'
                              ? viaje.peso / 1000
                              : viaje.peso,
                    },
                ]}
                abierto={abierto}
                onAbiertoChange={setAbierto}
            />
        </>
    );
}
