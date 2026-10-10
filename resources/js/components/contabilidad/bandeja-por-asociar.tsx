import { useForm } from '@inertiajs/react';
import {
    ChevronDown,
    FileText,
    Link2,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useId, useState } from 'react';
import {
    asociar,
    destroy,
} from '@/actions/App/Http/Controllers/FacturaController';
import {
    ConfirmarBorradoDialog,
    Resaltado,
} from '@/components/confirmar-borrado-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FacturaPorAsociar } from '@/types/contabilidad';

/**
 * Las facturas que no cubren ninguna GR: subidas en PDF sin poder asociarse
 * solas (la GR no está en el sistema, ya tiene otra factura, o la factura
 * cobra un período) o que se quedaron sin viajes. La tabla se recorre por GR,
 * así que sin este aviso no se verían en ningún lado.
 *
 * Arriba de la tabla y en ámbar mientras quede alguna: es trabajo pendiente.
 */
export function BandejaPorAsociar({
    facturas,
    puedeGestionar,
}: {
    facturas: FacturaPorAsociar[];
    puedeGestionar: boolean;
}) {
    const [abierta, setAbierta] = useState(false);

    if (facturas.length === 0) {
        return null;
    }

    return (
        <section className="rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40">
            <button
                type="button"
                onClick={() => setAbierta(!abierta)}
                aria-expanded={abierta}
                className="flex w-full items-center gap-3 p-4 text-left"
            >
                <TriangleAlert className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <span className="flex-1">
                    <span className="font-semibold text-amber-900 dark:text-amber-200">
                        {facturas.length === 1
                            ? '1 factura por asociar'
                            : `${facturas.length} facturas por asociar`}
                    </span>
                    <span className="block text-sm text-amber-800/80 dark:text-amber-300/80">
                        No se pudieron asociar solas a sus GR. Asócialas a mano.
                    </span>
                </span>
                <ChevronDown
                    className={cn(
                        'size-5 shrink-0 text-amber-700 transition-transform dark:text-amber-400',
                        abierta && 'rotate-180',
                    )}
                />
            </button>

            {abierta && (
                <ul className="flex flex-col gap-2 border-t border-amber-200 p-3 dark:border-amber-900">
                    {facturas.map((factura) => (
                        <ItemPorAsociar
                            key={factura.id}
                            factura={factura}
                            puedeGestionar={puedeGestionar}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}

function ItemPorAsociar({
    factura,
    puedeGestionar,
}: {
    factura: FacturaPorAsociar;
    puedeGestionar: boolean;
}) {
    return (
        <li className="flex flex-col gap-3 rounded-lg border bg-card p-3 sm:flex-row sm:items-center">
            <div className="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
                <p className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span className="font-mono font-medium">
                        {factura.numero}
                    </span>
                    <span className="text-muted-foreground tabular-nums">
                        {formatearFecha(factura.fecha_emision)}
                    </span>
                    {factura.neto !== null && (
                        <span className="font-medium tabular-nums">
                            Neto {factura.simbolo}{' '}
                            {factura.neto.toLocaleString('es-PE', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}
                        </span>
                    )}
                </p>
                {factura.cliente && (
                    <p className="truncate text-muted-foreground">
                        {factura.cliente}
                    </p>
                )}
                <p className="text-amber-700 dark:text-amber-400">
                    {factura.motivo}
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
                {puedeGestionar && (
                    <>
                        <AsociarDialog factura={factura} />
                        <ConfirmarBorradoDialog
                            url={destroy(factura.id).url}
                            titulo="Anular la factura"
                            etiquetaAccion="Anular"
                            descripcion={
                                <>
                                    Se anula la factura{' '}
                                    <Resaltado>{factura.numero}</Resaltado> y su
                                    PDF. No tiene GR asociadas.
                                </>
                            }
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 text-muted-foreground hover:text-destructive"
                                    aria-label={`Anular la factura ${factura.numero}`}
                                    title="Anular la factura"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            }
                        />
                    </>
                )}
            </div>
        </li>
    );
}

/**
 * Elegir las GR de la factura. Vienen propuestas las del mismo cliente, sin
 * facturar y cercanas a la emisión; las del período que cobra la factura,
 * ya marcadas. Las que no salgan en la lista se escriben por número.
 */
function AsociarDialog({ factura }: { factura: FacturaPorAsociar }) {
    const [open, setOpen] = useState(false);
    const numerosId = useId();

    const sugeridas = () =>
        factura.candidatas
            .filter((candidata) => candidata.sugerida)
            .map((candidata) => candidata.id);

    const {
        data,
        setData,
        transform,
        post,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({ viaje_ids: sugeridas(), numeros: '' });

    // Los números escritos llegan separados por coma, espacio o salto de
    // línea; el servidor los normaliza (`EG03-12429` → `EG03-00012429`).
    transform((datos) => ({
        viaje_ids: datos.viaje_ids,
        numeros_gr: datos.numeros
            .split(/[\s,;]+/)
            .map((numero) => numero.trim())
            .filter(Boolean),
    }));

    const alternar = (id: number) =>
        setData(
            'viaje_ids',
            data.viaje_ids.includes(id)
                ? data.viaje_ids.filter((elegido) => elegido !== id)
                : [...data.viaje_ids, id],
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(asociar(factura.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const errores = errors as Record<string, string | undefined>;

    return (
        <Dialog
            open={open}
            onOpenChange={(valor) => {
                setOpen(valor);

                if (!valor) {
                    reset();
                    setData('viaje_ids', sugeridas());
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm">
                    <Link2 className="size-4" />
                    Asociar GR
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            Asociar la factura {factura.numero}
                        </DialogTitle>
                        <DialogDescription>{factura.motivo}</DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-4 py-4">
                        {factura.candidatas.length > 0 ? (
                            <fieldset className="flex flex-col gap-1">
                                <legend className="mb-1 text-sm font-medium">
                                    GR de {factura.cliente ?? 'este cliente'}{' '}
                                    sin facturar
                                </legend>
                                <ul className="flex max-h-64 flex-col overflow-y-auto rounded-md border">
                                    {factura.candidatas.map((candidata) => (
                                        <li key={candidata.id}>
                                            <label className="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-muted">
                                                <Checkbox
                                                    checked={data.viaje_ids.includes(
                                                        candidata.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        alternar(candidata.id)
                                                    }
                                                />
                                                <span className="font-mono">
                                                    {candidata.numero_gr}
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {formatearFecha(
                                                        candidata.fecha_traslado,
                                                    )}
                                                </span>
                                                <span className="ml-auto truncate text-muted-foreground">
                                                    {candidata.destino}
                                                </span>
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            </fieldset>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No hay GR de este cliente sin facturar cerca de
                                la fecha. Escríbelas por número.
                            </p>
                        )}

                        <div className="grid gap-1.5">
                            <Label htmlFor={numerosId}>
                                Otras GR, por número
                            </Label>
                            <Input
                                id={numerosId}
                                value={data.numeros}
                                onChange={(e) =>
                                    setData(
                                        'numeros',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="EG03-12429, EG03-12430"
                                className="font-mono"
                            />
                            <InputError
                                message={
                                    errores.numeros_gr ?? errores.viaje_ids
                                }
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                (data.viaje_ids.length === 0 &&
                                    data.numeros.trim() === '')
                            }
                        >
                            {processing && <Spinner />}
                            Asociar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
