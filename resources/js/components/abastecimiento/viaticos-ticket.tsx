import { Ban, ChevronRight, Pencil, Plus, Wallet } from 'lucide-react';
import { useState } from 'react';
import type { AccionViatico } from '@/components/abastecimiento/viatico-dialog';
import {
    ACCIONES,
    CambiosMovimiento,
} from '@/components/historial/cambios-movimiento';
import { Button } from '@/components/ui/button';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { VueltaAbastecimiento } from '@/types/abastecimiento';

const soles = (valor: number) =>
    `S/ ${valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });

/**
 * Los viáticos del ticket: cada uno de la pizarra, pendiente o depositado
 * (con lo que se depositó de verdad), los imprevistos, los anulados tachados
 * y el historial de todo lo que se hizo con ellos.
 */
export function ViaticosTicket({
    vuelta,
    puedeGestionar,
    onAccion,
}: {
    vuelta: VueltaAbastecimiento;
    puedeGestionar: boolean;
    onAccion: (accion: AccionViatico) => void;
}) {
    // Sin el OK de taller o con candado no se deposita (`bloqueo_gastos`).
    const puedeDepositar = puedeGestionar && vuelta.bloqueo_gastos === null;
    const [verHistorial, setVerHistorial] = useState(false);
    const [verAnulados, setVerAnulados] = useState(false);

    return (
        <section className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between gap-2">
                <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Viáticos
                </h3>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {soles(vuelta.viatico_depositado)} depositado
                    {vuelta.viatico_pendiente > 0 && (
                        <span className="font-medium text-amber-700 dark:text-amber-400">
                            {' '}
                            · falta {soles(vuelta.viatico_pendiente)}
                        </span>
                    )}
                </span>
            </div>

            {vuelta.viaticos.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    La tarjeta del ticket no trae viáticos.
                </p>
            )}

            <ul className="flex flex-col divide-y rounded-lg border">
                {vuelta.viaticos.map((viatico, indice) => {
                    const deposito = viatico.deposito;
                    const diferencia =
                        deposito && viatico.previsto !== null
                            ? deposito.monto - viatico.previsto
                            : 0;

                    return (
                        <li
                            key={deposito?.id ?? `pendiente-${indice}`}
                            className="flex flex-col gap-1 p-3"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {viatico.concepto}
                                        {deposito?.imprevisto && (
                                            <span className="ml-1.5 rounded bg-muted px-1.5 py-0.5 text-[10px] font-normal text-muted-foreground uppercase">
                                                imprevisto
                                            </span>
                                        )}
                                    </p>
                                    {viatico.previsto !== null && (
                                        <p className="text-xs text-muted-foreground">
                                            Pizarra {soles(viatico.previsto)}
                                        </p>
                                    )}
                                </div>
                                {deposito ? (
                                    <div className="text-right">
                                        <p className="text-sm font-medium text-emerald-700 tabular-nums dark:text-emerald-400">
                                            {soles(deposito.monto)}
                                        </p>
                                        {diferencia !== 0 && (
                                            <p className="text-xs text-amber-700 tabular-nums dark:text-amber-400">
                                                {diferencia > 0 ? '+' : '−'}
                                                {soles(Math.abs(diferencia))}
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300">
                                        Pendiente
                                    </span>
                                )}
                            </div>

                            {deposito && (
                                <p className="text-xs text-muted-foreground">
                                    Depositado el{' '}
                                    {formatearFecha(deposito.fecha)}
                                    {deposito.numero_operacion &&
                                        ` · op. ${deposito.numero_operacion}`}
                                    {deposito.registrado_por &&
                                        ` · ${deposito.registrado_por}`}
                                    {deposito.observacion && (
                                        <span className="block italic">
                                            {deposito.observacion}
                                        </span>
                                    )}
                                </p>
                            )}

                            {puedeGestionar && (
                                <div className="flex gap-1.5">
                                    {deposito ? (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 px-2 text-xs"
                                                onClick={() =>
                                                    onAccion({
                                                        tipo: 'corregir',
                                                        id: deposito.id,
                                                        concepto:
                                                            viatico.concepto,
                                                        imprevisto:
                                                            deposito.imprevisto,
                                                        previsto:
                                                            viatico.previsto,
                                                        monto: deposito.monto,
                                                        fecha: deposito.fecha,
                                                        numero_operacion:
                                                            deposito.numero_operacion,
                                                        observacion:
                                                            deposito.observacion,
                                                    })
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Corregir
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 px-2 text-xs text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    onAccion({
                                                        tipo: 'anular',
                                                        id: deposito.id,
                                                        concepto:
                                                            viatico.concepto,
                                                        monto: deposito.monto,
                                                    })
                                                }
                                            >
                                                <Ban className="size-3.5" />
                                                Anular
                                            </Button>
                                        </>
                                    ) : (
                                        puedeDepositar &&
                                        viatico.linea_id !== null && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-7 px-2 text-xs"
                                                onClick={() =>
                                                    onAccion({
                                                        tipo: 'depositar',
                                                        vueltaId: vuelta.id,
                                                        codigo: vuelta.codigo,
                                                        lineaId:
                                                            viatico.linea_id as number,
                                                        concepto:
                                                            viatico.concepto,
                                                        previsto:
                                                            viatico.previsto,
                                                    })
                                                }
                                            >
                                                <Wallet className="size-3.5" />
                                                Depositar
                                            </Button>
                                        )
                                    )}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>

            {puedeDepositar && (
                <Button
                    variant="outline"
                    size="sm"
                    className="self-start"
                    onClick={() =>
                        onAccion({
                            tipo: 'imprevisto',
                            vueltaId: vuelta.id,
                            codigo: vuelta.codigo,
                        })
                    }
                >
                    <Plus className="size-3.5" />
                    Viático imprevisto
                </Button>
            )}

            {vuelta.viaticos_anulados.length > 0 && (
                <div className="flex flex-col gap-1">
                    <Desplegable
                        abierto={verAnulados}
                        onCambiar={setVerAnulados}
                        texto={`Anulados · ${vuelta.viaticos_anulados.length}`}
                    />
                    {verAnulados && (
                        <ul className="flex flex-col gap-1.5 text-xs">
                            {vuelta.viaticos_anulados.map((anulado) => (
                                <li
                                    key={anulado.id}
                                    className="rounded-md bg-muted/50 p-2"
                                >
                                    <span className="line-through">
                                        {anulado.concepto} ·{' '}
                                        {soles(anulado.monto)} ·{' '}
                                        {formatearFecha(anulado.fecha)}
                                    </span>
                                    <span className="block text-muted-foreground">
                                        Anulado
                                        {anulado.anulado_por &&
                                            ` por ${anulado.anulado_por}`}
                                        {anulado.motivo &&
                                            `: ${anulado.motivo}`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {vuelta.movimientos_viaticos.length > 0 && (
                <div className="flex flex-col gap-1">
                    <Desplegable
                        abierto={verHistorial}
                        onCambiar={setVerHistorial}
                        texto={`Historial · ${vuelta.movimientos_viaticos.length} movimientos`}
                    />
                    {verHistorial && (
                        <ol className="flex flex-col gap-2 border-l pl-3">
                            {vuelta.movimientos_viaticos.map((movimiento) => (
                                <li key={movimiento.id} className="text-xs">
                                    <p>
                                        <span className="text-muted-foreground tabular-nums">
                                            {fechaHora(movimiento.fecha)}
                                        </span>{' '}
                                        ·{' '}
                                        <span className="font-medium">
                                            {movimiento.usuario ?? 'Sistema'}
                                        </span>{' '}
                                        {ACCIONES[
                                            movimiento.accion
                                        ]?.texto.toLowerCase() ??
                                            movimiento.accion}{' '}
                                        {movimiento.etiqueta?.split(' · ')[0]}
                                    </p>
                                    <CambiosMovimiento
                                        accion={movimiento.accion}
                                        cambios={movimiento.cambios}
                                        className="mt-0.5"
                                    />
                                </li>
                            ))}
                        </ol>
                    )}
                </div>
            )}
        </section>
    );
}

function Desplegable({
    abierto,
    onCambiar,
    texto,
}: {
    abierto: boolean;
    onCambiar: (abierto: boolean) => void;
    texto: string;
}) {
    return (
        <button
            type="button"
            onClick={() => onCambiar(!abierto)}
            className="flex items-center gap-1 self-start text-xs text-muted-foreground hover:text-foreground"
        >
            <ChevronRight
                className={cn('size-3.5 transition', abierto && 'rotate-90')}
            />
            {texto}
        </button>
    );
}
