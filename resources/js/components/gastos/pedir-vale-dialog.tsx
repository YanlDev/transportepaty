import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/ValeGastoController';
import { CampoArchivo } from '@/components/campo-archivo';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearPlaca } from '@/lib/format';
import type { EnumOption } from '@/types/fleet';
import type { CategoriaGasto } from '@/types/gastos';

const SIN_UNIDAD = 'ninguna';

type FormData = {
    area: string;
    categoria_gasto_id: string;
    concepto: string;
    motivo: string;
    monto: string;
    beneficiario: string;
    vehiculo_id: string;
    sustento: File[];
};

/**
 * Pedir un vale de gasto: qué se paga, cuánto, a quién y para qué. El área
 * ya viene puesta con la de quien pide.
 */
export function PedirValeDialog({
    open,
    onOpenChange,
    areas,
    areaDelUsuario,
    categorias,
    vehiculos,
    maximoKb,
}: {
    open: boolean;
    onOpenChange: (abierto: boolean) => void;
    areas: EnumOption[];
    areaDelUsuario: string;
    categorias: CategoriaGasto[];
    vehiculos: { id: number; placa: string }[];
    maximoKb: number;
}) {
    const { data, setData, transform, post, processing, progress, errors } =
        useForm<FormData>({
            area: areaDelUsuario,
            categoria_gasto_id: '',
            concepto: '',
            motivo: '',
            monto: '',
            beneficiario: '',
            vehiculo_id: SIN_UNIDAD,
            sustento: [],
        });
    const [preparando, setPreparando] = useState(false);
    const errores = errors as Record<string, string | undefined>;

    transform((datos) => ({
        ...datos,
        vehiculo_id:
            datos.vehiculo_id === SIN_UNIDAD ? null : datos.vehiculo_id,
    }));

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(store().url, { preserveScroll: true });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Pedir vale de gasto</DialogTitle>
                    <DialogDescription>
                        El vale se pide antes de gastar: gerencia lo revisa y te
                        avisa cuando lo aprueba.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Área que pide"
                            error={errors.area}
                            required
                        >
                            {(id) => (
                                <Select
                                    value={data.area}
                                    onValueChange={(valor) =>
                                        setData('area', valor)
                                    }
                                >
                                    <SelectTrigger id={id} className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {areas.map((area) => (
                                            <SelectItem
                                                key={area.value}
                                                value={area.value}
                                            >
                                                {area.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                        <Field
                            label="Categoría"
                            error={errors.categoria_gasto_id}
                            required
                        >
                            {(id) => (
                                <Select
                                    value={data.categoria_gasto_id}
                                    onValueChange={(valor) =>
                                        setData('categoria_gasto_id', valor)
                                    }
                                >
                                    <SelectTrigger id={id} className="w-full">
                                        <SelectValue placeholder="Elige una" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categorias.map((categoria) => (
                                            <SelectItem
                                                key={categoria.id}
                                                value={String(categoria.id)}
                                            >
                                                {categoria.nombre}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    </div>

                    <Field
                        label="Qué se va a pagar"
                        error={errors.concepto}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.concepto}
                                onChange={(e) =>
                                    setData('concepto', e.target.value)
                                }
                                placeholder="Peajes de la ruta a Arequipa"
                            />
                        )}
                    </Field>

                    <Field label="Para qué" error={errors.motivo} required>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                value={data.motivo}
                                onChange={(e) =>
                                    setData('motivo', e.target.value)
                                }
                                placeholder="Por qué hace falta el gasto"
                            />
                        )}
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Monto (S/)" error={errors.monto} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={data.monto}
                                    onChange={(e) =>
                                        setData('monto', e.target.value)
                                    }
                                    placeholder="0.00"
                                />
                            )}
                        </Field>
                        <Field
                            label="A quién se le paga"
                            error={errors.beneficiario}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.beneficiario}
                                    onChange={(e) =>
                                        setData('beneficiario', e.target.value)
                                    }
                                    placeholder="Persona o empresa"
                                />
                            )}
                        </Field>
                    </div>

                    <Field
                        label="Unidad"
                        error={errors.vehiculo_id}
                        ayuda="Solo si el gasto es de una unidad."
                    >
                        {(id) => (
                            <Select
                                value={data.vehiculo_id}
                                onValueChange={(valor) =>
                                    setData('vehiculo_id', valor)
                                }
                            >
                                <SelectTrigger id={id} className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_UNIDAD}>
                                        Ninguna: gasto general del área
                                    </SelectItem>
                                    {vehiculos.map((vehiculo) => (
                                        <SelectItem
                                            key={vehiculo.id}
                                            value={String(vehiculo.id)}
                                        >
                                            {formatearPlaca(vehiculo.placa)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>

                    <div className="grid gap-1.5">
                        <span className="text-sm font-medium">
                            Sustento (opcional)
                        </span>
                        <CampoArchivo
                            archivos={data.sustento}
                            onArchivos={(lista) => setData('sustento', lista)}
                            multiple
                            perfil="comprobante"
                            maximoKb={maximoKb}
                            progreso={progress?.percentage ?? null}
                            onPreparando={setPreparando}
                            error={
                                errores.sustento ??
                                Object.entries(errores).find(([clave]) =>
                                    clave.startsWith('sustento.'),
                                )?.[1]
                            }
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={processing || preparando}
                        >
                            {processing && <Spinner />}
                            Pedir vale
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
