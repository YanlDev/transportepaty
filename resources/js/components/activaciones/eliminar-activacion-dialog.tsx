import { useForm } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/ActivacionController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatearFechaHora } from '@/lib/format';
import type { Activacion } from '@/types/fleet';

type Props = {
    vehiculoId: number;
    activacion: Activacion;
    onClose: () => void;
};

export function EliminarActivacionDialog({
    vehiculoId,
    activacion,
    onClose,
}: Props) {
    const { delete: destroyActivacion, processing } = useForm();

    const eliminar = () => {
        destroyActivacion(destroy([vehiculoId, activacion.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar activación</DialogTitle>
                    <DialogDescription>
                        ¿Seguro que deseas eliminar la activación del{' '}
                        <span className="font-medium text-foreground">
                            {formatearFechaHora(activacion.fecha)}
                        </span>
                        ? Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
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
