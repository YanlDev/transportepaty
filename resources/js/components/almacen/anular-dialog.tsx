import { useForm } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

/**
 * Anular un documento del almacén. No se borra: queda a la vista con el
 * motivo, y sus movimientos salen del kardex. Si eso deja alguna salida sin
 * stock, el backend lo rechaza y el error se muestra acá.
 */
export function AnularDialog({
    url,
    titulo,
    descripcion,
    etiqueta = 'Anular',
}: {
    url: string;
    titulo: string;
    descripcion: string;
    etiqueta?: string;
}) {
    const [abierto, setAbierto] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({ motivo: '' });
    const errores = errors as Record<string, string | undefined>;

    const anular = (evento: React.FormEvent) => {
        evento.preventDefault();
        // Va en un portal, pero React propaga el submit por el árbol: sin
        // esto, enviaría también el formulario de la pantalla de atrás.
        evento.stopPropagation();

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAbierto(false);
            },
        });
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(valor) => {
                setAbierto(valor);

                if (!valor) {
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" className="text-destructive">
                    <Ban className="size-4" />
                    {etiqueta}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={anular}>
                    <DialogHeader>
                        <DialogTitle>{titulo}</DialogTitle>
                        <DialogDescription>{descripcion}</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor="motivo-anulacion">Motivo</Label>
                        <Textarea
                            id="motivo-anulacion"
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            placeholder="Por qué se anula"
                            rows={3}
                        />
                        <InputError message={errores.motivo} />
                        <InputError message={errores.stock} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {etiqueta}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
