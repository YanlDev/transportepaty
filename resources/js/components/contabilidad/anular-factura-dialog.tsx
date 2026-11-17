import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { anular } from '@/actions/App/Http/Controllers/FacturaController';
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

type Props = {
    factura: { id: number; numero: string };
    /** Cuántos viajes cobra: vuelven a quedar por facturar. */
    viajes: number;
    trigger: React.ReactNode;
};

/**
 * Anula una factura diciendo por qué. No se borra: queda en «Facturas
 * anuladas», de donde se puede restaurar, y sus viajes vuelven a estar por
 * facturar. Si ya tiene un cobro registrado, el servidor pide quitarlo antes.
 */
export function AnularFacturaDialog({ factura, viajes, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const motivoId = useId();

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({ motivo: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(anular(factura.id).url, {
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
                        <DialogTitle>
                            Anular la factura {factura.numero}
                        </DialogTitle>
                        <DialogDescription>
                            {viajes === 0
                                ? 'La factura sale de la cobranza.'
                                : viajes === 1
                                  ? 'La factura sale de la cobranza y su viaje vuelve a quedar por facturar.'
                                  : `La factura sale de la cobranza y sus ${viajes} viajes vuelven a quedar por facturar.`}{' '}
                            No se borra: queda en «Facturas anuladas» y se puede
                            restaurar.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor={motivoId}>Motivo</Label>
                        <Textarea
                            id={motivoId}
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            placeholder="Ej.: se emitió con el RUC equivocado; la reemplaza la E001-14700"
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
