import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/VehiculoFotoController';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { EnumOption } from '@/types/fleet';

type Props = {
    vehiculoId: number;
    posiciones: EnumOption[];
    trigger?: React.ReactNode;
};

export function AgregarFotoDialog({ vehiculoId, posiciones, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<{ posicion: string; archivo: File | null }>({
            posicion: posiciones[0]?.value ?? 'otro',
            archivo: null,
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(store(vehiculoId).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset();

                if (fileInput.current) {
                    fileInput.current.value = '';
                }

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
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button size="sm" variant="outline">
                        <Plus className="size-4" />
                        Agregar foto
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Agregar foto</DialogTitle>
                        <DialogDescription>
                            Sube una imagen del vehículo e indica la posición.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-1.5">
                            <Label>Posición</Label>
                            <Select
                                value={data.posicion}
                                onValueChange={(value) =>
                                    setData('posicion', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {posiciones.map((posicion) => (
                                        <SelectItem
                                            key={posicion.value}
                                            value={posicion.value}
                                        >
                                            {posicion.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.posicion} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Imagen</Label>
                            <Input
                                ref={fileInput}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(e) =>
                                    setData(
                                        'archivo',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={errors.archivo} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            Subir foto
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
