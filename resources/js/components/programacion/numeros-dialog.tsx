import { useForm } from '@inertiajs/react';
import { actualizarNumeros } from '@/actions/App/Http/Controllers/ProgramacionController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { ProgramacionTarjeta } from '@/types/programacion';

type FormData = {
    telefono: string;
    telefono_alterno: string;
    whatsapp_adicional: string;
};

/**
 * Los números a los que se avisa de esta salida, corregibles ahí mismo: el
 * celular equivocado se descubre justo cuando hay que mandar el aviso, y
 * mandar a la persona al padrón de conductores en ese momento es perder el
 * hilo de lo que estaba haciendo.
 *
 * Dejar un campo vacío borra ese número. Los dos primeros son del conductor y
 * quedan en su ficha —valen para todas sus salidas—; el adicional es de esta
 * programación, y se dice en pantalla para que nadie lo descubra después.
 */
export function NumerosDialog({
    tarjeta,
    open,
    onOpenChange,
}: {
    tarjeta: ProgramacionTarjeta;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, patch, processing, errors } = useForm<FormData>({
        telefono: tarjeta.telefono ?? '',
        telefono_alterno: tarjeta.telefono_alterno ?? '',
        whatsapp_adicional: tarjeta.whatsapp_adicional ?? '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        patch(actualizarNumeros(tarjeta.id).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Números para avisar</DialogTitle>
                    <DialogDescription>
                        {tarjeta.conductor} · {tarjeta.placa}. Deja un campo
                        vacío para quitar ese número.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <Field
                        label="Celular del conductor"
                        error={errors.telefono}
                        ayuda="Queda en su ficha: vale para todas sus salidas."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="tel"
                                inputMode="tel"
                                value={data.telefono}
                                onChange={(evento) =>
                                    setData('telefono', evento.target.value)
                                }
                                placeholder="999888777"
                            />
                        )}
                    </Field>

                    <Field
                        label="Celular alterno"
                        error={errors.telefono_alterno}
                        ayuda="El otro número que usa, si tiene."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="tel"
                                inputMode="tel"
                                value={data.telefono_alterno}
                                onChange={(evento) =>
                                    setData(
                                        'telefono_alterno',
                                        evento.target.value,
                                    )
                                }
                                placeholder="999888777"
                            />
                        )}
                    </Field>

                    <Field
                        label="WhatsApp adicional"
                        error={errors.whatsapp_adicional}
                        ayuda="Solo para esta salida: el dueño de la unidad, un apoyo."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="tel"
                                inputMode="tel"
                                value={data.whatsapp_adicional}
                                onChange={(evento) =>
                                    setData(
                                        'whatsapp_adicional',
                                        evento.target.value,
                                    )
                                }
                                placeholder="999888777"
                            />
                        )}
                    </Field>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
