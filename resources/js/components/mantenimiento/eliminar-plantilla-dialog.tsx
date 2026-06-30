import { useForm } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/PlantillaMantenimientoController';
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
import type { PlantillaMantenimiento } from '@/types/fleet';

type Props = {
    plantilla: PlantillaMantenimiento;
    onClose: () => void;
};

export function EliminarPlantillaDialog({ plantilla, onClose }: Props) {
    const { delete: destroyPlantilla, processing } = useForm();

    const eliminar = () => {
        destroyPlantilla(destroy(plantilla.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar plantilla</DialogTitle>
                    <DialogDescription>
                        ¿Seguro que deseas eliminar{' '}
                        <span className="font-medium text-foreground">
                            {plantilla.nombre}
                        </span>
                        ? Los mantenimientos ya registrados conservan su
                        historial.
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
