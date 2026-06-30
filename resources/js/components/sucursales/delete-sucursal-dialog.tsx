import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/SucursalController';
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
import type { SucursalListItem } from '@/types/fleet';

type Props = {
    sucursal: SucursalListItem;
    trigger: React.ReactNode;
};

export function DeleteSucursalDialog({ sucursal, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroySucursal, processing } = useForm();

    const tieneDependientes =
        sucursal.vehiculos_count > 0 || sucursal.conductores_count > 0;

    const eliminar = () => {
        destroySucursal(destroy(sucursal.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar sucursal</DialogTitle>
                    <DialogDescription>
                        {tieneDependientes ? (
                            <>
                                No puedes eliminar{' '}
                                <span className="font-medium text-foreground">
                                    {sucursal.nombre}
                                </span>{' '}
                                porque tiene {sucursal.vehiculos_count}{' '}
                                vehículo(s) y {sucursal.conductores_count}{' '}
                                conductor(es) asignados. Reasígnalos primero.
                            </>
                        ) : (
                            <>
                                ¿Seguro que deseas eliminar{' '}
                                <span className="font-medium text-foreground">
                                    {sucursal.nombre}
                                </span>
                                ? Esta acción no se puede deshacer.
                            </>
                        )}
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
                        disabled={processing || tieneDependientes}
                    >
                        {processing && <Spinner />}
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
