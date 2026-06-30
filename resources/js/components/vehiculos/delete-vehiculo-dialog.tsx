import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/VehiculoController';
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
import { Spinner } from '@/components/ui/spinner';

type Props = {
    vehiculo: { id: number; placa: string; marca: string; modelo: string };
    trigger: React.ReactNode;
};

export function DeleteVehiculoDialog({ vehiculo, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroyVehiculo, processing } = useForm();

    const eliminar = () => {
        destroyVehiculo(destroy(vehiculo.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar vehículo</DialogTitle>
                    <DialogDescription>
                        ¿Seguro que deseas eliminar{' '}
                        <span className="font-medium text-foreground">
                            {vehiculo.marca} {vehiculo.modelo} ({vehiculo.placa}
                            )
                        </span>
                        ? Podrás recuperarlo más adelante, pero dejará de
                        aparecer en la lista.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" type="button">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={eliminar}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
