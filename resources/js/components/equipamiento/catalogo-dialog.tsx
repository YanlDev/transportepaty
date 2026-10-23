import { useForm } from '@inertiajs/react';
import { Plus, SlidersHorizontal, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { actualizarCatalogo } from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
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
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { ItemCatalogo } from '@/types/equipamiento';

type Fila = { id: number | null; nombre: string; minimo: string };

/**
 * Qué se cuenta en cada carreta y cuántas debe llevar como mínimo. El mínimo
 * es igual para todas; en blanco, el ítem se cuenta pero no avisa faltantes.
 * Quitar un ítem no borra los conteos anteriores.
 */
export function CatalogoDialog({ catalogo }: { catalogo: ItemCatalogo[] }) {
    const [abierto, setAbierto] = useState(false);

    const { data, setData, put, transform, processing, errors } = useForm<{
        items: Fila[];
    }>({
        items: catalogo.map((item) => ({
            id: item.id,
            nombre: item.nombre,
            minimo: item.minimo === null ? '' : String(item.minimo),
        })),
    });

    const cambiar = (indice: number, cambios: Partial<Fila>) =>
        setData(
            'items',
            data.items.map((fila, i) =>
                i === indice ? { ...fila, ...cambios } : fila,
            ),
        );

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            items: datos.items.map((fila) => ({
                id: fila.id,
                nombre: fila.nombre,
                minimo: fila.minimo === '' ? null : Number(fila.minimo),
            })),
        }));

        put(actualizarCatalogo().url, {
            preserveScroll: true,
            onSuccess: () => setAbierto(false),
        });
    };

    const error = (indice: number, campo: string): string | undefined =>
        (errors as Record<string, string | undefined>)[
            `items.${indice}.${campo}`
        ];

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <SlidersHorizontal className="size-4" />
                    Ítems y mínimos
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Equipamiento de las carretas</DialogTitle>
                        <DialogDescription>
                            Lo que se cuenta y el mínimo que debe llevar cada
                            carreta. Sin mínimo, se cuenta pero no avisa.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-[minmax(0,1fr)_5rem_2.25rem] gap-2 text-xs text-muted-foreground">
                        <span>Ítem</span>
                        <span>Mínimo</span>
                    </div>
                    <div className="-mt-2 flex flex-col gap-2">
                        {data.items.map((fila, indice) => (
                            <div
                                key={fila.id ?? `nuevo-${indice}`}
                                className="grid grid-cols-[minmax(0,1fr)_5rem_2.25rem] gap-2"
                            >
                                <Input
                                    aria-label="Ítem"
                                    value={fila.nombre}
                                    onChange={(e) =>
                                        cambiar(indice, {
                                            nombre: e.target.value,
                                        })
                                    }
                                />
                                <Input
                                    aria-label={`Mínimo de ${fila.nombre}`}
                                    inputMode="numeric"
                                    placeholder="—"
                                    value={fila.minimo}
                                    onChange={(e) =>
                                        cambiar(indice, {
                                            minimo: e.target.value.replace(
                                                /\D/g,
                                                '',
                                            ),
                                        })
                                    }
                                    className="text-center tabular-nums"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Quitar ${fila.nombre}`}
                                    disabled={data.items.length === 1}
                                    onClick={() =>
                                        setData(
                                            'items',
                                            data.items.filter(
                                                (_, i) => i !== indice,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                                <InputError
                                    className="col-span-full"
                                    message={
                                        error(indice, 'nombre') ??
                                        error(indice, 'minimo')
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="self-start"
                        onClick={() =>
                            setData('items', [
                                ...data.items,
                                { id: null, nombre: '', minimo: '' },
                            ])
                        }
                    >
                        <Plus className="size-4" />
                        Agregar ítem
                    </Button>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setAbierto(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
