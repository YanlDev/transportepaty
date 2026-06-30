import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/UserController';
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
import type { UserListItem } from '@/types/fleet';

type Props = {
    usuario: UserListItem;
    trigger: React.ReactNode;
};

export function DeleteUserDialog({ usuario, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroyUser, processing } = useForm();

    const eliminar = () => {
        destroyUser(destroy(usuario.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar usuario</DialogTitle>
                    <DialogDescription>
                        ¿Seguro que deseas eliminar la cuenta de{' '}
                        <span className="font-medium text-foreground">
                            {usuario.name}
                        </span>
                        ? Esta acción no se puede deshacer.
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
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
