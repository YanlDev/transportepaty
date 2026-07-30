import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { liberar } from '@/actions/App/Http/Controllers/AsignacionController';
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

export function LiberarAsignacionDialog({ asignacion, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { post, processing } = useForm();

    const confirmar = () => {
        post(liberar(asignacion.id).url, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Liberar unidad</DialogTitle>
                    <DialogDescription>
                        El tracto{' '}
                        <span className="font-medium text-foreground">
                            {formatearPlaca(asignacion.tracto.placa)}
                        </span>
                        {asignacion.carreta && (
                            <>
                                , la carreta{' '}
                                <span className="font-medium text-foreground">
                                    {formatearPlaca(asignacion.carreta.placa)}
                                </span>
                            </>
                        )}{' '}
                        y{' '}
                        <span className="font-medium text-foreground">
                            {asignacion.conductor.nombre_completo}
                        </span>{' '}
                        quedarán disponibles para una nueva asignación. La
                        asignación actual no se borra: pasa al historial con la
                        fecha de hoy.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" type="button">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button onClick={confirmar} disabled={processing}>
                        {processing && <Spinner />}
                        Liberar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
