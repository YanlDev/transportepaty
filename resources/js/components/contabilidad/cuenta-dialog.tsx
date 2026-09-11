import { useForm } from '@inertiajs/react';
import { useId } from 'react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/CuentaBancariaController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { CuentaBancaria } from '@/types/contabilidad';
import type { EnumOption } from '@/types/fleet';

type FormData = {
    banco: string;
    alias: string;
    numero_cuenta: string;
    cci: string;
    moneda: string;
    activa: boolean;
    notas: string;
};

export function CuentaDialog({
    open,
    onOpenChange,
    cuenta,
    monedas,
}: {
    open: boolean;
    onOpenChange: (abierto: boolean) => void;
    cuenta: CuentaBancaria | null;
    monedas: EnumOption[];
}) {
    // Quien llama monta este diálogo con `key` por cuenta, así que el estado
    // inicial de `useForm` —que solo se lee al montar— ya es el correcto y no
    // hace falta resembrarlo con un efecto al abrir.
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        banco: cuenta?.banco ?? '',
        alias: cuenta?.alias ?? '',
        numero_cuenta: cuenta?.numero_cuenta ?? '',
        cci: cuenta?.cci ?? '',
        moneda: cuenta?.moneda ?? 'PEN',
        activa: cuenta?.activa ?? true,
        notas: cuenta?.notas ?? '',
    });

    const activaId = useId();

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        const opciones = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (cuenta) {
            put(update(cuenta.id).url, opciones);
        } else {
            post(store().url, opciones);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {cuenta ? 'Editar cuenta' : 'Nueva cuenta'}
                    </DialogTitle>
                    <DialogDescription>
                        El alias es lo que se ve en la tabla de cobranza; el
                        número queda para conciliar contra el extracto.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Banco" error={errors.banco} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.banco}
                                    onChange={(e) =>
                                        setData('banco', e.target.value)
                                    }
                                    placeholder="BCP"
                                />
                            )}
                        </Field>
                        <Field
                            label="Alias"
                            error={errors.alias}
                            required
                            ayuda="Nombre corto para la tabla."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.alias}
                                    onChange={(e) =>
                                        setData('alias', e.target.value)
                                    }
                                    placeholder="BCP Soles"
                                />
                            )}
                        </Field>
                        <Field
                            label="N° de cuenta"
                            error={errors.numero_cuenta}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.numero_cuenta}
                                    onChange={(e) =>
                                        setData('numero_cuenta', e.target.value)
                                    }
                                    placeholder="191-1234567-0-89"
                                />
                            )}
                        </Field>
                        <Field label="CCI" error={errors.cci}>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.cci}
                                    onChange={(e) =>
                                        setData('cci', e.target.value)
                                    }
                                    placeholder="00219100123456708912"
                                />
                            )}
                        </Field>
                        <Field label="Moneda" error={errors.moneda} required>
                            {(id) => (
                                <Select
                                    value={data.moneda}
                                    onValueChange={(valor) =>
                                        setData('moneda', valor)
                                    }
                                >
                                    <SelectTrigger id={id} className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {monedas.map((moneda) => (
                                            <SelectItem
                                                key={moneda.value}
                                                value={moneda.value}
                                            >
                                                {moneda.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </Field>
                    </div>

                    <Field label="Notas" error={errors.notas}>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                value={data.notas}
                                onChange={(e) =>
                                    setData('notas', e.target.value)
                                }
                            />
                        )}
                    </Field>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id={activaId}
                            checked={data.activa}
                            onCheckedChange={(marcado) =>
                                setData('activa', marcado === true)
                            }
                        />
                        <Label
                            htmlFor={activaId}
                            className="text-sm font-normal"
                        >
                            Activa
                            <span className="block text-xs text-muted-foreground">
                                Una cuenta cerrada deja de ofrecerse al marcar
                                pagos, pero sigue apareciendo en las facturas
                                que ya cobró.
                            </span>
                        </Label>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
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
