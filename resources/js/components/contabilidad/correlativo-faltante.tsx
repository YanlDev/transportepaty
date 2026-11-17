import { useForm } from '@inertiajs/react';
import { ChevronDown, ListOrdered } from 'lucide-react';
import { useState } from 'react';
import correlativo from '@/actions/App/Http/Controllers/CorrelativoController';
import { Button } from '@/components/ui/button';
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
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { CorrelativoFaltante, RangoFaltante } from '@/types/contabilidad';

const MOTIVOS = [
    { value: 'anulada_sunat', label: 'Se anuló en SUNAT' },
    { value: 'no_es_flete', label: 'No es un flete (otro servicio)' },
    { value: 'otro', label: 'Otro' },
];

/**
 * Los números de factura que faltan en el correlativo. Es un aviso para que
 * alguien lo mire: la factura puede no haberse subido, haberse anulado en
 * SUNAT o no ser un flete. Subirla la saca de la lista; si no corresponde
 * subirla, se explica y deja de avisarse. Cerrada por defecto.
 */
export function CorrelativoFaltante({
    series,
    puedeExplicar,
}: {
    series: CorrelativoFaltante[];
    puedeExplicar: boolean;
}) {
    const [abierta, setAbierta] = useState(false);
    const faltan = series.reduce((suma, serie) => suma + serie.faltan, 0);

    if (faltan === 0) {
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
                <ListOrdered className="size-5 shrink-0 text-muted-foreground" />
                <span className="flex-1">
                    <span className="font-semibold">
                        {faltan === 1
                            ? 'Falta 1 número de factura'
                            : `Faltan ${faltan} números de factura`}
                    </span>
                    <span className="block text-sm text-muted-foreground">
                        Números del correlativo que no están en el sistema. Sube
                        la factura, o explica por qué no está.
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
                <ul className="flex flex-col divide-y border-t">
                    {series.flatMap((serie) =>
                        serie.rangos.map((rango) => (
                            <li
                                key={`${serie.serie}-${rango.desde}`}
                                className="flex items-center gap-3 px-4 py-2 text-sm"
                            >
                                <span className="flex-1">
                                    <span className="font-mono font-medium">
                                        {serie.serie}-{rango.desde}
                                        {rango.hasta !== rango.desde &&
                                            ` al ${rango.hasta}`}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {rango.cantidad > 1 &&
                                            ` · ${rango.cantidad} números`}
                                        {rango.despues_del &&
                                            ` · después del ${formatearFecha(rango.despues_del)}`}
                                    </span>
                                </span>
                                {puedeExplicar && (
                                    <ExplicarDialog
                                        serie={serie.serie}
                                        rango={rango}
                                    />
                                )}
                            </li>
                        )),
                    )}
                </ul>
            )}
        </section>
    );
}

function ExplicarDialog({
    serie,
    rango,
}: {
    serie: string;
    rango: RangoFaltante;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            serie,
            desde: String(rango.desde),
            hasta: String(rango.hasta),
            motivo: 'anulada_sunat',
            nota: '',
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(correlativo.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Explicar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Explicar {serie}-{rango.desde}
                            {rango.hasta !== rango.desde &&
                                ` al ${rango.hasta}`}
                        </DialogTitle>
                        <DialogDescription>
                            Esos números dejan de avisarse como faltantes. No se
                            crea ni se toca ninguna factura; si después se sube
                            la factura, entra normal.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Desde" error={errors.desde} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    min={rango.desde}
                                    max={rango.hasta}
                                    value={data.desde}
                                    onChange={(e) =>
                                        setData('desde', e.target.value)
                                    }
                                    required
                                />
                            )}
                        </Field>
                        <Field label="Hasta" error={errors.hasta} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    min={rango.desde}
                                    max={rango.hasta}
                                    value={data.hasta}
                                    onChange={(e) =>
                                        setData('hasta', e.target.value)
                                    }
                                    required
                                />
                            )}
                        </Field>
                    </div>
                    <Field label="Qué pasó" error={errors.motivo} required>
                        {(id) => (
                            <Select
                                value={data.motivo}
                                onValueChange={(valor) =>
                                    setData('motivo', valor)
                                }
                            >
                                <SelectTrigger id={id} className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {MOTIVOS.map((motivo) => (
                                        <SelectItem
                                            key={motivo.value}
                                            value={motivo.value}
                                        >
                                            {motivo.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field label="Nota" error={errors.nota}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.nota}
                                maxLength={255}
                                onChange={(e) =>
                                    setData('nota', e.target.value)
                                }
                            />
                        )}
                    </Field>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
