import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { anular } from '@/actions/App/Http/Controllers/ViajeController';
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
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    viaje: { id: number; numero_gr: string };
    trigger: React.ReactNode;
};

/**
 * Marca la GR como anulada ante SUNAT. No se borra: queda en el listado, en
 * gris, y deja de contar como viaje en el tablero, la cobranza y las fichas.
 * El motivo es opcional, pero es lo que después explica la fila gris.
 */
export function AnularViajeDialog({ viaje, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const motivoId = useId();

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({ motivo: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(anular(viaje.id).url, {
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
                        <DialogTitle>Anular GR {viaje.numero_gr}</DialogTitle>
                        <DialogDescription>
                            La GR queda en el listado, en gris, pero deja de
                            contar como viaje en el tablero, la cobranza y las
                            fichas. Se puede reactivar después.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor={motivoId}>Motivo (opcional)</Label>
                        <Textarea
                            id={motivoId}
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            placeholder="Ej.: placa del tracto mal escrita, reemplazada por EG03-…"
                            rows={3}
                            maxLength={500}
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
                            Anular GR
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
