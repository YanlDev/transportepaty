import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ViajeController';
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
    viaje: { id: number; numero_gr: string };
    trigger: React.ReactNode;
};

export function DeleteViajeDialog({ viaje, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroyViaje, processing } = useForm();

    const eliminar = () => {
        destroyViaje(destroy(viaje.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar viaje</DialogTitle>
                    <DialogDescription>
                        ¿Seguro que deseas eliminar la GR{' '}
                        <span className="font-medium text-foreground">
                            {viaje.numero_gr}
                        </span>
                        ? Esta acción no se puede deshacer; si la vuelves a
                        subir, se registra como un viaje nuevo.
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
