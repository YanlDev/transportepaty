import { useForm } from '@inertiajs/react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/ProgramacionController';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
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
import type {
    ClienteOpcion,
    ConductorOpcion,
    ProgramacionTarjeta,
    UnidadOpcion,
} from '@/types/programacion';

type FormData = {
    fecha: string;
    vehiculo_id: number | null;
    conductor_id: number | null;
    cliente_id: number | null;
    destino: string;
};

/**
 * Programar una unidad, o corregir una ya programada. Cuatro campos y nada
 * más: el pedido fue explícito en que cargar tenía que ser rápido, así que
 * todo lo que no sea unidad, conductor, cliente y destino queda afuera.
 *
 * Al crear, el diálogo se queda abierto y limpia solo la unidad y el
 * conductor: se programa de a varias unidades para el mismo cliente y
 * destino, y volver a tipearlos cada vez es justo lo que hace lenta la carga.
 */
export function ProgramacionDialog({
    open,
    onOpenChange,
    fecha,
    programacion,
    unidades,
    conductores,
    clientes,
    destinosUsados,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    fecha: string;
    /** `null` para programar una nueva. */
    programacion: ProgramacionTarjeta | null;
    unidades: UnidadOpcion[];
    conductores: ConductorOpcion[];
    clientes: ClienteOpcion[];
    destinosUsados: string[];
}) {
    // La clave del diálogo en la página lo remonta al cambiar de tarjeta, así
    // que estos valores iniciales ya son los correctos y no hace falta
    // resembrarlos con un efecto.
    const { data, setData, post, put, processing, errors, reset } =
        useForm<FormData>({
            fecha,
            vehiculo_id: programacion?.vehiculo_id ?? null,
            conductor_id: programacion?.conductor_id ?? null,
            cliente_id: programacion?.cliente_id ?? null,
            destino: programacion?.destino ?? '',
        });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        if (programacion) {
            put(update(programacion.id).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        post(store().url, {
            preserveScroll: true,
            // Cliente y destino se conservan a propósito: lo habitual es
            // programar varias unidades seguidas para el mismo cliente.
            onSuccess: () => reset('vehiculo_id', 'conductor_id'),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {programacion
                            ? 'Editar programación'
                            : 'Programar unidad'}
                    </DialogTitle>
                    <DialogDescription>
                        Carga particular del {fecha}. Al guardar una nueva, el
                        cliente y el destino quedan puestos para seguir cargando
                        unidades.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Unidad"
                            error={errors.vehiculo_id}
                            required
                        >
                            {(id) => (
                                <SelectorBuscable
                                    id={id}
                                    etiqueta="Unidad"
                                    valor={data.vehiculo_id}
                                    onCambio={(valor) =>
                                        setData('vehiculo_id', valor)
                                    }
                                    invalido={Boolean(errors.vehiculo_id)}
                                    opciones={unidades.map((unidad) => ({
                                        valor: unidad.id,
                                        etiqueta: unidad.placa,
                                    }))}
                                />
                            )}
                        </Field>

                        <Field
                            label="Conductor"
                            error={errors.conductor_id}
                            required
                        >
                            {(id) => (
                                <SelectorBuscable
                                    id={id}
                                    etiqueta="Conductor"
                                    valor={data.conductor_id}
                                    onCambio={(valor) =>
                                        setData('conductor_id', valor)
                                    }
                                    invalido={Boolean(errors.conductor_id)}
                                    opciones={conductores.map((conductor) => ({
                                        valor: conductor.id,
                                        etiqueta: conductor.nombre,
                                    }))}
                                />
                            )}
                        </Field>

                        <Field
                            label="Cliente"
                            error={errors.cliente_id}
                            required
                        >
                            {(id) => (
                                <SelectorBuscable
                                    id={id}
                                    etiqueta="Cliente"
                                    valor={data.cliente_id}
                                    onCambio={(valor) =>
                                        setData('cliente_id', valor)
                                    }
                                    invalido={Boolean(errors.cliente_id)}
                                    opciones={clientes.map((cliente) => ({
                                        valor: cliente.id,
                                        etiqueta: cliente.alias,
                                    }))}
                                />
                            )}
                        </Field>

                        <Field
                            label="Destino"
                            error={errors.destino}
                            required
                            ayuda="Se autocompleta con los ya usados."
                        >
                            {(id) => (
                                <>
                                    <Input
                                        id={id}
                                        list="destinos-usados"
                                        value={data.destino}
                                        onChange={(evento) =>
                                            setData(
                                                'destino',
                                                evento.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="JULIACA"
                                    />
                                    <datalist id="destinos-usados">
                                        {destinosUsados.map((destino) => (
                                            <option
                                                key={destino}
                                                value={destino}
                                            />
                                        ))}
                                    </datalist>
                                </>
                            )}
                        </Field>
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {programacion ? 'Cancelar' : 'Listo'}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {programacion ? 'Guardar' : 'Programar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
