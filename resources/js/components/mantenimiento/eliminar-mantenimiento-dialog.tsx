import { useForm } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/MantenimientoController';
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
import type { Mantenimiento } from '@/types/fleet';

type Props = {
    vehiculoId: number;
    mantenimiento: Mantenimiento;
    onClose: () => void;
};

export function EliminarMantenimientoDialog({
    vehiculoId,
    mantenimiento,
    onClose,
}: Props) {
    const { delete: destroyAction, processing } = useForm({});

    const submit = () => {
        destroyAction(destroy([vehiculoId, mantenimiento.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar mantenimiento</DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de eliminar este mantenimiento? Esta
                        acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-border bg-muted/50 p-3 text-sm">
                    <p>
                        <span className="font-medium text-foreground">
                            {mantenimiento.items
                                .map((i) => i.nombre)
                                .join(', ') || 'Mantenimiento'}
                        </span>
                    </p>
                    <p className="text-muted-foreground">
                        {mantenimiento.odometro.toLocaleString('es-PE')} km ·{' '}
                        {new Date(
                            mantenimiento.fecha_realizado,
                        ).toLocaleDateString('es-PE')}
                        {mantenimiento.proveedor &&
                            ` · ${mantenimiento.proveedor}`}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={submit}
                    >
                        {processing && <Spinner />}
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
