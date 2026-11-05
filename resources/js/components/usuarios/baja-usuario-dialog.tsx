import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { darDeBaja } from '@/actions/App/Http/Controllers/UserController';
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
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { UserListItem } from '@/types/fleet';

/**
 * Dar de baja a quien deja la empresa: no entra más, no recibe alertas ni
 * aparece en las listas, pero su nombre sigue en el historial. Se puede
 * reactivar.
 */
export function BajaUsuarioDialog({
    usuario,
    trigger,
}: {
    usuario: UserListItem;
    trigger: React.ReactNode;
}) {
    const [abierto, setAbierto] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        motivo: '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(darDeBaja(usuario.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAbierto(false);
            },
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="md:max-w-md">
                <DialogHeader>
                    <DialogTitle>Dar de baja a {usuario.name}</DialogTitle>
                    <DialogDescription>
                        La cuenta ya no entra ni recibe alertas. No se borra: su
                        nombre sigue en el historial, y se puede reactivar.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id={`baja-${usuario.id}`}
                    onSubmit={enviar}
                    className="flex flex-col gap-4"
                >
                    <Field label="Motivo" error={errors.motivo} required>
                        {(id) => (
                            <Input
                                id={id}
                                autoFocus
                                placeholder="Ej.: dejó la empresa"
                                value={data.motivo}
                                onChange={(evento) =>
                                    setData('motivo', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </form>
                <DialogFooter>
                    <Button
                        type="submit"
                        form={`baja-${usuario.id}`}
                        variant="destructive"
                        disabled={processing}
                    >
                        Dar de baja
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
