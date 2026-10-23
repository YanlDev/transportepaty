import { useForm } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { ItemCarreta } from '@/types/equipamiento';

type Formulario = {
    fecha: string;
    observacion: string;
    items: { id: number; buenas: number; danadas: number }[];
};

/**
 * El conteo en base, con botones − y + para contar con el pulgar. Arranca
 * con lo del conteo anterior: lo habitual es que cambie poco.
 */
export function ConteoDialog({
    carretaId,
    placa,
    items,
    hoy,
    abierto,
    onAbiertoChange,
}: {
    carretaId: number;
    placa: string;
    items: ItemCarreta[];
    hoy: string;
    abierto: boolean;
    onAbiertoChange: (abierto: boolean) => void;
}) {
    const { data, setData, post, processing, errors } = useForm<Formulario>({
        fecha: hoy,
        observacion: '',
        items: items.map((item) => ({
            id: item.id,
            buenas: item.buenas ?? 0,
            danadas: item.danadas ?? 0,
        })),
    });

    const cambiar = (
        indice: number,
        campo: 'buenas' | 'danadas',
        valor: number,
    ) => {
        setData(
            'items',
            data.items.map((item, i) =>
                i === indice ? { ...item, [campo]: Math.max(0, valor) } : item,
            ),
        );
    };

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        post(store(carretaId).url, {
            preserveScroll: true,
            onSuccess: () => onAbiertoChange(false),
        });
    };

    return (
        <Dialog open={abierto} onOpenChange={onAbiertoChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Contar equipamiento</DialogTitle>
                        <DialogDescription>
                            {placa}: cuántas hay de cada una. Las dañadas no
                            cuentan para el mínimo.
                        </DialogDescription>
                    </DialogHeader>

                    <InputError
                        message={
                            (errors as Record<string, string | undefined>).items
                        }
                    />

                    <div className="flex flex-col divide-y rounded-lg border">
                        {items.map((item, indice) => (
                            <div
                                key={item.id}
                                className="flex flex-col gap-2 p-3"
                            >
                                <div className="flex items-baseline justify-between gap-2">
                                    <span className="text-sm font-medium">
                                        {item.nombre}
                                    </span>
                                    {item.minimo !== null && (
                                        <span className="text-xs text-muted-foreground">
                                            mínimo {item.minimo}
                                        </span>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Contador
                                        id={`buenas-${item.id}`}
                                        etiqueta="Buenas"
                                        item={item.nombre}
                                        valor={data.items[indice].buenas}
                                        onCambio={(valor) =>
                                            cambiar(indice, 'buenas', valor)
                                        }
                                    />
                                    <Contador
                                        id={`danadas-${item.id}`}
                                        etiqueta="Dañadas"
                                        item={item.nombre}
                                        valor={data.items[indice].danadas}
                                        onCambio={(valor) =>
                                            cambiar(indice, 'danadas', valor)
                                        }
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="conteo-fecha">Fecha</Label>
                        <Input
                            id="conteo-fecha"
                            type="date"
                            max={hoy}
                            value={data.fecha}
                            onChange={(e) => setData('fecha', e.target.value)}
                            className="w-44"
                        />
                        <InputError message={errors.fecha} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="conteo-observacion">
                            Observación (opcional)
                        </Label>
                        <Textarea
                            id="conteo-observacion"
                            rows={2}
                            value={data.observacion}
                            onChange={(e) =>
                                setData('observacion', e.target.value)
                            }
                        />
                        <InputError message={errors.observacion} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onAbiertoChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar conteo
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Contador({
    id,
    etiqueta,
    item,
    valor,
    onCambio,
}: {
    id: string;
    etiqueta: string;
    /** Para que cada botón diga de qué ítem es. */
    item: string;
    valor: number;
    onCambio: (valor: number) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs text-muted-foreground">
                {etiqueta}
            </Label>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-10 shrink-0"
                    onClick={() => onCambio(valor - 1)}
                    aria-label={`${etiqueta} de ${item}: una menos`}
                >
                    <Minus className="size-4" />
                </Button>
                <Input
                    id={id}
                    inputMode="numeric"
                    value={String(valor)}
                    onChange={(e) =>
                        onCambio(Number(e.target.value.replace(/\D/g, '')) || 0)
                    }
                    className="h-10 text-center text-base tabular-nums"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-10 shrink-0"
                    onClick={() => onCambio(valor + 1)}
                    aria-label={`${etiqueta} de ${item}: una más`}
                >
                    <Plus className="size-4" />
                </Button>
            </div>
        </div>
    );
}
