import { useForm } from '@inertiajs/react';
import { CircleCheck, CircleX } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/TallerController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';

type FormData = {
    resultado: 'apto' | 'no_apto' | '';
    disponible_estimado: string;
    motivo: string;
    trabajos: string;
};

/**
 * El OK de salida de taller. Apto pasa el ticket a abastecimiento; no apto
 * pide cuándo estará lista la unidad, por qué y qué se le va a hacer.
 */
export function RevisionTallerDialog({
    ticket,
    onCerrar,
}: {
    ticket: { id: number; codigo: string | null; placa: string } | null;
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormData>({
            resultado: '',
            disponible_estimado: '',
            motivo: '',
            trabajos: '',
        });

    const cerrar = () => {
        reset();
        clearErrors();
        onCerrar();
    };

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        if (ticket === null) {
            return;
        }

        post(store(ticket.id).url, {
            preserveScroll: true,
            onSuccess: cerrar,
        });
    };

    const noApta = data.resultado === 'no_apto';

    return (
        <Dialog
            open={ticket !== null}
            onOpenChange={(abierto) => !abierto && cerrar()}
        >
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[85dvh] md:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Revisión de taller ·{' '}
                        <span className="font-mono">
                            {ticket ? formatearPlaca(ticket.placa) : ''}
                        </span>
                    </DialogTitle>
                    <DialogDescription>
                        {ticket?.codigo} · sin el apto, abastecimiento no puede
                        registrar nada.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="revision-taller"
                    onSubmit={enviar}
                    className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-1"
                >
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => setData('resultado', 'apto')}
                            className={cn(
                                'flex flex-col items-center gap-1 rounded-xl border-2 p-4 text-sm font-medium transition-colors',
                                data.resultado === 'apto'
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                    : 'border-border hover:bg-muted',
                            )}
                        >
                            <CircleCheck className="size-6 text-emerald-600" />
                            Apto
                        </button>
                        <button
                            type="button"
                            onClick={() => setData('resultado', 'no_apto')}
                            className={cn(
                                'flex flex-col items-center gap-1 rounded-xl border-2 p-4 text-sm font-medium transition-colors',
                                noApta
                                    ? 'border-red-500 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200'
                                    : 'border-border hover:bg-muted',
                            )}
                        >
                            <CircleX className="size-6 text-red-600" />
                            No apto
                        </button>
                    </div>
                    {errors.resultado && (
                        <p className="text-sm text-destructive">
                            Elige apto o no apto.
                        </p>
                    )}

                    {noApta && (
                        <>
                            <Field
                                label="¿Cuándo estará lista?"
                                error={errors.disponible_estimado}
                                required
                                ayuda="Fecha y hora estimada."
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="datetime-local"
                                        value={data.disponible_estimado}
                                        onChange={(evento) =>
                                            setData(
                                                'disponible_estimado',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <Field
                                label="Motivo"
                                error={errors.motivo}
                                required
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        placeholder="Ej.: fuga de aire en la carreta"
                                        value={data.motivo}
                                        onChange={(evento) =>
                                            setData(
                                                'motivo',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </>
                    )}

                    {data.resultado !== '' && (
                        <Field
                            label={
                                noApta
                                    ? 'Trabajos a realizar'
                                    : 'Trabajos realizados'
                            }
                            error={errors.trabajos}
                            ayuda="Opcional."
                        >
                            {(id) => (
                                <Textarea
                                    id={id}
                                    rows={3}
                                    value={data.trabajos}
                                    onChange={(evento) =>
                                        setData('trabajos', evento.target.value)
                                    }
                                />
                            )}
                        </Field>
                    )}
                </form>

                <DialogFooter>
                    <Button variant="outline" onClick={cerrar}>
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        form="revision-taller"
                        disabled={processing || data.resultado === ''}
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
