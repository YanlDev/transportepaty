import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ImportacionDisponibilidadController';
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
import { formatearFecha } from '@/lib/format';

type Props = {
    importacion: { id: number; fecha: string };
    trigger: React.ReactNode;
};

export function DeleteImportacionDialog({ importacion, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroyImportacion, processing } = useForm();

    const eliminar = () => {
        destroyImportacion(destroy(importacion.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Descartar importación</DialogTitle>
                    <DialogDescription>
                        ¿Seguro que deseas descartar la importación del{' '}
                        <span className="font-medium text-foreground">
                            {formatearFecha(importacion.fecha)}
                        </span>
                        ? Se borrará el archivo y su previsualización. Esta
                        acción no se puede deshacer.
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
                        Descartar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
