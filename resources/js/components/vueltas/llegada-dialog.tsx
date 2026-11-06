import { useForm } from '@inertiajs/react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
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
import { formatearFecha } from '@/lib/format';
import type { TarjetaVuelta } from '@/types/vueltas';

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}

/**
 * Registrar que la unidad volvió a la base, con el día y el kilometraje. La
 * llegada no se calcula: la marca una persona. Desde ahí el ticket está en
 * base y se puede programar el siguiente.
 */
export function LlegadaDialog({
    ticket,
    onCerrar,
}: {
    ticket: TarjetaVuelta;
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        llegada_el: ticket.llegada?.fecha ?? hoy(),
        km_llegada: ticket.llegada?.km ? String(ticket.llegada.km) : '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(vueltas.registrarLlegada(ticket.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="flex flex-col overflow-hidden md:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Llegada a la base · {ticket.codigo}
                    </DialogTitle>
                    <DialogDescription>
                        {ticket.llegada_prevista
                            ? `Debía llegar el ${formatearFecha(ticket.llegada_prevista)}.`
                            : 'El día en que volvió y el kilometraje con que llegó.'}
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="registrar-llegada"
                    onSubmit={enviar}
                    className="grid grid-cols-2 gap-4 px-1"
                >
                    <Field label="Llegó el" error={errors.llegada_el} required>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.llegada_el}
                                onChange={(evento) =>
                                    setData('llegada_el', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        label="Kilometraje"
                        error={errors.km_llegada}
                        ayuda="Con el de salida da lo recorrido y el rendimiento."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="numeric"
                                min="0"
                                step="1"
                                value={data.km_llegada}
                                onChange={(evento) =>
                                    setData('km_llegada', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </form>

                <DialogFooter>
                    <Button
                        type="submit"
                        form="registrar-llegada"
                        disabled={processing}
                    >
                        Registrar llegada
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
