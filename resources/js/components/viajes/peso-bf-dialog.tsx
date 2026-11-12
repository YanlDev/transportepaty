import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { actualizarPeso } from '@/actions/App/Http/Controllers/ViajeController';
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
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatearPlaca } from '@/lib/format';

export type GuiaSinPeso = {
    id: number;
    numero_gr: string;
    cliente: string;
    placa_tracto: string | null;
    /** El que ya tiene, en TN, para corregirlo. */
    peso?: number | null;
};

/**
 * Pide el peso en toneladas de las GR de bienes fiscalizados: el PDF de esa
 * GR no lo trae. Se abre solo apenas termina la subida, con todas las BF del
 * lote, y también desde la celda de peso de cada GR para corregirlo.
 */
export function PesoBfDialog({
    guias,
    abierto,
    onAbiertoChange,
}: {
    guias: GuiaSinPeso[];
    abierto: boolean;
    onAbiertoChange: (abierto: boolean) => void;
}) {
    const [guardadas, setGuardadas] = useState<number[]>([]);

    const alGuardar = (id: number) => {
        const nuevas = [...guardadas, id];
        setGuardadas(nuevas);

        if (guias.every((guia) => nuevas.includes(guia.id))) {
            onAbiertoChange(false);
        }
    };

    return (
        <Dialog
            open={abierto}
            onOpenChange={(valor) => {
                onAbiertoChange(valor);

                if (!valor) {
                    setGuardadas([]);
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {guias.length === 1
                            ? `Peso de la GR ${guias[0].numero_gr}`
                            : 'Peso de las GR de bienes fiscalizados'}
                    </DialogTitle>
                    <DialogDescription>
                        La GR de bienes fiscalizados no trae el peso. Escríbelo
                        en toneladas.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    {guias.map((guia) => (
                        <FilaPeso
                            key={guia.id}
                            guia={guia}
                            guardada={guardadas.includes(guia.id)}
                            onGuardada={() => alGuardar(guia.id)}
                        />
                    ))}
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" type="button">
                            {guias.length > 1 ? 'Lo hago después' : 'Cancelar'}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function FilaPeso({
    guia,
    guardada,
    onGuardada,
}: {
    guia: GuiaSinPeso;
    guardada: boolean;
    onGuardada: () => void;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        peso: guia.peso ? String(guia.peso) : '',
    });

    const guardar = (event: React.FormEvent) => {
        event.preventDefault();

        patch(actualizarPeso(guia.id).url, {
            preserveScroll: true,
            onSuccess: onGuardada,
        });
    };

    return (
        <form onSubmit={guardar} className="grid gap-1.5 rounded-lg border p-3">
            <div className="flex min-w-0 items-baseline justify-between gap-2">
                <span className="font-mono text-sm font-semibold">
                    {guia.numero_gr}
                </span>
                {guia.placa_tracto && (
                    <span className="shrink-0 font-mono text-xs text-muted-foreground">
                        {formatearPlaca(guia.placa_tracto)}
                    </span>
                )}
            </div>
            <p className="truncate text-xs text-muted-foreground">
                {guia.cliente}
            </p>
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Input
                        type="number"
                        inputMode="decimal"
                        step="0.001"
                        min="0"
                        value={data.peso}
                        onChange={(e) => setData('peso', e.target.value)}
                        placeholder="Ej.: 30.5"
                        aria-label={`Peso de la GR ${guia.numero_gr} en toneladas`}
                        className="pr-10 tabular-nums"
                        disabled={guardada}
                    />
                    <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
                        TN
                    </span>
                </div>
                <Button
                    type="submit"
                    disabled={processing || guardada || data.peso === ''}
                    variant={guardada ? 'outline' : 'default'}
                >
                    {processing && <Spinner />}
                    {guardada ? (
                        <>
                            <Check className="size-4" />
                            Guardado
                        </>
                    ) : (
                        'Guardar'
                    )}
                </Button>
            </div>
            <InputError message={errors.peso} />
        </form>
    );
}
