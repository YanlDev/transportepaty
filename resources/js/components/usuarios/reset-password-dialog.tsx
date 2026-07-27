import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { updatePassword } from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    usuarioId: number;
    trigger: React.ReactNode;
};

export function ResetPasswordDialog({ usuarioId, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const passwordId = useId();
    const confirmId = useId();

    const { data, setData, put, processing, errors, reset, clearErrors } =
        useForm({
            password: '',
            password_confirmation: '',
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        put(updatePassword(usuarioId).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Restablecer contraseña</DialogTitle>
                        <DialogDescription>
                            Define una nueva contraseña para este usuario. Se
                            aplicará de inmediato.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor={passwordId}>Nueva contraseña</Label>
                            <Input
                                id={passwordId}
                                type="password"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                placeholder="••••••••"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor={confirmId}>
                                Confirmar contraseña
                            </Label>
                            <Input
                                id={confirmId}
                                type="password"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                placeholder="••••••••"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-navy-800 hover:bg-navy-900"
                        >
                            {processing && <Spinner />}
                            Guardar contraseña
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
