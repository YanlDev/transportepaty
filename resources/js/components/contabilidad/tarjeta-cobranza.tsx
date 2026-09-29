import { ArrowRight } from 'lucide-react';
import { AccionesFila } from '@/components/contabilidad/acciones-fila';
import { CeldaGrFisica } from '@/components/contabilidad/celda-gr-fisica';
import { EstadoCobranzaBadge } from '@/components/contabilidad/estado-cobranza-badge';
import { VerGuia } from '@/components/contabilidad/ver-guia';
import { Copiable } from '@/components/copiable';
import { Checkbox } from '@/components/ui/checkbox';
import { ClienteConRemitente } from '@/components/viajes/cliente-con-remitente';
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
    const factura = viaje.factura;

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
                    diasVencida={factura?.dias_vencida}
                />
                {factura?.monto != null ? (
                    <span className="text-right">
                        <span className="text-base font-semibold tabular-nums">
                            {factura.simbolo}{' '}
                            {factura.monto.toLocaleString('es-PE', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}
                        </span>
                        {/* El monto es de la factura, no del viaje. */}
                        {factura.viajes_count > 1 && (
                            <span className="block text-xs text-muted-foreground">
                                por {factura.viajes_count} viajes
                            </span>
                        )}
                    </span>
                ) : (
                    // Sin factura, la pastilla de estado ya lo dice.
                    factura && (
                        <span className="text-sm text-muted-foreground">
                            Sin monto
                        </span>
                    )
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
                    {/* Un viaje ya facturado no se puede volver a elegir. */}
                    {puedeFacturar && factura === null && (
                        <Checkbox
                            aria-label={`Seleccionar la GR ${viaje.numero_gr} para facturar`}
                            checked={seleccionado}
                            onCheckedChange={() => onSeleccionar(viaje)}
                        />
                    )}
                    <span className="min-w-0 font-mono text-xs whitespace-nowrap">
                        <Copiable valor={viaje.numero_gr} etiqueta="N° GR" />
                    </span>
                    {factura && (
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            · {factura.numero}
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
                    <VerGuia viaje={viaje} />
                    <AccionesFila viaje={viaje} puedeFacturar={puedeFacturar} />
                </div>
            </div>
        </div>
    );
}
