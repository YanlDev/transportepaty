import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/FacturaController';
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
 * Le suma otra factura a un viaje que ya tiene una: la estadía aparte del
 * flete, o un cobro partido. Va en un diálogo desde las acciones de la fila y
 * no como un enlace dentro de la celda del N° factura, donde no cabía y se
 * montaba sobre la fecha de emisión.
 */
export function OtraFacturaDialog({ viaje, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const numeroId = useId();

    const {
        data,
        setData,
        transform,
        post,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({ numero: '', viaje_ids: [viaje.id] });

    transform((datos) => ({ ...datos, numero: datos.numero.trim() }));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(store().url, {
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
                            Otra factura para la GR {viaje.numero_gr}
                        </DialogTitle>
                        <DialogDescription>
                            Se suma a la que ya tiene, sin reemplazarla: por
                            ejemplo, la estadía aparte del flete. El monto y las
                            fechas se completan después en la tabla.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor={numeroId}>N° de factura</Label>
                        <Input
                            id={numeroId}
                            value={data.numero}
                            onChange={(e) =>
                                setData('numero', e.target.value.toUpperCase())
                            }
                            placeholder="F001-00123"
                            maxLength={30}
                            className="font-mono"
                            autoFocus
                            required
                        />
                        <InputError
                            message={errors.numero ?? errors.viaje_ids}
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={processing || data.numero.trim() === ''}
                        >
                            {processing && <Spinner />}
                            Registrar factura
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
