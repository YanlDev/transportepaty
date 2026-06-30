import { useForm } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/CargaCombustibleController';
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
import type { CargaCombustible } from '@/types/fleet';

type Props = {
    vehiculoId: number;
    carga: CargaCombustible;
    onClose: () => void;
};

export function EliminarCargaDialog({ vehiculoId, carga, onClose }: Props) {
    const { delete: destroyCarga, processing } = useForm();

    const eliminar = () => {
        destroyCarga(destroy([vehiculoId, carga.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar carga</DialogTitle>
                    <DialogDescription>
                        ¿Seguro que deseas eliminar la carga del{' '}
                        <span className="font-medium text-foreground">
                            {formatearFechaHora(carga.fecha_carga)}
                        </span>
                        ? Esta acción no se puede deshacer y recalculará el
                        rendimiento de las cargas vecinas.
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
