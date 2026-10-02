import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { marcarNoFacturable } from '@/actions/App/Http/Controllers/ContabilidadController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    viaje: { id: number; numero_gr: string };
    trigger: React.ReactNode;
};

/**
 * Saca una GR de la cobranza sin anularla: la cajita de 0.2 TNE que viajó con
 * la carga grande, una cortesía. Ante SUNAT sigue valiendo y el viaje cuenta
 * en la operación; solo deja de aparecer como «por facturar». El motivo es
 * opcional, pero es lo que dentro de tres meses explica por qué no se cobró.
 */
export function NoFacturableDialog({ viaje, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const motivoId = useId();

    const { data, setData, patch, processing, errors, reset, clearErrors } =
        useForm({ no_facturable: true, motivo: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        patch(marcarNoFacturable(viaje.id).url, {
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
                            No facturar la GR {viaje.numero_gr}
                        </DialogTitle>
                        <DialogDescription>
                            La GR sale de lo pendiente por facturar. No se
                            anula: sigue contando como viaje en la operación. Se
                            puede volver a facturar después.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor={motivoId}>Motivo (opcional)</Label>
                        <Input
                            id={motivoId}
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            placeholder="Ej.: caja de 0.2 TNE que viajó con la carga"
                            maxLength={255}
                        />
                        <InputError
                            message={errors.motivo ?? errors.no_facturable}
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            No se factura
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
