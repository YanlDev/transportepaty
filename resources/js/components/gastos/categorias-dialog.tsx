import { router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/CategoriaGastoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { StatusBadge } from '@/components/ui/status-badge';
import type { CategoriaGastoAdministrada } from '@/types/gastos';

/**
 * Las categorías de gasto, que lleva gerencia. No se borran: la que ya no se
 * usa se desactiva y deja de ofrecerse al pedir.
 */
export function CategoriasDialog({
    open,
    onOpenChange,
    categorias,
}: {
    open: boolean;
    onOpenChange: (abierto: boolean) => void;
    categorias: CategoriaGastoAdministrada[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        nombre: '',
    });

    const agregar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(store().url, { preserveScroll: true, onSuccess: () => reset() });
    };

    const cambiarEstado = (categoria: CategoriaGastoAdministrada) =>
        router.put(
            update(categoria.id).url,
            { nombre: categoria.nombre, activa: !categoria.activa },
            { preserveScroll: true },
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Categorías de gasto</DialogTitle>
                    <DialogDescription>
                        Es en lo que cada área clasifica su vale. Una
                        desactivada ya no se ofrece al pedir, pero los vales que
                        la usaron la conservan.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={agregar} className="grid gap-1.5">
                    <div className="flex gap-2">
                        <Input
                            aria-label="Nueva categoría"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            placeholder="Nueva categoría"
                        />
                        <Button type="submit" disabled={processing}>
                            <Plus className="size-4" />
                            Agregar
                        </Button>
                    </div>
                    <InputError message={errors.nombre} />
                </form>

                <ul className="divide-y rounded-xl border">
                    {categorias.map((categoria) => (
                        <li
                            key={categoria.id}
                            className="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {categoria.nombre}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {categoria.vales === 1
                                        ? '1 vale'
                                        : `${categoria.vales} vales`}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <StatusBadge
                                    label={
                                        categoria.activa
                                            ? 'Activa'
                                            : 'Desactivada'
                                    }
                                    tone={
                                        categoria.activa ? 'success' : 'neutral'
                                    }
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => cambiarEstado(categoria)}
                                >
                                    {categoria.activa
                                        ? 'Desactivar'
                                        : 'Activar'}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
