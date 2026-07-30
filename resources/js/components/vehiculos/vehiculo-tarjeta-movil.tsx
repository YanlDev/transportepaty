import { Link } from '@inertiajs/react';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import { Copiable } from '@/components/copiable';
import {
    filaSemaforo,
    ResumenProblemas,
} from '@/components/semaforo-documental';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import type { VehiculoListItem } from '@/types/fleet';

/**
 * El vehículo como ficha, para pantallas donde la tabla no cabe. Deja arriba lo
 * que sirve para identificarlo —placa y estado— y debajo lo que hace falta para
 * decidir si puede salir: el TUC y el resumen documental. El resto de columnas
 * (año, color, ejes) se consulta entrando al detalle.
 *
 * La placa es un enlace de verdad y no toda la tarjeta: dentro hay botones de
 * copiar, y anidar botones en un enlace no es válido ni utilizable.
 */
export function VehiculoTarjetaMovil({
    vehiculo,
}: {
    vehiculo: VehiculoListItem;
}) {
    return (
        <div
            className={`group/fila flex flex-col gap-2 border p-3 ${filaSemaforo[vehiculo.documentacion.semaforo]}`}
        >
            <div className="flex items-start justify-between gap-2">
                <Copiable valor={vehiculo.placa} etiqueta="placa">
                    <Link
                        href={show(vehiculo.id)}
                        className="text-base font-semibold underline-offset-2 hover:underline"
                    >
                        {vehiculo.placa}
                    </Link>
                </Copiable>
                <EstadoBadge estado={vehiculo.estado} />
            </div>

            <p className="text-xs text-muted-foreground">
                {vehiculo.tipo_label}
                {vehiculo.marca && ` · ${vehiculo.marca}`}
                {vehiculo.caja_label && ` · ${vehiculo.caja_label}`}
            </p>

            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <span className="font-medium">TUC</span>
                    <Copiable valor={vehiculo.tuc_numero} etiqueta="TUC" />
                </span>

                <ResumenProblemas estado={vehiculo.documentacion} />
            </div>
        </div>
    );
}
