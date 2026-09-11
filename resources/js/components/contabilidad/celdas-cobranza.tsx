import { CeldaEditable } from '@/components/contabilidad/celda-editable';
import { CeldaEntidad } from '@/components/contabilidad/celda-entidad';
import { CeldaMoneda } from '@/components/contabilidad/celda-moneda';
import { CeldaNuevaFactura } from '@/components/contabilidad/celda-nueva-factura';
import { TableCell } from '@/components/ui/table';
import { formatearFecha } from '@/lib/format';
import type { CuentaOpcion, ViajeContable } from '@/types/contabilidad';
import type { EnumOption } from '@/types/fleet';

/**
 * Las seis columnas de la hoja del contador, editables en el sitio. Cada celda
 * guarda su propio campo por separado, así que se llenan en el orden en que se
 * van conociendo: primero el número de factura, el monto cuando llega, y la
 * fecha de pago recién cuando entra la plata.
 */
export function CeldasCobranza({
    viaje,
    cuentas,
    monedas,
    editable,
}: {
    viaje: ViajeContable;
    cuentas: CuentaOpcion[];
    monedas: EnumOption[];
    editable: boolean;
}) {
    const factura = viaje.factura;

    if (factura === null) {
        return (
            <>
                <TableCell className="text-right text-muted-foreground/40">
                    —
                </TableCell>
                <TableCell className="font-mono text-[11px] whitespace-nowrap">
                    {editable ? (
                        <CeldaNuevaFactura viajeId={viaje.id} />
                    ) : (
                        <span className="text-muted-foreground/40">—</span>
                    )}
                </TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
                <TableCell className="text-muted-foreground/40">—</TableCell>
            </>
        );
    }

    return (
        <>
            <TableCell className="whitespace-nowrap tabular-nums">
                <div className="flex items-center justify-end gap-0.5">
                    <CeldaMoneda
                        facturaId={factura.id}
                        moneda={factura.moneda}
                        simbolo={factura.simbolo}
                        monedas={monedas}
                        editable={editable}
                    />
                    <CeldaEditable
                        facturaId={factura.id}
                        campo="monto"
                        valor={factura.monto}
                        tipo="numero"
                        editable={editable}
                        className="text-right"
                        ancho="min-w-24"
                    >
                        {factura.monto === null ? null : (
                            <span className="inline-flex flex-col items-end">
                                <span className="font-medium">
                                    {factura.monto.toLocaleString('es-PE', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })}
                                </span>
                                {/* El monto es de la factura, no de la fila:
                                    sin esto, tres filas de S/ 4,500 se leen
                                    como S/ 13,500 cobrados. */}
                                {factura.viajes_count > 1 && (
                                    <span className="text-[10px] text-muted-foreground">
                                        por {factura.viajes_count} viajes
                                    </span>
                                )}
                            </span>
                        )}
                    </CeldaEditable>
                </div>
            </TableCell>

            <TableCell className="font-mono text-[11px] whitespace-nowrap tabular-nums">
                <CeldaEditable
                    facturaId={factura.id}
                    campo="numero"
                    valor={factura.numero}
                    editable={editable}
                    ancho="min-w-28"
                />
            </TableCell>

            <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                <CeldaEditable
                    facturaId={factura.id}
                    campo="fecha_emision"
                    valor={factura.fecha_emision}
                    tipo="fecha"
                    editable={editable}
                    ancho="min-w-32"
                >
                    {factura.fecha_emision
                        ? formatearFecha(factura.fecha_emision)
                        : null}
                </CeldaEditable>
            </TableCell>

            <TableCell className="whitespace-nowrap tabular-nums">
                <CeldaEditable
                    facturaId={factura.id}
                    campo="fecha_pago"
                    valor={factura.fecha_pago}
                    tipo="fecha"
                    editable={editable}
                    ancho="min-w-32"
                >
                    {factura.fecha_pago
                        ? formatearFecha(factura.fecha_pago)
                        : null}
                </CeldaEditable>
            </TableCell>

            <TableCell className="whitespace-nowrap text-muted-foreground">
                <CeldaEntidad
                    facturaId={factura.id}
                    moneda={factura.moneda}
                    cuentaId={factura.cuenta_bancaria_id}
                    cuenta={factura.cuenta}
                    cuentas={cuentas}
                    editable={editable}
                />
                {/* Cobrada pero sin decir por dónde entró la plata: es
                    justamente el dato que este módulo existe para no perder. */}
                {factura.fecha_pago !== null &&
                    factura.cuenta_bancaria_id === null && (
                        <span className="block text-[10px] text-amber-700 dark:text-amber-500">
                            falta la entidad
                        </span>
                    )}
            </TableCell>

            <TableCell className="max-w-[220px] text-muted-foreground">
                <CeldaEditable
                    facturaId={factura.id}
                    campo="observacion"
                    valor={factura.observacion}
                    editable={editable}
                    className="truncate"
                    ancho="min-w-40"
                />
            </TableCell>
        </>
    );
}
