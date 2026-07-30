import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/AsignacionController';
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
import { formatearPlaca } from '@/lib/format';
import type { AsignacionListItem } from '@/types/fleet';

type Props = {
    asignacion: AsignacionListItem;
    trigger: React.ReactNode;
};

export function DeleteAsignacionDialog({ asignacion, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroyAsignacion, processing } = useForm();

    const eliminar = () => {
        destroyAsignacion(destroy(asignacion.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar asignación</DialogTitle>
                    <DialogDescription>
                        Se borrará el registro de{' '}
                        <span className="font-medium text-foreground">
                            {formatearPlaca(asignacion.tracto.placa)}
                        </span>{' '}
                        con{' '}
                        <span className="font-medium text-foreground">
                            {asignacion.conductor.nombre_completo}
                        </span>{' '}
                        y desaparecerá del historial. Si la unidad simplemente
                        cambió de conductor, usa <strong>Liberar</strong> en vez
                        de eliminar.
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
