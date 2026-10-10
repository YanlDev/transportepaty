import { ArrowRight } from 'lucide-react';
import { AccionesFila } from '@/components/contabilidad/acciones-fila';
import { CeldaGrFisica } from '@/components/contabilidad/celda-gr-fisica';
import { DescargarGuia } from '@/components/contabilidad/descargar-guia';
import { EstadoCobranzaBadge } from '@/components/contabilidad/estado-cobranza-badge';
import { Checkbox } from '@/components/ui/checkbox';
import { ClienteConRemitente } from '@/components/viajes/cliente-con-remitente';
import { diasVencidaMayor, esFacturable } from '@/lib/cobranza';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ViajeContable } from '@/types/contabilidad';

type Props = {
    viaje: ViajeContable;
    /** Color del grupo de GR que viajaron juntas, o null si va sola. */
    colorGrupo: string | null;
    puedeFacturar: boolean;
    seleccionado: boolean;
    onSeleccionar: (viaje: ViajeContable) => void;
};

/**
 * Una GR de la cobranza en el celular. Arriba lo que se viene a mirar —si se
 * cobró y cuánto—, después de qué viaje se trata. La edición celda por celda
 * (monto, cuenta, fechas) es de tablet y escritorio: en el teléfono se
 * revisa, se marca la GR física y se eligen viajes para facturar.
 */
export function TarjetaCobranza({
    viaje,
    colorGrupo,
    puedeFacturar,
    seleccionado,
    onSeleccionar,
}: Props) {
    const facturas = viaje.facturas;

    return (
        <div
            className={cn(
                'flex flex-col gap-2 rounded-xl border bg-card p-3',
                colorGrupo && cn('border-l-2', colorGrupo),
                seleccionado && 'border-primary bg-primary/5',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <EstadoCobranzaBadge
                    estado={viaje.estado}
                    label={viaje.estado_label}
                    diasVencida={diasVencidaMayor(viaje)}
                />
                {/* Una sola factura: el neto grande —lo que tiene que entrar a
                    la cuenta—, y debajo de dónde sale. Con varias, el detalle
                    va abajo, una línea por factura. */}
                {facturas.length === 1 &&
                    (facturas[0].neto != null ? (
                        <span className="text-right">
                            <span className="text-base font-semibold tabular-nums">
                                {facturas[0].simbolo}{' '}
                                {formatearMonto(facturas[0].neto)}
                            </span>
                            <span className="block text-xs text-muted-foreground tabular-nums">
                                Total {formatearMonto(facturas[0].total ?? 0)}
                                {(facturas[0].detraccion ?? 0) > 0 &&
                                    ` − detr. ${formatearMonto(facturas[0].detraccion ?? 0)}`}
                            </span>
                            {/* El valor es de la factura, no del viaje. */}
                            {facturas[0].viajes_count > 1 &&
                                facturas[0].monto != null && (
                                    <span className="block text-xs text-muted-foreground">
                                        {formatearMonto(
                                            facturas[0].monto /
                                                facturas[0].viajes_count,
                                        )}{' '}
                                        c/u + IGV · {facturas[0].viajes_count}{' '}
                                        GR
                                    </span>
                                )}
                        </span>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Sin monto
                        </span>
                    ))}
                {facturas.length > 1 && (
                    <span className="text-sm text-muted-foreground">
                        {facturas.length} facturas
                    </span>
                )}
            </div>

            <div className="flex min-w-0 items-center justify-between gap-2">
                <span className="shrink-0 text-sm font-medium tabular-nums">
                    {formatearFecha(viaje.fecha_traslado)}
                </span>
                <span className="min-w-0 truncate">
                    <ClienteConRemitente
                        cliente={viaje.cliente}
                        remitente={viaje.remitente}
                    />
                </span>
            </div>

            <p className="flex min-w-0 items-center gap-1.5 text-sm">
                <span className="truncate">{viaje.origen_ciudad}</span>
                <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
                <span className="truncate">{viaje.destino_ciudad}</span>
                <span className="ml-auto shrink-0 font-mono text-xs text-muted-foreground">
                    {formatearPlaca(viaje.placa_tracto)}
                </span>
            </p>

            <div className="flex items-center justify-between gap-2 border-t pt-2">
                <div className="flex min-w-0 items-center gap-2">
                    {/* También uno ya facturado: puede llevar otra factura
                        aparte, como la estadía. No uno que no se cobra. */}
                    {puedeFacturar && esFacturable(viaje) && (
                        <Checkbox
                            aria-label={`Seleccionar la GR ${viaje.numero_gr} para facturar`}
                            checked={seleccionado}
                            onCheckedChange={() => onSeleccionar(viaje)}
                        />
                    )}
                    <span className="min-w-0 font-mono text-xs whitespace-nowrap">
                        {viaje.numero_gr}
                    </span>
                    <DescargarGuia viaje={viaje} />
                    {facturas.length === 1 && (
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            · {facturas[0].numero}
                        </span>
                    )}
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <CeldaGrFisica
                        viajeId={viaje.id}
                        numeroGr={viaje.numero_gr}
                        recibidaAt={viaje.gr_fisica_recibida_at}
                        editable={puedeFacturar}
                    />
                    {facturas.length <= 1 && (
                        <AccionesFila
                            viaje={viaje}
                            factura={facturas[0] ?? null}
                            puedeFacturar={puedeFacturar}
                            agregarOtra
                        />
                    )}
                </div>
            </div>

            {facturas.length > 1 && (
                <ul className="flex flex-col gap-1 border-t pt-2">
                    {facturas.map((factura, indice) => (
                        <li
                            key={factura.id}
                            className="flex items-center justify-between gap-2 text-sm"
                        >
                            <span className="truncate font-mono text-xs">
                                {factura.numero}
                            </span>
                            <span className="ml-auto shrink-0 tabular-nums">
                                {factura.neto != null
                                    ? `${factura.simbolo} ${formatearMonto(factura.neto)}`
                                    : 'Sin monto'}
                                {factura.estado === 'facturado' && (
                                    <span className="ml-1 text-xs text-amber-700 dark:text-amber-500">
                                        por cobrar
                                    </span>
                                )}
                                {factura.estado === 'falta_detraccion' && (
                                    <span className="ml-1 text-xs text-sky-700 dark:text-sky-400">
                                        falta detr.
                                    </span>
                                )}
                            </span>
                            <AccionesFila
                                viaje={viaje}
                                factura={factura}
                                puedeFacturar={puedeFacturar}
                                agregarOtra={indice === facturas.length - 1}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function formatearMonto(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
