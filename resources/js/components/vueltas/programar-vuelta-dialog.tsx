import { router, useForm } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    ChevronDown,
    CircleHelp,
    Layers,
    Lock,
    MapPin,
    Mountain,
    Package,
    Plus,
    Sparkles,
    Tag,
    Truck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/VueltaController';
import { CampoSelector, PanelBuscador } from '@/components/selector-en-dialogo';
import type { OpcionBuscable } from '@/components/selector-en-dialogo';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ItinerarioVuelta } from '@/components/vueltas/itinerario-vuelta';
import { cn } from '@/lib/utils';
import type {
    Opcion,
    OpcionesVuelta,
    OpcionTramo,
    ParadaVuelta,
    Sentido,
    Vuelta,
} from '@/types/vueltas';

type ParadaForm = {
    /** Solo las que ya existen. */
    id: number | null;
    sentido: Sentido;
    zona_origen: string;
    zona_destino: string;
    lugar_origen: string | null;
    lugar_destino: string | null;
    tipo_carga: string;
    cliente_id: number | null;
    /** Con GR: es lo que pasó, se muestra pero no se toca. */
    bloqueada: boolean;
    /** Un vacío que el formulario agregó solo para unir dos paradas. */
    auto?: boolean;
};

type FormData = {
    tracto_id: number | null;
    carreta_id: number | null;
    conductor_id: number | null;
    inicio: string;
    paradas: ParadaForm[];
};

type Eligiendo =
    | { campo: 'tracto_id' | 'carreta_id' | 'conductor_id' }
    | { campo: 'cliente'; sentido: Sentido }
    | null;

/** El regreso todavía sin decidir: se programa cuando se sabe. */
const POR_DEFINIR = 'por_definir';

const ZONAS_DE_PASO = ['juliaca', 'sur', 'cusco'];

const ICONOS: Record<string, ReactNode> = {
    concentrado_pisco: <Mountain className="size-4" />,
    concentrado_metalico: <Layers className="size-4" />,
    particular_lima: <Tag className="size-4" />,
    vacio_lima: <Truck className="size-4" />,
    escoria_mina: <Sparkles className="size-4" />,
    materiales_mina: <Package className="size-4" />,
    particular: <Tag className="size-4" />,
    vacio_juliaca: <Truck className="size-4" />,
    [POR_DEFINIR]: <CircleHelp className="size-4" />,
};

function desdeParada(parada: ParadaVuelta): ParadaForm {
    return {
        id: parada.id,
        sentido: parada.sentido,
        zona_origen: parada.zona_origen ?? '',
        zona_destino: parada.zona_destino ?? '',
        lugar_origen: parada.lugar_origen,
        lugar_destino: parada.lugar_destino,
        tipo_carga: parada.tipo_carga,
        cliente_id: parada.cliente_id,
        bloqueada: parada.guias.length > 0,
    };
}

function nuevaParada(
    sentido: Sentido,
    datos: Partial<ParadaForm> = {},
): ParadaForm {
    return {
        id: null,
        sentido,
        zona_origen: '',
        zona_destino: '',
        lugar_origen: null,
        lugar_destino: null,
        tipo_carga: 'particular',
        cliente_id: null,
        bloqueada: false,
        ...datos,
    };
}

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}

/**
 * Une lo elegido en un recorrido continuo: si la unidad está en Juliaca y la
 * ida arranca en la mina, antepone el vacío hasta la mina (ahí se abastece);
 * si la ida termina donde no arranca el regreso, une los dos con un vacío.
 * Los vacíos que agregó antes se rehacen cada vez.
 */
function normalizar(
    paradas: ParadaForm[],
    zonaActual: string | null,
): ParadaForm[] {
    const propias = paradas.filter((parada) => !parada.auto);
    const ida = propias.filter((parada) => parada.sentido === 'ida');
    const regreso = propias.filter((parada) => parada.sentido === 'regreso');
    const conGuias = propias.some((parada) => parada.bloqueada);

    if (
        !conGuias &&
        zonaActual !== null &&
        ZONAS_DE_PASO.includes(zonaActual) &&
        ida[0]?.zona_origen === 'mina'
    ) {
        ida.unshift(
            nuevaParada('ida', {
                zona_origen: zonaActual,
                zona_destino: 'mina',
                tipo_carga: 'vacio',
                auto: true,
            }),
        );
    }

    const ultimaIda = ida.at(-1);
    const primeraRegreso = regreso[0];

    if (
        ultimaIda?.zona_destino &&
        primeraRegreso?.zona_origen &&
        ultimaIda.zona_destino !== primeraRegreso.zona_origen
    ) {
        ida.push(
            nuevaParada('ida', {
                zona_origen: ultimaIda.zona_destino,
                zona_destino: primeraRegreso.zona_origen,
                tipo_carga: 'vacio',
                auto: true,
            }),
        );
    }

    return [...ida, ...regreso];
}

function SelectOpciones({
    valor,
    opciones,
    placeholder,
    invalido,
    onCambiar,
}: {
    valor: string;
    opciones: Opcion[];
    placeholder: string;
    invalido?: boolean;
    onCambiar: (valor: string) => void;
}) {
    return (
        <Select value={valor || undefined} onValueChange={onCambiar}>
            <SelectTrigger
                className="w-full min-w-0"
                aria-invalid={invalido || undefined}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {opciones.map((opcion) => (
                    <SelectItem key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/** Una opción de tramo como tarjeta elegible. */
function TarjetaOpcion({
    valor,
    titulo,
    descripcion,
    elegida,
    sentido,
    onElegir,
}: {
    valor: string;
    titulo: string;
    descripcion: string;
    elegida: boolean;
    sentido: Sentido;
    onElegir: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onElegir}
            aria-pressed={elegida}
            className={cn(
                'flex items-start gap-3 rounded-xl border p-3 text-left transition',
                elegida
                    ? sentido === 'ida'
                        ? 'border-amber-500 bg-amber-500/10 ring-1 ring-amber-500'
                        : 'border-violet-500 bg-violet-500/10 ring-1 ring-violet-500'
                    : 'hover:border-foreground/30 hover:bg-accent/50',
            )}
        >
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    elegida
                        ? sentido === 'ida'
                            ? 'bg-amber-500 text-white'
                            : 'bg-violet-500 text-white'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                {ICONOS[valor]}
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-medium">{titulo}</span>
                <span className="block text-xs text-muted-foreground">
                    {descripcion}
                </span>
            </span>
        </button>
    );
}

/**
 * Programar la vuelta de una unidad, un tramo a la vez: la ida se programa
 * al salir y el regreso cuando se sabe con qué vuelve (escoria, materiales o
 * carga particular). Las paradas que ya tienen GR quedan fijas.
 */
export function ProgramarVueltaDialog({
    open,
    onOpenChange,
    vuelta,
    tractoInicial,
    fechaInicial,
    opciones,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** `null` para programar una nueva. */
    vuelta: Vuelta | null;
    /** Para programar desde la ficha de una unidad. */
    tractoInicial?: number | null;
    /** El día que se estaba mirando: ahí sale la vuelta nueva. */
    fechaInicial?: string;
    opciones: OpcionesVuelta;
}) {
    const unidadInicial = opciones.tractos.find(
        (unidad) => unidad.id === tractoInicial,
    );

    // La clave del diálogo en la página lo remonta al cambiar de vuelta, así
    // que estos valores iniciales ya son los correctos.
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        tracto_id: vuelta?.tracto_id ?? tractoInicial ?? null,
        carreta_id: vuelta?.carreta_id ?? unidadInicial?.carreta_id ?? null,
        conductor_id:
            vuelta?.conductor_id ?? unidadInicial?.conductor_id ?? null,
        inicio: vuelta?.inicio ?? fechaInicial ?? hoy(),
        paradas: vuelta
            ? [...vuelta.ida, ...vuelta.regreso].map(desdeParada)
            : [],
    });

    const [eligiendo, setEligiendo] = useState<Eligiendo>(null);
    const [elegida, setElegida] = useState<Record<Sentido, string | null>>({
        ida: null,
        regreso: vuelta && vuelta.regreso.length > 0 ? null : POR_DEFINIR,
    });
    const [destino, setDestino] = useState<string | null>(null);
    const [aMano, setAMano] = useState(false);
    const [confirmandoQuitar, setConfirmandoQuitar] = useState(false);

    const errores = errors as Record<string, string | undefined>;
    const conGuias = data.paradas.some((parada) => parada.bloqueada);
    const zonaActual =
        opciones.tractos.find((tracto) => tracto.id === data.tracto_id)
            ?.zona_actual ?? null;
    const etiquetaZona = (zona: string | null) =>
        opciones.zonas.find((opcion) => opcion.value === zona)?.label ??
        zona ??
        '¿?';
    const etiquetaCarga = (carga: string) =>
        opciones.cargas.find((opcion) => opcion.value === carga)?.label ??
        carga;

    const opcionesTractos: OpcionBuscable[] = opciones.tractos.map(
        (unidad) => ({ valor: unidad.id, etiqueta: unidad.placa }),
    );
    const opcionesCarretas: OpcionBuscable[] = opciones.carretas.map(
        (carreta) => ({ valor: carreta.id, etiqueta: carreta.placa }),
    );
    const opcionesConductores: OpcionBuscable[] = opciones.conductores.map(
        (conductor) => ({ valor: conductor.id, etiqueta: conductor.nombre }),
    );
    const opcionesClientes: OpcionBuscable[] = opciones.clientes.map(
        (cliente) => ({
            valor: cliente.id,
            etiqueta: cliente.alias,
            detalle: cliente.ruc ?? undefined,
        }),
    );

    /** Cambia las paradas y vuelve a unir el recorrido. */
    const fijarParadas = (paradas: ParadaForm[], zona = zonaActual) =>
        setData('paradas', normalizar(paradas, zona));

    /** Elegir la unidad deja puestos la carreta y el conductor de su última GR. */
    const elegirUnidad = (tractoId: number) => {
        const unidad = opciones.tractos.find(
            (opcion) => opcion.id === tractoId,
        );

        setData((anterior) => ({
            ...anterior,
            tracto_id: tractoId,
            carreta_id: unidad?.carreta_id ?? anterior.carreta_id,
            conductor_id: unidad?.conductor_id ?? anterior.conductor_id,
            paradas: normalizar(anterior.paradas, unidad?.zona_actual ?? null),
        }));
    };

    /**
     * Elegir una opción reemplaza lo que ese tramo todavía no tiene con GR.
     * La carga particular de regreso necesita el destino; si no es Juliaca,
     * la unidad descarga ahí y sigue vacía a Juliaca.
     */
    const elegirOpcion = (
        sentido: Sentido,
        valor: string,
        lugar: string | null = destino,
    ) => {
        setElegida((anterior) => ({ ...anterior, [sentido]: valor }));

        const fijas = data.paradas.filter(
            (parada) => parada.sentido !== sentido || parada.bloqueada,
        );

        if (valor === POR_DEFINIR) {
            fijarParadas(fijas.filter((parada) => !parada.auto));

            return;
        }

        const opcion = opciones.tramos[sentido].find(
            (candidata) => candidata.value === valor,
        );

        if (!opcion) {
            return;
        }

        const elegidoDestino = opciones.tramos.destinos.find(
            (candidato) => candidato.lugar === lugar,
        );

        if (opcion.pide_destino && !elegidoDestino) {
            fijarParadas(fijas.filter((parada) => !parada.auto));

            return;
        }

        const nuevas = opcion.paradas.map((parada) =>
            nuevaParada(sentido, {
                zona_origen: parada.zona_origen,
                zona_destino: parada.zona_destino ?? elegidoDestino?.zona ?? '',
                lugar_destino:
                    parada.zona_destino === null
                        ? (elegidoDestino?.lugar ?? null)
                        : null,
                tipo_carga: parada.tipo_carga,
            }),
        );

        if (elegidoDestino && elegidoDestino.zona !== 'juliaca') {
            nuevas.push(
                nuevaParada(sentido, {
                    zona_origen: elegidoDestino.zona,
                    lugar_origen: elegidoDestino.lugar,
                    zona_destino: 'juliaca',
                    tipo_carga: 'vacio',
                }),
            );
        }

        const ida =
            sentido === 'ida'
                ? [
                      ...fijas.filter((parada) => parada.sentido === 'ida'),
                      ...nuevas,
                  ]
                : fijas.filter((parada) => parada.sentido === 'ida');
        const regreso =
            sentido === 'regreso'
                ? [
                      ...fijas.filter((parada) => parada.sentido === 'regreso'),
                      ...nuevas,
                  ]
                : fijas.filter((parada) => parada.sentido === 'regreso');

        fijarParadas([...ida, ...regreso]);
    };

    const elegirDestino = (lugar: string) => {
        setDestino(lugar);
        elegirOpcion('regreso', 'particular', lugar);
    };

    /** El cliente va en las paradas con carga particular del tramo. */
    const clienteDe = (sentido: Sentido) =>
        data.paradas.find(
            (parada) =>
                parada.sentido === sentido &&
                !parada.bloqueada &&
                parada.tipo_carga === 'particular',
        )?.cliente_id ?? null;

    const fijarCliente = (sentido: Sentido, clienteId: number) =>
        setData(
            'paradas',
            data.paradas.map((parada) =>
                parada.sentido === sentido &&
                !parada.bloqueada &&
                parada.tipo_carga === 'particular'
                    ? { ...parada, cliente_id: clienteId }
                    : parada,
            ),
        );

    const cambiarParada = (indice: number, cambios: Partial<ParadaForm>) =>
        setData(
            'paradas',
            data.paradas.map((parada, i) =>
                i === indice ? { ...parada, ...cambios, auto: false } : parada,
            ),
        );

    const agregarAMano = (sentido: Sentido) => {
        const ultima =
            data.paradas
                .filter((parada) => parada.sentido === sentido)
                .at(-1) ?? data.paradas.at(-1);
        const nueva = nuevaParada(sentido, {
            zona_origen: ultima?.zona_destino ?? '',
        });
        const corte =
            sentido === 'ida'
                ? data.paradas.findIndex(
                      (parada) => parada.sentido === 'regreso',
                  )
                : -1;

        setData(
            'paradas',
            corte === -1
                ? [...data.paradas, nueva]
                : [
                      ...data.paradas.slice(0, corte),
                      nueva,
                      ...data.paradas.slice(corte),
                  ],
        );
    };

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        const opcionesEnvio = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (vuelta) {
            put(update(vuelta.id).url, opcionesEnvio);

            return;
        }

        post(store().url, opcionesEnvio);
    };

    const quitar = () => {
        if (!vuelta) {
            return;
        }

        router.delete(destroy(vuelta.id).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const buscadores = {
        tracto_id: {
            titulo: 'Unidad',
            opciones: opcionesTractos,
            valor: data.tracto_id,
            onElegir: elegirUnidad,
        },
        carreta_id: {
            titulo: 'Carreta',
            opciones: opcionesCarretas,
            valor: data.carreta_id,
            onElegir: (valor: number) => setData('carreta_id', valor),
        },
        conductor_id: {
            titulo: 'Conductor',
            opciones: opcionesConductores,
            valor: data.conductor_id,
            onElegir: (valor: number) => setData('conductor_id', valor),
        },
    } as const;

    const buscador =
        eligiendo === null
            ? null
            : eligiendo.campo === 'cliente'
              ? {
                    titulo: 'Cliente',
                    opciones: opcionesClientes,
                    valor: clienteDe(eligiendo.sentido),
                    onElegir: (valor: number) =>
                        fijarCliente(eligiendo.sentido, valor),
                }
              : buscadores[eligiendo.campo];

    const itinerario = data.paradas.map((parada) => ({
        sentido: parada.sentido,
        desde: parada.lugar_origen ?? etiquetaZona(parada.zona_origen),
        hasta: parada.lugar_destino ?? etiquetaZona(parada.zona_destino),
        carga:
            parada.tipo_carga === 'vacio'
                ? 'vacía'
                : etiquetaCarga(parada.tipo_carga),
        vacio: parada.tipo_carga === 'vacio',
        hecha: parada.bloqueada,
    }));

    const tramo = (sentido: Sentido) => {
        const subiendo = sentido === 'regreso';
        const lista: (
            | OpcionTramo
            | {
                  value: string;
                  label: string;
                  descripcion: string;
                  pide_destino: boolean;
              }
        )[] = subiendo
            ? [
                  {
                      value: POR_DEFINIR,
                      label: 'Por definir',
                      descripcion: 'Se programa cuando se sepa',
                      pide_destino: false,
                  },
                  ...opciones.tramos.regreso,
              ]
            : opciones.tramos.ida;
        const fijas = data.paradas.filter(
            (parada) => parada.sentido === sentido && parada.bloqueada,
        );
        const conParticular = data.paradas.some(
            (parada) =>
                parada.sentido === sentido &&
                !parada.bloqueada &&
                parada.tipo_carga === 'particular',
        );

        return (
            <section className="flex flex-col gap-3 rounded-xl border p-4">
                <h3
                    className={cn(
                        'flex items-center gap-1.5 text-sm font-semibold',
                        subiendo
                            ? 'text-violet-700 dark:text-violet-400'
                            : 'text-amber-700 dark:text-amber-400',
                    )}
                >
                    {subiendo ? (
                        <ArrowUpRight className="size-4" />
                    ) : (
                        <ArrowDownRight className="size-4" />
                    )}
                    {subiendo ? 'Regreso · subida' : 'Ida · bajada'}
                </h3>

                {fijas.length > 0 && (
                    <p className="flex items-center gap-1.5 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                        <Lock className="size-3.5" />
                        Ya tiene GR: lo que elijas se agrega después.
                    </p>
                )}

                <div className="grid gap-2">
                    {lista.map((opcion) => (
                        <TarjetaOpcion
                            key={opcion.value}
                            valor={opcion.value}
                            titulo={opcion.label}
                            descripcion={opcion.descripcion}
                            sentido={sentido}
                            elegida={elegida[sentido] === opcion.value}
                            onElegir={() => elegirOpcion(sentido, opcion.value)}
                        />
                    ))}
                </div>

                {subiendo && elegida.regreso === 'particular' && (
                    <div className="flex flex-col gap-2">
                        <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                            <MapPin className="size-3.5" />
                            ¿A dónde va? Si no es Juliaca, de ahí sigue a
                            Juliaca.
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {opciones.tramos.destinos.map((candidato) => (
                                <button
                                    key={candidato.lugar}
                                    type="button"
                                    onClick={() =>
                                        elegirDestino(candidato.lugar)
                                    }
                                    className={cn(
                                        'rounded-full border px-3 py-1 text-xs transition',
                                        destino === candidato.lugar
                                            ? 'border-violet-500 bg-violet-500 text-white'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {candidato.lugar}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {conParticular && (
                    <Field label="Cliente">
                        {() => (
                            <CampoSelector
                                etiqueta="Cliente (opcional)"
                                elegida={opcionesClientes.find(
                                    (opcion) =>
                                        opcion.valor === clienteDe(sentido),
                                )}
                                onAbrir={() =>
                                    setEligiendo({ campo: 'cliente', sentido })
                                }
                            />
                        )}
                    </Field>
                )}
            </section>
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex flex-col gap-0 overflow-hidden p-0 md:max-h-[92dvh] md:max-w-4xl">
                {buscador !== null ? (
                    <div className="p-6">
                        <DialogHeader className="sr-only">
                            <DialogTitle>
                                Elegir {buscador.titulo.toLowerCase()}
                            </DialogTitle>
                        </DialogHeader>
                        <PanelBuscador
                            titulo={buscador.titulo}
                            valor={buscador.valor}
                            opciones={buscador.opciones}
                            onElegir={(valor) => {
                                buscador.onElegir(valor);
                                setEligiendo(null);
                            }}
                            onVolver={() => setEligiendo(null)}
                        />
                    </div>
                ) : (
                    <>
                        <DialogHeader className="border-b px-6 pt-6 pb-4">
                            <DialogTitle className="text-lg">
                                {vuelta
                                    ? `Vuelta ${vuelta.codigo ?? ''}`
                                    : 'Programar vuelta'}
                            </DialogTitle>
                            <DialogDescription>
                                Programa la ida al salir; el regreso, cuando se
                                sepa con qué vuelve. Las GR caen solas en su
                                parada.
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            id="programar-vuelta"
                            onSubmit={enviar}
                            className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-6 py-5"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Field
                                    label="Unidad"
                                    error={errores.tracto_id}
                                    required
                                    ayuda={
                                        conGuias
                                            ? 'Ya tiene GR: no se cambia.'
                                            : zonaActual
                                              ? `Está en ${etiquetaZona(zonaActual)}.`
                                              : undefined
                                    }
                                >
                                    {(id) => (
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Unidad"
                                            elegida={opcionesTractos.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.tracto_id,
                                            )}
                                            invalido={Boolean(
                                                errores.tracto_id,
                                            )}
                                            onAbrir={() =>
                                                !conGuias &&
                                                setEligiendo({
                                                    campo: 'tracto_id',
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    label="Sale el"
                                    error={errores.inicio}
                                    required
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            type="date"
                                            value={data.inicio}
                                            onChange={(evento) =>
                                                setData(
                                                    'inicio',
                                                    evento.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    label="Conductor"
                                    error={errores.conductor_id}
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
                                            onAbrir={() =>
                                                setEligiendo({
                                                    campo: 'conductor_id',
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    label="Carreta"
                                    error={errores.carreta_id}
                                >
                                    {(id) => (
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Carreta"
                                            elegida={opcionesCarretas.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.carreta_id,
                                            )}
                                            onAbrir={() =>
                                                setEligiendo({
                                                    campo: 'carreta_id',
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                            </div>

                            <div className="rounded-xl bg-muted/40 p-4">
                                <span className="mb-3 block text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Recorrido
                                </span>
                                <ItinerarioVuelta paradas={itinerario} />
                            </div>

                            {errores.paradas && (
                                <p className="text-sm text-destructive">
                                    {errores.paradas}
                                </p>
                            )}

                            <div className="grid gap-4 md:grid-cols-2">
                                {tramo('ida')}
                                {tramo('regreso')}
                            </div>

                            <div className="flex flex-col gap-3">
                                <button
                                    type="button"
                                    onClick={() => setAMano(!aMano)}
                                    className="flex items-center gap-1 self-start text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <ChevronDown
                                        className={cn(
                                            'size-3.5 transition',
                                            aMano && 'rotate-180',
                                        )}
                                    />
                                    Ajustar las paradas a mano
                                </button>

                                {aMano && (
                                    <div className="flex flex-col gap-2">
                                        {data.paradas.map((parada, indice) =>
                                            parada.bloqueada ? (
                                                <div
                                                    key={
                                                        parada.id ??
                                                        `n${indice}`
                                                    }
                                                    className="flex items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                                                >
                                                    <Lock className="size-3.5" />
                                                    {itinerario[indice].desde} →{' '}
                                                    {itinerario[indice].hasta} ·{' '}
                                                    {itinerario[indice].carga}
                                                </div>
                                            ) : (
                                                <div
                                                    key={
                                                        parada.id ??
                                                        `n${indice}`
                                                    }
                                                    className="grid grid-cols-[5rem_1fr_1fr_1fr_auto] items-center gap-2"
                                                >
                                                    <span
                                                        className={cn(
                                                            'text-xs font-medium',
                                                            parada.sentido ===
                                                                'ida'
                                                                ? 'text-amber-700 dark:text-amber-400'
                                                                : 'text-violet-700 dark:text-violet-400',
                                                        )}
                                                    >
                                                        {parada.sentido ===
                                                        'ida'
                                                            ? 'Ida'
                                                            : 'Regreso'}
                                                    </span>
                                                    <SelectOpciones
                                                        valor={
                                                            parada.zona_origen
                                                        }
                                                        opciones={
                                                            opciones.zonas
                                                        }
                                                        placeholder="Desde"
                                                        invalido={Boolean(
                                                            errores[
                                                                `paradas.${indice}.zona_origen`
                                                            ],
                                                        )}
                                                        onCambiar={(valor) =>
                                                            cambiarParada(
                                                                indice,
                                                                {
                                                                    zona_origen:
                                                                        valor,
                                                                    lugar_origen:
                                                                        null,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <SelectOpciones
                                                        valor={
                                                            parada.zona_destino
                                                        }
                                                        opciones={
                                                            opciones.zonas
                                                        }
                                                        placeholder="Hasta"
                                                        invalido={Boolean(
                                                            errores[
                                                                `paradas.${indice}.zona_destino`
                                                            ],
                                                        )}
                                                        onCambiar={(valor) =>
                                                            cambiarParada(
                                                                indice,
                                                                {
                                                                    zona_destino:
                                                                        valor,
                                                                    lugar_destino:
                                                                        null,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <SelectOpciones
                                                        valor={
                                                            parada.tipo_carga
                                                        }
                                                        opciones={
                                                            opciones.cargas
                                                        }
                                                        placeholder="Carga"
                                                        onCambiar={(valor) =>
                                                            cambiarParada(
                                                                indice,
                                                                {
                                                                    tipo_carga:
                                                                        valor,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Quitar parada"
                                                        onClick={() =>
                                                            setData(
                                                                'paradas',
                                                                data.paradas.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        indice,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <X className="size-4" />
                                                    </Button>
                                                </div>
                                            ),
                                        )}
                                        <div className="flex gap-2">
                                            {(['ida', 'regreso'] as const).map(
                                                (sentido) => (
                                                    <Button
                                                        key={sentido}
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            agregarAMano(
                                                                sentido,
                                                            )
                                                        }
                                                    >
                                                        <Plus className="size-4" />
                                                        Parada de{' '}
                                                        {sentido === 'ida'
                                                            ? 'ida'
                                                            : 'regreso'}
                                                    </Button>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </form>

                        <DialogFooter className="gap-2 border-t px-6 py-4 sm:justify-between">
                            {vuelta?.quitable ? (
                                <Button
                                    type="button"
                                    variant={
                                        confirmandoQuitar
                                            ? 'destructive'
                                            : 'ghost'
                                    }
                                    disabled={processing}
                                    onClick={() =>
                                        confirmandoQuitar
                                            ? quitar()
                                            : setConfirmandoQuitar(true)
                                    }
                                >
                                    {confirmandoQuitar
                                        ? '¿Seguro? Quitar vuelta'
                                        : 'Quitar vuelta'}
                                </Button>
                            ) : (
                                <span />
                            )}
                            <Button
                                type="submit"
                                form="programar-vuelta"
                                disabled={
                                    processing || data.paradas.length === 0
                                }
                            >
                                {vuelta ? 'Guardar cambios' : 'Programar'}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
