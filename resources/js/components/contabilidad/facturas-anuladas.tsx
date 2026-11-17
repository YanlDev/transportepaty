import { router } from '@inertiajs/react';
import { Ban, ChevronDown, FileText, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { restaurar } from '@/actions/App/Http/Controllers/FacturaController';
import { Button } from '@/components/ui/button';
import { avisarError } from '@/lib/aviso-error';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FacturaAnulada } from '@/types/contabilidad';

/**
 * Las facturas anuladas: no cuentan en la cobranza, pero quedan a la vista
 * con su motivo, quién las anuló y las GR que cobraban. Cerrada por defecto:
 * es registro, no trabajo pendiente.
 */
export function FacturasAnuladas({ facturas }: { facturas: FacturaAnulada[] }) {
    const [abierta, setAbierta] = useState(false);

    if (facturas.length === 0) {
        return null;
    }

    return (
        <section className="rounded-xl border bg-card">
            <button
                type="button"
                onClick={() => setAbierta(!abierta)}
                aria-expanded={abierta}
                className="flex w-full items-center gap-3 p-4 text-left"
            >
                <Ban className="size-5 shrink-0 text-muted-foreground" />
                <span className="flex-1">
                    <span className="font-semibold">
                        {facturas.length === 1
                            ? '1 factura anulada'
                            : `${facturas.length} facturas anuladas`}
                    </span>
                    <span className="block text-sm text-muted-foreground">
                        No cuentan en la cobranza. Se pueden restaurar.
                    </span>
                </span>
                <ChevronDown
                    className={cn(
                        'size-5 shrink-0 text-muted-foreground transition-transform',
                        abierta && 'rotate-180',
                    )}
                />
            </button>

            {abierta && (
                <ul className="flex flex-col gap-2 border-t p-3">
                    {facturas.map((factura) => (
                        <li
                            key={factura.id}
                            className="flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center"
                        >
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
                                <p className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span className="font-mono font-medium line-through">
                                        {factura.numero}
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatearFecha(factura.fecha_emision)}
                                    </span>
                                    {factura.total !== null && (
                                        <span className="tabular-nums">
                                            Total {factura.simbolo}{' '}
                                            {factura.total.toLocaleString(
                                                'es-PE',
                                                {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                },
                                            )}
                                        </span>
                                    )}
                                </p>
                                {factura.cliente && (
                                    <p className="truncate text-muted-foreground">
                                        {factura.cliente}
                                    </p>
                                )}
                                <p>{factura.motivo ?? 'Sin motivo'}</p>
                                <p className="text-xs text-muted-foreground">
                                    Anulada
                                    {factura.anulada_el &&
                                        ` el ${formatearFecha(factura.anulada_el)}`}
                                    {factura.anulada_por &&
                                        ` por ${factura.anulada_por}`}
                                    {factura.guias.length > 0 &&
                                        ` · cobraba ${factura.guias.join(', ')}`}
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-1">
                                {factura.archivo_url && (
                                    <Button asChild variant="ghost" size="sm">
                                        <a
                                            href={factura.archivo_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <FileText className="size-4" />
                                            PDF
                                        </a>
                                    </Button>
                                )}
                                {factura.restaurable && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                restaurar(factura.id).url,
                                                {},
                                                {
                                                    preserveScroll: true,
                                                    onError: avisarError,
                                                },
                                            )
                                        }
                                    >
                                        <Undo2 className="size-4" />
                                        Restaurar
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
