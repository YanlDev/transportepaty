import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import cobros from '@/actions/App/Http/Controllers/CobroController';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { Cobro } from '@/types/contabilidad';

/**
 * Anula un cobro diciendo por qué. No se borra: queda en la lista, tachado,
 * y las facturas que pagaba vuelven a deber ese monto.
 */
export function AnularCobroDialog({
    cobro,
    trigger,
}: {
    cobro: Cobro;
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const motivoId = useId();

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({ motivo: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(cobros.anular(cobro.id).url, {
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
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Anular el cobro</DialogTitle>
                        <DialogDescription>
                            {cobro.facturas.length === 0
                                ? 'El cobro deja de contar como saldo a favor.'
                                : cobro.facturas.length === 1
                                  ? `La factura ${cobro.facturas[0].numero} vuelve a deber lo que este cobro le pagaba.`
                                  : `Sus ${cobro.facturas.length} facturas vuelven a deber lo que este cobro les pagaba.`}{' '}
                            No se borra: queda en la lista como anulado.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor={motivoId}>Motivo</Label>
                        <Textarea
                            id={motivoId}
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            placeholder="Ej.: el depósito era de otro cliente"
                            maxLength={500}
                            rows={3}
                            required
                        />
                        <InputError message={errors.motivo} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Anular
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
