import { FilaCobranza } from '@/components/contabilidad/fila-cobranza';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { FilaAgrupada } from '@/lib/agrupar-viajes';
import type { CuentaOpcion, ViajeContable } from '@/types/contabilidad';
import type { EnumOption } from '@/types/fleet';
import { INICIO_COBRANZA } from './columnas-cobranza';

type Props = {
    filas: FilaAgrupada<ViajeContable>[];
    /** Los de esta página que todavía no se facturaron: lo seleccionable. */
    facturables: ViajeContable[];
    cuentas: CuentaOpcion[];
    monedas: EnumOption[];
    puedeFacturar: boolean;
    seleccion: number[];
    onSeleccion: (ids: number[]) => void;
};

/**
 * La hoja de cobranza. Las doce primeras columnas son el viaje tal como
 * ocurrió y no se tocan acá; de `INICIO_COBRANZA` en adelante empieza lo que
 * el contador llena.
 */
export function TablaCobranza({
    filas,
    facturables,
    cuentas,
    monedas,
    puedeFacturar,
    seleccion,
    onSeleccion,
}: Props) {
    const todosMarcados =
        facturables.length > 0 &&
        facturables.every((viaje) => seleccion.includes(viaje.id));

    const alternar = (viajeId: number) => {
        onSeleccion(
            seleccion.includes(viajeId)
                ? seleccion.filter((otro) => otro !== viajeId)
                : [...seleccion, viajeId],
        );
    };

    return (
        <div className="overflow-x-auto rounded-xl border shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        {puedeFacturar && (
                            <TableHead className="w-0">
                                <Checkbox
                                    aria-label="Seleccionar todos los viajes sin facturar de esta página"
                                    checked={todosMarcados}
                                    disabled={facturables.length === 0}
                                    onCheckedChange={(marcado) =>
                                        onSeleccion(
                                            marcado
                                                ? facturables.map(
                                                      (viaje) => viaje.id,
                                                  )
                                                : [],
                                        )
                                    }
                                />
                            </TableHead>
                        )}
                        <TableHead>Fecha</TableHead>
                        <TableHead>N° GR</TableHead>
                        <TableHead>GR Remitente</TableHead>
                        <TableHead>Tracto</TableHead>
                        <TableHead>Carreta</TableHead>
                        <TableHead>Conductor</TableHead>
                        <TableHead>Cliente</TableHead>
                        <TableHead>Origen</TableHead>
                        <TableHead>Destino</TableHead>
                        <TableHead>Tipo de carga</TableHead>
                        <TableHead className="text-right">Peso</TableHead>
                        {/* Sin título: el ojo se explica solo y así queda
                            pegado al peso, cerrando el bloque de operación. */}
                        <TableHead className="w-0" />
                        <TableHead className={INICIO_COBRANZA}>
                            Estado
                        </TableHead>
                        <TableHead className="text-right">
                            Valor flete fact.
                        </TableHead>
                        <TableHead>N° factura</TableHead>
                        <TableHead>F. emisión</TableHead>
                        <TableHead>F. pago</TableHead>
                        <TableHead>Entidad</TableHead>
                        <TableHead>Observación</TableHead>
                        <TableHead className="w-0" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {filas.map(({ viaje, colorGrupo }) => (
                        <FilaCobranza
                            key={viaje.id}
                            viaje={viaje}
                            colorGrupo={colorGrupo}
                            cuentas={cuentas}
                            monedas={monedas}
                            puedeFacturar={puedeFacturar}
                            seleccionado={seleccion.includes(viaje.id)}
                            onSeleccionar={alternar}
                        />
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
