import { router } from '@inertiajs/react';
import { Lock, LockOpen } from 'lucide-react';
import { useState } from 'react';
import cierres from '@/actions/App/Http/Controllers/CierreDeMesController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
import { avisarError } from '@/lib/aviso-error';
import { formatearFecha } from '@/lib/format';
import type { MesDeCobranza } from '@/types/contabilidad';

/**
 * Cerrar un mes de la cobranza: sus facturas y sus cobros dejan de poder
 * crearse, corregirse o anularse hasta que se reabra. Antes de cerrar se ve
 * lo que el mes todavía tiene suelto; cerrar no lo impide, solo lo avisa.
 */
export function CierreDeMesDialog({
    meses,
    puedeCerrar,
    trigger,
}: {
    meses: MesDeCobranza[];
    puedeCerrar: boolean;
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Cierre de mes</DialogTitle>
                    <DialogDescription>
                        Con el mes cerrado, sus facturas y sus cobros no se
                        crean, corrigen ni anulan. Cobrar después una factura de
                        un mes cerrado sí se puede.
                    </DialogDescription>
                </DialogHeader>

                <ul className="flex flex-col divide-y rounded-lg border">
                    {meses.map((mes) => (
                        <li
                            key={mes.mes}
                            className="flex flex-col gap-2 p-3 text-sm sm:flex-row sm:items-center"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-2 font-medium capitalize">
                                    {mes.cerrado && (
                                        <Lock className="size-4 text-muted-foreground" />
                                    )}
                                    {mes.label}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {mes.facturas}{' '}
                                    {mes.facturas === 1
                                        ? 'factura'
                                        : 'facturas'}
                                    {mes.sin_cobrar > 0 &&
                                        ` · ${mes.sin_cobrar} sin cobrar`}
                                    {mes.gr_sin_facturar > 0 &&
                                        ` · ${mes.gr_sin_facturar} GR sin facturar`}
                                </p>
                                {mes.cerrado && (
                                    <p className="text-xs text-muted-foreground">
                                        Cerrado
                                        {mes.cerrado_el &&
                                            ` el ${formatearFecha(mes.cerrado_el)}`}
                                        {mes.cerrado_por &&
                                            ` por ${mes.cerrado_por}`}
                                    </p>
                                )}
                            </div>

                            {mes.en_curso ? (
                                <span className="text-xs text-muted-foreground">
                                    en curso
                                </span>
                            ) : (
                                puedeCerrar &&
                                (mes.cerrado ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                cierres.destroy(mes.mes).url,
                                                {
                                                    preserveScroll: true,
                                                    onError: avisarError,
                                                },
                                            )
                                        }
                                    >
                                        <LockOpen className="size-4" />
                                        Reabrir
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                cierres.store().url,
                                                { mes: mes.mes },
                                                {
                                                    preserveScroll: true,
                                                    onError: avisarError,
                                                },
                                            )
                                        }
                                    >
                                        <Lock className="size-4" />
                                        Cerrar
                                    </Button>
                                ))
                            )}
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
