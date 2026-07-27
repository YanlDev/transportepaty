import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/VehiculoDocumentoController';
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

type DocumentoForm = {
    tipo: string;
    numero: string;
    fecha_emision: string;
    fecha_vencimiento: string;
    archivo: File | null;
};

type Props = {
    vehiculoId: number;
    tipos: EnumOption[];
};

export function AgregarDocumentoDialog({ vehiculoId, tipos }: Props) {
    const [open, setOpen] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<DocumentoForm>({
            tipo: tipos[0]?.value ?? 'otro',
            numero: '',
            fecha_emision: '',
            fecha_vencimiento: '',
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
                <Button size="sm" variant="outline">
                    <Plus className="size-4" />
                    Agregar documento
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Agregar documento</DialogTitle>
                        <DialogDescription>
                            Sube el documento escaneado (imagen o PDF). La fecha
                            de vencimiento es opcional.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-1.5">
                            <Label>Tipo de documento</Label>
                            <Select
                                value={data.tipo}
                                onValueChange={(value) =>
                                    setData('tipo', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {tipos.map((tipo) => (
                                        <SelectItem
                                            key={tipo.value}
                                            value={tipo.value}
                                        >
                                            {tipo.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.tipo} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Número (opcional)</Label>
                            <Input
                                value={data.numero}
                                onChange={(e) =>
                                    setData('numero', e.target.value)
                                }
                                placeholder="N.° de documento / póliza"
                            />
                            <InputError message={errors.numero} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-1.5">
                                <Label>Emisión (opcional)</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_emision}
                                    onChange={(e) =>
                                        setData('fecha_emision', e.target.value)
                                    }
                                />
                                <InputError message={errors.fecha_emision} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Vencimiento (opcional)</Label>
                                <Input
                                    type="date"
                                    value={data.fecha_vencimiento}
                                    onChange={(e) =>
                                        setData(
                                            'fecha_vencimiento',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.fecha_vencimiento}
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Archivo (imagen o PDF)</Label>
                            <Input
                                ref={fileInput}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
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
                            className="bg-navy-800 hover:bg-navy-900"
                        >
                            {processing && <Spinner />}
                            Subir documento
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
