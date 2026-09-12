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
    UltimoViaje,
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
 * Programar una unidad, o corregir una ya programada. Cinco campos y nada
 * más: el pedido fue explícito en que cargar tenía que ser rápido, así que
 * todo lo que no sea día, unidad, conductor, cliente y destino queda afuera.
 *
 * Tres cosas lo hacen rápido, y las tres apuntan a lo mismo —no volver a
 * tipear lo que no cambia entre una unidad y la siguiente:
 *
 * 1. Elegir el conductor deja puesta la unidad de su última guía.
 * 2. Al guardar una nueva, el día, el cliente y el destino se conservan y el
 *    diálogo queda abierto para seguir cargando.
 * 3. El destino autocompleta con los ya usados.
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
    ultimoViajePorConductor,
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
    /** Con qué tracto salió cada conductor la última vez, por `conductor_id`. */
    ultimoViajePorConductor: Record<number, UltimoViaje>;
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

    const ultimoDelConductor =
        data.conductor_id === null
            ? null
            : (ultimoViajePorConductor[data.conductor_id] ?? null);

    /**
     * Elegir el conductor deja puesta la unidad con la que salió la última
     * vez, que es casi siempre la misma. Pisa lo que hubiera antes a
     * propósito: el caso normal es elegir conductor y guardar, y si la unidad
     * de hoy es otra se cambia después, que es un clic contra los dos que
     * ahorra en las demás.
     *
     * Un conductor sin guías previas —uno nuevo— no prellena nada en vez de
     * dejar una unidad inventada.
     */
    const elegirConductor = (conductorId: number) => {
        const ultimo = ultimoViajePorConductor[conductorId];

        setData((anterior) => ({
            ...anterior,
            conductor_id: conductorId,
            vehiculo_id: ultimo ? ultimo.vehiculo_id : anterior.vehiculo_id,
        }));
    };

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
                        Al guardar una nueva, el día, el cliente y el destino
                        quedan puestos para seguir cargando unidades.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {/* Editable y no fijo al día que se está viendo: se
                            programa mirando hoy para cargar mañana. */}
                        <Field
                            label="Día de carga"
                            error={errors.fecha}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="date"
                                    value={data.fecha}
                                    onChange={(evento) =>
                                        setData('fecha', evento.target.value)
                                    }
                                />
                            )}
                        </Field>

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
                            ayuda={
                                ultimoDelConductor
                                    ? `Última GR: ${ultimoDelConductor.placa} el ${ultimoDelConductor.fecha}.`
                                    : undefined
                            }
                        >
                            {(id) => (
                                <SelectorBuscable
                                    id={id}
                                    etiqueta="Conductor"
                                    valor={data.conductor_id}
                                    onCambio={elegirConductor}
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
