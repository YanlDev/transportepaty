import { useForm } from '@inertiajs/react';
import { useState } from 'react';
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
    /** Ruta `destroy` de la entidad, ya resuelta por Wayfinder. */
    url: string;
    titulo: string;
    /** El cuerpo del aviso: qué se borra y si se puede deshacer. */
    descripcion: React.ReactNode;
    trigger: React.ReactNode;
    etiquetaAccion?: string;
};

/**
 * El paso de «¿seguro?» antes de un DELETE. Cada módulo lo envuelve con su
 * propio texto —lo único que cambia entre un vehículo y un usuario— y esta
 * capa se queda con lo demás: abrir, enviar, cerrar al terminar.
 */
export function ConfirmarBorradoDialog({
    url,
    titulo,
    descripcion,
    trigger,
    etiquetaAccion = 'Eliminar',
}: Props) {
    const [open, setOpen] = useState(false);
    const { delete: eliminarRecurso, processing } = useForm();

    const eliminar = () => {
        eliminarRecurso(url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{titulo}</DialogTitle>
                    <DialogDescription>{descripcion}</DialogDescription>
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
                        {etiquetaAccion}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Resalta el nombre de lo que se está por borrar dentro del aviso. */
export function Resaltado({ children }: { children: React.ReactNode }) {
    return <span className="font-medium text-foreground">{children}</span>;
}
