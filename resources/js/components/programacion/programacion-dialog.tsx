import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { storeExpress } from '@/actions/App/Http/Controllers/ClienteController';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/ProgramacionController';
import { CampoSelector, PanelBuscador } from '@/components/selector-en-dialogo';
import type { OpcionBuscable } from '@/components/selector-en-dialogo';
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
import { cn } from '@/lib/utils';
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
    whatsapp_adicional: string;
    precio_flete: string;
    precio_incluye_igv: boolean;
};

/** El alta mínima de un cliente, sin salir de la programación. */
type FormCliente = {
    ruc: string;
    razon_social: string;
    alias: string;
    contacto: string;
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
            whatsapp_adicional: programacion?.whatsapp_adicional ?? '',
            precio_flete: programacion?.precio_flete?.toString() ?? '',
            precio_incluye_igv: programacion?.precio_incluye_igv ?? false,
        });

    /**
     * El alta express, abierta con el texto que se alcanzó a escribir en el
     * buscador de clientes. `null` mientras está cerrada.
     *
     * Va acá dentro y no en otro diálogo a propósito: un modal sobre otro
     * modal se tapan entre sí, que es lo que se quiso evitar al pasar el
     * selector a popover.
     */
    const [altaCliente, setAltaCliente] = useState<string | null>(null);

    /**
     * Qué campo se está eligiendo. Mientras hay uno, el diálogo muestra el
     * buscador en lugar del formulario: nada flota sobre nada.
     */
    const [eligiendo, setEligiendo] = useState<
        'vehiculo_id' | 'conductor_id' | 'cliente_id' | null
    >(null);

    const formCliente = useForm<FormCliente>({
        ruc: '',
        razon_social: '',
        alias: '',
        contacto: '',
    });

    const opcionesUnidades: OpcionBuscable[] = unidades.map((unidad) => ({
        valor: unidad.id,
        etiqueta: unidad.placa,
    }));

    const opcionesConductores: OpcionBuscable[] = conductores.map(
        (conductor) => ({
            valor: conductor.id,
            etiqueta: conductor.nombre,
        }),
    );

    const opcionesClientes: OpcionBuscable[] = clientes.map((cliente) => ({
        valor: cliente.id,
        etiqueta: cliente.alias,
        detalle: cliente.ruc,
    }));

    const ultimoDelConductor =
        data.conductor_id === null
            ? null
            : (ultimoViajePorConductor[data.conductor_id] ?? null);

    /**
     * Lo escrito en el buscador arranca como razón social y como alias: en la
     * mayoría de los casos se tipeó el nombre del cliente, así que quedan dos
     * campos menos que llenar y el alias se recorta si hace falta.
     */
    const abrirAltaCliente = (texto: string) => {
        formCliente.setData({
            ruc: '',
            razon_social: texto,
            alias: texto.slice(0, 60),
            contacto: '',
        });
        setAltaCliente(texto);
    };

    const cerrarAltaCliente = () => {
        setAltaCliente(null);
        formCliente.clearErrors();
    };

    /**
     * Crea el cliente y lo deja elegido en el formulario, sin perder nada de
     * lo ya cargado. Al volver, la página trae la lista de clientes recargada:
     * el recién creado se reconoce ahí por su RUC, que es el único dato que
     * con seguridad no cambió entre lo que se envió y lo que se guardó.
     */
    const crearCliente = () => {
        const ruc = formCliente.data.ruc;

        formCliente.post(storeExpress().url, {
            preserveScroll: true,
            onSuccess: (page) => {
                const lista = (page.props.clientes ?? []) as ClienteOpcion[];
                const creado = lista.find((cliente) => cliente.ruc === ruc);

                if (creado) {
                    setData('cliente_id', creado.id);
                }

                formCliente.reset();
                setAltaCliente(null);
            },
        });
    };

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
            onSuccess: () =>
                reset(
                    'vehiculo_id',
                    'conductor_id',
                    'whatsapp_adicional',
                    'precio_flete',
                ),
        });
    };

    /** El buscador que ocupa el diálogo mientras se elige un campo. */
    const buscadores = {
        vehiculo_id: {
            titulo: 'Unidad',
            opciones: opcionesUnidades,
            valor: data.vehiculo_id,
            onElegir: (valor: number) => setData('vehiculo_id', valor),
            crear: undefined,
        },
        conductor_id: {
            titulo: 'Conductor',
            opciones: opcionesConductores,
            valor: data.conductor_id,
            onElegir: elegirConductor,
            crear: undefined,
        },
        cliente_id: {
            titulo: 'Cliente',
            opciones: opcionesClientes,
            valor: data.cliente_id,
            onElegir: (valor: number) => setData('cliente_id', valor),
            crear: {
                etiqueta: 'Crear cliente',
                onCrear: (texto: string) => {
                    setEligiendo(null);
                    abrirAltaCliente(texto);
                },
            },
        },
    } as const;

    const buscador = eligiendo === null ? null : buscadores[eligiendo];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {buscador !== null ? (
                    <>
                        <DialogHeader className="sr-only">
                            <DialogTitle>
                                Elegir {buscador.titulo.toLowerCase()}
                            </DialogTitle>
                        </DialogHeader>
                        <PanelBuscador
                            titulo={buscador.titulo}
                            valor={buscador.valor}
                            opciones={buscador.opciones}
                            crear={buscador.crear}
                            onElegir={(valor) => {
                                buscador.onElegir(valor);
                                setEligiendo(null);
                            }}
                            onVolver={() => setEligiendo(null)}
                        />
                    </>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>
                                {programacion
                                    ? 'Editar programación'
                                    : 'Programar unidad'}
                            </DialogTitle>
                            <DialogDescription>
                                Al guardar una nueva, el día, el cliente y el
                                destino quedan puestos para seguir cargando
                                unidades.
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
                                                setData(
                                                    'fecha',
                                                    evento.target.value,
                                                )
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
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Unidad"
                                            elegida={opcionesUnidades.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.vehiculo_id,
                                            )}
                                            invalido={Boolean(
                                                errors.vehiculo_id,
                                            )}
                                            onAbrir={() =>
                                                setEligiendo('vehiculo_id')
                                            }
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
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Conductor"
                                            elegida={opcionesConductores.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.conductor_id,
                                            )}
                                            invalido={Boolean(
                                                errors.conductor_id,
                                            )}
                                            onAbrir={() =>
                                                setEligiendo('conductor_id')
                                            }
                                        />
                                    )}
                                </Field>

                                <Field
                                    label="Cliente"
                                    error={errors.cliente_id}
                                    required
                                >
                                    {(id) => (
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Cliente"
                                            elegida={opcionesClientes.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.cliente_id,
                                            )}
                                            invalido={Boolean(
                                                errors.cliente_id,
                                            )}
                                            onAbrir={() =>
                                                setEligiendo('cliente_id')
                                            }
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
                                                {destinosUsados.map(
                                                    (destino) => (
                                                        <option
                                                            key={destino}
                                                            value={destino}
                                                        />
                                                    ),
                                                )}
                                            </datalist>
                                        </>
                                    )}
                                </Field>

                                {/* El flete acordado: no siempre está cerrado al
                            programar, así que es opcional. Va al aviso de
                            facturación, no al del conductor. */}
                                <Field
                                    label="Precio del flete (S/)"
                                    error={errors.precio_flete}
                                    ayuda={desgloseDelFlete(
                                        data.precio_flete,
                                        data.precio_incluye_igv,
                                    )}
                                >
                                    {(id) => (
                                        <div className="flex gap-2">
                                            <Input
                                                id={id}
                                                type="number"
                                                inputMode="decimal"
                                                step="0.01"
                                                min="0"
                                                value={data.precio_flete}
                                                onChange={(evento) =>
                                                    setData(
                                                        'precio_flete',
                                                        evento.target.value,
                                                    )
                                                }
                                                placeholder="1850.00"
                                            />
                                            {/* Dos botones y no una casilla: así se ve
                                        de un vistazo cómo se pactó, sin tener
                                        que leer el texto de una etiqueta. */}
                                            <div className="flex shrink-0 rounded-md border p-0.5">
                                                {[
                                                    {
                                                        igv: false,
                                                        texto: '+ IGV',
                                                    },
                                                    {
                                                        igv: true,
                                                        texto: 'Incluido',
                                                    },
                                                ].map(({ igv, texto }) => (
                                                    <button
                                                        key={texto}
                                                        type="button"
                                                        aria-pressed={
                                                            data.precio_incluye_igv ===
                                                            igv
                                                        }
                                                        onClick={() =>
                                                            setData(
                                                                'precio_incluye_igv',
                                                                igv,
                                                            )
                                                        }
                                                        className={cn(
                                                            'rounded px-2 text-xs font-medium transition-colors',
                                                            data.precio_incluye_igv ===
                                                                igv
                                                                ? 'bg-primary text-primary-foreground'
                                                                : 'text-muted-foreground hover:bg-accent',
                                                        )}
                                                    >
                                                        {texto}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </Field>

                                {/* A quién más avisar de esta salida: el dueño de la
                            unidad, un apoyo. El segundo celular del propio
                            conductor va en su ficha, no acá: ese sirve para
                            todas sus salidas, no solo para esta. */}
                                <Field
                                    label="WhatsApp adicional"
                                    error={errors.whatsapp_adicional}
                                    ayuda="Opcional. Otro número al que mandar el aviso de esta salida."
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            type="tel"
                                            inputMode="tel"
                                            value={data.whatsapp_adicional}
                                            onChange={(evento) =>
                                                setData(
                                                    'whatsapp_adicional',
                                                    evento.target.value,
                                                )
                                            }
                                            placeholder="999888777"
                                        />
                                    )}
                                </Field>
                            </div>

                            {/* No es un `<form>`: iría anidado dentro del de la
                        programación, que el HTML no permite. Envía con un
                        botón normal. */}
                            {altaCliente !== null && (
                                <div className="flex flex-col gap-4 rounded-md border border-dashed bg-muted/30 p-4">
                                    <p className="text-sm font-medium">
                                        Cliente nuevo
                                        <span className="ml-1 font-normal text-muted-foreground">
                                            — el resto de la ficha se completa
                                            después en el padrón.
                                        </span>
                                    </p>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            label="RUC"
                                            error={formCliente.errors.ruc}
                                            required
                                            ayuda="Con él se enlazan sus GR ya importadas."
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    inputMode="numeric"
                                                    maxLength={11}
                                                    value={formCliente.data.ruc}
                                                    onChange={(evento) =>
                                                        formCliente.setData(
                                                            'ruc',
                                                            evento.target.value.replace(
                                                                /\D/g,
                                                                '',
                                                            ),
                                                        )
                                                    }
                                                    placeholder="20123456789"
                                                />
                                            )}
                                        </Field>

                                        <Field
                                            label="Alias"
                                            error={formCliente.errors.alias}
                                            required
                                            ayuda="El nombre corto que se ve en la tarjeta."
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    maxLength={60}
                                                    value={
                                                        formCliente.data.alias
                                                    }
                                                    onChange={(evento) =>
                                                        formCliente.setData(
                                                            'alias',
                                                            evento.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </Field>

                                        <Field
                                            label="Razón social"
                                            error={
                                                formCliente.errors.razon_social
                                            }
                                            required
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    value={
                                                        formCliente.data
                                                            .razon_social
                                                    }
                                                    onChange={(evento) =>
                                                        formCliente.setData(
                                                            'razon_social',
                                                            evento.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </Field>

                                        <Field
                                            label="Contacto"
                                            error={formCliente.errors.contacto}
                                        >
                                            {(id) => (
                                                <Input
                                                    id={id}
                                                    value={
                                                        formCliente.data
                                                            .contacto
                                                    }
                                                    onChange={(evento) =>
                                                        formCliente.setData(
                                                            'contacto',
                                                            evento.target.value,
                                                        )
                                                    }
                                                    placeholder="Nombre y teléfono"
                                                />
                                            )}
                                        </Field>
                                    </div>

                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={cerrarAltaCliente}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={crearCliente}
                                            disabled={formCliente.processing}
                                        >
                                            Crear y elegir
                                        </Button>
                                    </div>
                                </div>
                            )}

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
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

/** La tasa del IGV, la misma que usa el servidor para el aviso a facturación. */
const IGV = 0.18;

/**
 * El otro importe del flete, para verlo mientras se escribe: si se pactó sin
 * IGV, cuánto es con IGV, y al revés. Es la cuenta que de otro modo alguien
 * hace a mano antes de mandarle el mensaje a facturación.
 */
function desgloseDelFlete(
    precio: string,
    incluyeIgv: boolean,
): string | undefined {
    const monto = Number(precio);

    if (precio.trim() === '' || Number.isNaN(monto) || monto <= 0) {
        return 'Opcional. Lo recibe facturación en su aviso.';
    }

    const soles = (valor: number) =>
        valor.toLocaleString('es-PE', {
            style: 'currency',
            currency: 'PEN',
            minimumFractionDigits: 2,
        });

    return incluyeIgv
        ? `Neto sin IGV: ${soles(monto / (1 + IGV))}`
        : `Total con IGV: ${soles(monto * (1 + IGV))}`;
}
