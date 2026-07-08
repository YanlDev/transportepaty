import { useForm } from '@inertiajs/react';
import { Plus, Power } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/ActivacionController';
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
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption } from '@/types/fleet';

type FormData = {
    fecha: string;
    kilometraje: string;
    resultado: string;
    observaciones: string;
};

type Props = {
    vehiculoId: number;
    resultados: EnumOption[];
    kilometrajeSugerido?: number;
    /** Compact icon-only trigger (for tight headers). */
    compacto?: boolean;
};

export function RegistrarActivacionDialog({
    vehiculoId,
    resultados,
    kilometrajeSugerido,
    compacto,
}: Props) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormData>({
            fecha: new Date().toISOString().slice(0, 10),
            kilometraje: kilometrajeSugerido ? String(kilometrajeSugerido) : '',
            resultado: 'sin_novedad',
            observaciones: '',
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(store(vehiculoId).url, {
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
            <DialogTrigger asChild>
                {compacto ? (
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        title="Registrar activación"
                    >
                        <Plus className="size-4" />
                    </Button>
                ) : (
                    <Button className="bg-emerald-800 hover:bg-emerald-900">
                        <Power className="size-4" />
                        Registrar activación
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Registrar activación periódica</DialogTitle>
                        <DialogDescription>
                            Deja constancia de que la unidad en reposo fue
                            encendida y conducida brevemente (10-15 min) para
                            prevenir su deterioro.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label>Fecha de la activación</Label>
                                <Input
                                    type="date"
                                    value={data.fecha}
                                    onChange={(e) =>
                                        setData('fecha', e.target.value)
                                    }
                                />
                                <InputError message={errors.fecha} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Odómetro (km) — opcional</Label>
                                <Input
                                    type="number"
                                    inputMode="numeric"
                                    value={data.kilometraje}
                                    onChange={(e) =>
                                        setData('kilometraje', e.target.value)
                                    }
                                    placeholder="Lectura del tablero"
                                />
                                <InputError message={errors.kilometraje} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Resultado</Label>
                            <Select
                                value={data.resultado}
                                onValueChange={(value) =>
                                    setData('resultado', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {resultados.map((r) => (
                                        <SelectItem key={r.value} value={r.value}>
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.resultado} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label>
                                Observaciones
                                {data.resultado === 'anomalia'
                                    ? ''
                                    : ' — opcional'}
                            </Label>
                            <Textarea
                                value={data.observaciones}
                                onChange={(e) =>
                                    setData('observaciones', e.target.value)
                                }
                                placeholder={
                                    data.resultado === 'anomalia'
                                        ? 'Describe la anomalía detectada (ruidos, luces, llantas, etc.)'
                                        : 'Arranque, luces de tablero, ruidos, estado de llantas…'
                                }
                                rows={3}
                            />
                            <InputError message={errors.observaciones} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            Registrar activación
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
