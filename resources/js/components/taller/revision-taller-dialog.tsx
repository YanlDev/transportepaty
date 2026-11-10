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
    /** El km con que sale: con el de llegada da el rendimiento. */
    kilometraje: string;
};

/** `datetime-local` de hoy o de mañana a esa hora, en la hora del equipo. */
function fechaLocal(diasDespues: number, hora: number): string {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + diasDespues);
    const dosCifras = (numero: number) => String(numero).padStart(2, '0');

    return `${fecha.getFullYear()}-${dosCifras(fecha.getMonth() + 1)}-${dosCifras(fecha.getDate())}T${dosCifras(hora)}:00`;
}

/** Lo que casi siempre se responde a «¿cuándo estará lista?». */
const ATAJOS_DE_FECHA = [
    { etiqueta: 'Hoy 6 pm', valor: () => fechaLocal(0, 18) },
    { etiqueta: 'Mañana 8 am', valor: () => fechaLocal(1, 8) },
    { etiqueta: 'Mañana 6 pm', valor: () => fechaLocal(1, 18) },
];

/** Para no escribir en el patio; el campo sigue libre para detallar. */
const MOTIVOS_FRECUENTES = [
    'Llantas',
    'Frenos',
    'Fuga de aire',
    'Fuga de aceite',
    'Sistema eléctrico',
];

/**
 * El OK de salida de taller. Apto pasa el ticket a abastecimiento; no apto
 * pide cuándo estará lista la unidad, por qué y qué se le va a hacer.
 */
export function RevisionTallerDialog({
    ticket,
    onCerrar,
    resultadoInicial = '',
}: {
    ticket: {
        id: number;
        codigo: string | null;
        placa: string;
        /** El último km conocido: se propone como km de salida. */
        km_actual?: number | null;
    } | null;
    onCerrar: () => void;
    /** Desde los botones grandes del celular llega ya elegido. */
    resultadoInicial?: FormData['resultado'];
}) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormData>({
            resultado: resultadoInicial,
            disponible_estimado: '',
            motivo: '',
            trabajos: '',
            // Se propone el último km anotado: en el patio casi siempre es
            // ese o unos pocos más, y corregir dos cifras es más rápido que
            // escribir seis.
            kilometraje: ticket?.km_actual ? String(ticket.km_actual) : '',
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
                                    <div className="flex flex-col gap-2">
                                        <div className="flex flex-wrap gap-1.5">
                                            {ATAJOS_DE_FECHA.map((atajo) => (
                                                <Button
                                                    key={atajo.etiqueta}
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        data.disponible_estimado ===
                                                        atajo.valor()
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    className="pointer-coarse:h-10"
                                                    onClick={() =>
                                                        setData(
                                                            'disponible_estimado',
                                                            atajo.valor(),
                                                        )
                                                    }
                                                >
                                                    {atajo.etiqueta}
                                                </Button>
                                            ))}
                                        </div>
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
                                    </div>
                                )}
                            </Field>
                            <Field
                                label="Motivo"
                                error={errors.motivo}
                                required
                            >
                                {(id) => (
                                    <div className="flex flex-col gap-2">
                                        <div className="flex flex-wrap gap-1.5">
                                            {MOTIVOS_FRECUENTES.map(
                                                (motivo) => (
                                                    <Button
                                                        key={motivo}
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            data.motivo ===
                                                            motivo
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        className="pointer-coarse:h-10"
                                                        onClick={() =>
                                                            setData(
                                                                'motivo',
                                                                motivo,
                                                            )
                                                        }
                                                    >
                                                        {motivo}
                                                    </Button>
                                                ),
                                            )}
                                        </div>
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
                                    </div>
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

                    {data.resultado === 'apto' && (
                        <Field
                            label="Kilometraje de salida"
                            error={errors.kilometraje}
                            ayuda={
                                ticket?.km_actual
                                    ? `Propuesto: el último km anotado (${ticket.km_actual.toLocaleString('es-PE')}). Corrígelo si el tablero marca otro.`
                                    : 'El odómetro con que sale: con el de llegada se calcula lo recorrido y el rendimiento.'
                            }
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    inputMode="numeric"
                                    min="0"
                                    step="1"
                                    value={data.kilometraje}
                                    onChange={(evento) =>
                                        setData(
                                            'kilometraje',
                                            evento.target.value,
                                        )
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
