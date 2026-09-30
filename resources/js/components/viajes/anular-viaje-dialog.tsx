import { useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import { anular } from '@/actions/App/Http/Controllers/ViajeController';
import InputError from '@/components/input-error';
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
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';

/** Los motivos de «Baja de GRE» en SOL; el valor es el `codMotivo` de SUNAT. */
const MOTIVOS_BAJA = [
    { value: '02', label: 'Antes de iniciar el traslado' },
    { value: '01', label: 'Durante el traslado, por cambio de destinatario' },
] as const;

type Props = {
    viaje: { id: number; numero_gr: string };
    trigger: React.ReactNode;
};

/**
 * Marca la GR como anulada. No se borra: queda en el listado, en gris, y deja
 * de contar como viaje en el tablero, la cobranza y las fichas. El motivo es
 * opcional, pero es lo que después explica la fila gris.
 *
 * Quien puede emitir GR también puede darla de baja en SUNAT desde acá. Esa
 * baja no tiene vuelta atrás; si SUNAT la rechaza, la GR no se anula.
 */
export function AnularViajeDialog({ viaje, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const motivoId = useId();
    const bajaId = useId();
    const { puede } = usePermisos();

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({ motivo: '', baja_sunat: '' });
    const conBaja = data.baja_sunat !== '';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(anular(viaje.id).url, {
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
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Anular GR {viaje.numero_gr}</DialogTitle>
                        <DialogDescription>
                            La GR queda en el listado, en gris, pero deja de
                            contar como viaje en el tablero, la cobranza y las
                            fichas. Se puede reactivar después.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor={motivoId}>Motivo (opcional)</Label>
                        <Textarea
                            id={motivoId}
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            placeholder="Ej.: placa del tracto mal escrita, reemplazada por EG03-…"
                            rows={3}
                            maxLength={500}
                        />
                        <InputError message={errors.motivo} />
                    </div>

                    {puede('viajes.emitir') && (
                        <div className="grid gap-3 rounded-lg border p-3">
                            <div className="flex items-start gap-2.5">
                                <Checkbox
                                    id={bajaId}
                                    checked={conBaja}
                                    onCheckedChange={(marcado) =>
                                        setData(
                                            'baja_sunat',
                                            marcado === true
                                                ? MOTIVOS_BAJA[0].value
                                                : '',
                                        )
                                    }
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor={bajaId}
                                    className="leading-snug"
                                >
                                    Darla de baja también en SUNAT
                                </Label>
                            </div>

                            {conBaja && (
                                <>
                                    <div
                                        role="radiogroup"
                                        aria-label="Motivo de la baja en SUNAT"
                                        className="grid gap-1.5"
                                    >
                                        {MOTIVOS_BAJA.map((motivo) => (
                                            <button
                                                key={motivo.value}
                                                type="button"
                                                role="radio"
                                                aria-checked={
                                                    data.baja_sunat ===
                                                    motivo.value
                                                }
                                                onClick={() =>
                                                    setData(
                                                        'baja_sunat',
                                                        motivo.value,
                                                    )
                                                }
                                                className={cn(
                                                    'rounded-md border px-3 py-2 text-left text-sm transition-colors',
                                                    data.baja_sunat ===
                                                        motivo.value
                                                        ? 'border-destructive bg-destructive/5 font-medium'
                                                        : 'hover:bg-muted',
                                                )}
                                            >
                                                {motivo.label}
                                            </button>
                                        ))}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        La baja en SUNAT no se puede deshacer y
                                        la GR ya no se podrá reactivar.
                                    </p>
                                </>
                            )}
                            <InputError message={errors.baja_sunat} />
                        </div>
                    )}

                    <DialogFooter className="pt-4">
                        <DialogClose asChild>
                            <Button variant="outline" type="button">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {conBaja ? 'Dar de baja y anular' : 'Anular GR'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
