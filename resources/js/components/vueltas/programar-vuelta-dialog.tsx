import { router, useForm } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Lock, Plus, X } from 'lucide-react';
import { useState } from 'react';
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
import type {
    Opcion,
    OpcionesVuelta,
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
    tipo_carga: string;
    cliente_id: number | null;
    /** Con GR: es lo que pasó, se muestra pero no se toca. */
    bloqueada: boolean;
    /** Para pintar las bloqueadas tal como las dejó la GR. */
    resumen: string | null;
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
    | { campo: 'cliente'; indice: number }
    | null;

const SIN_ELEGIR = '';

function desdeParada(parada: ParadaVuelta): ParadaForm {
    return {
        id: parada.id,
        sentido: parada.sentido,
        zona_origen: parada.zona_origen ?? SIN_ELEGIR,
        zona_destino: parada.zona_destino ?? SIN_ELEGIR,
        tipo_carga: parada.tipo_carga,
        cliente_id: parada.cliente_id,
        bloqueada: parada.guias.length > 0,
        resumen:
            parada.guias.length > 0
                ? `${parada.origen ?? '¿?'} → ${parada.destino ?? '¿?'} · ${parada.tipo_carga_label} · ${parada.guias.map((guia) => guia.numero_gr).join(', ')}`
                : null,
    };
}

function nuevaParada(
    sentido: Sentido,
    datos: Partial<ParadaForm> = {},
): ParadaForm {
    return {
        id: null,
        sentido,
        zona_origen: SIN_ELEGIR,
        zona_destino: SIN_ELEGIR,
        tipo_carga: 'particular',
        cliente_id: null,
        bloqueada: false,
        resumen: null,
        ...datos,
    };
}

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
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

/**
 * Programar la vuelta de una unidad, o corregirla en el camino.
 *
 * Se arranca de una plantilla —las vueltas que más se repiten— y se ajusta
 * lo que haga falta. Las paradas que ya tienen GR quedan fijas: eso ya
 * pasó. Lo demás se cambia, porque en Pisco o en Callao se decide qué sigue.
 */
export function ProgramarVueltaDialog({
    open,
    onOpenChange,
    vuelta,
    tractoInicial,
    opciones,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** `null` para programar una nueva. */
    vuelta: Vuelta | null;
    /** Para programar desde la ficha de una unidad. */
    tractoInicial?: number | null;
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
        inicio: vuelta?.inicio ?? hoy(),
        paradas: vuelta
            ? [...vuelta.ida, ...vuelta.regreso].map(desdeParada)
            : [],
    });

    const [eligiendo, setEligiendo] = useState<Eligiendo>(null);
    const [confirmandoQuitar, setConfirmandoQuitar] = useState(false);
    const errores = errors as Record<string, string | undefined>;
    const conGuias = data.paradas.some((parada) => parada.bloqueada);

    const opcionesUnidades: OpcionBuscable[] = opciones.tractos.map(
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
        }));
    };

    /** La plantilla reemplaza todo lo que todavía no tiene GR. */
    const aplicarPlantilla = (valor: string) => {
        const plantilla = opciones.plantillas.find(
            (opcion) => opcion.value === valor,
        );

        if (!plantilla) {
            return;
        }

        setData((anterior) => ({
            ...anterior,
            paradas: [
                ...anterior.paradas.filter((parada) => parada.bloqueada),
                ...plantilla.paradas.map((parada) =>
                    nuevaParada(parada.sentido, parada),
                ),
            ],
        }));
    };

    const cambiarParada = (indice: number, cambios: Partial<ParadaForm>) => {
        setData(
            'paradas',
            data.paradas.map((parada, i) =>
                i === indice ? { ...parada, ...cambios } : parada,
            ),
        );
    };

    /** La nueva arranca donde terminó la última del tramo. */
    const agregarParada = (sentido: Sentido) => {
        const delTramo = data.paradas.filter(
            (parada) => parada.sentido === sentido,
        );
        const ultima = delTramo.at(-1) ?? data.paradas.at(-1);

        const nueva = nuevaParada(sentido, {
            zona_origen: ultima?.zona_destino ?? SIN_ELEGIR,
        });

        // Las de ida van antes que las de regreso.
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

    const quitarParada = (indice: number) => {
        setData(
            'paradas',
            data.paradas.filter((_, i) => i !== indice),
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
            opciones: opcionesUnidades,
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
                    valor: data.paradas[eligiendo.indice]?.cliente_id ?? null,
                    onElegir: (valor: number) =>
                        cambiarParada(eligiendo.indice, { cliente_id: valor }),
                }
              : buscadores[eligiendo.campo];

    const tramo = (sentido: Sentido) => (
        <div className="flex flex-col gap-2">
            <h3 className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {sentido === 'ida' ? (
                    <ArrowDownRight className="size-3.5" />
                ) : (
                    <ArrowUpRight className="size-3.5" />
                )}
                {sentido === 'ida' ? 'Ida · bajada' : 'Regreso · subida'}
            </h3>

            {data.paradas.map((parada, indice) => {
                if (parada.sentido !== sentido) {
                    return null;
                }

                if (parada.bloqueada) {
                    return (
                        <div
                            key={parada.id ?? `n${indice}`}
                            className="flex items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                        >
                            <Lock className="size-3.5 shrink-0" />
                            <span className="min-w-0 truncate">
                                {parada.resumen}
                            </span>
                        </div>
                    );
                }

                const error = (campo: string) =>
                    errores[`paradas.${indice}.${campo}`];
                const conCliente =
                    parada.tipo_carga !== 'vacio' &&
                    parada.tipo_carga !== 'concentrado' &&
                    parada.tipo_carga !== 'metalico';

                return (
                    <div
                        key={parada.id ?? `n${indice}`}
                        className="flex flex-col gap-2 rounded-md border p-2"
                    >
                        <div className="grid grid-cols-[1fr_1fr_auto] gap-2">
                            <SelectOpciones
                                valor={parada.zona_origen}
                                opciones={opciones.zonas}
                                placeholder="Desde"
                                invalido={Boolean(error('zona_origen'))}
                                onCambiar={(valor) =>
                                    cambiarParada(indice, {
                                        zona_origen: valor,
                                    })
                                }
                            />
                            <SelectOpciones
                                valor={parada.zona_destino}
                                opciones={opciones.zonas}
                                placeholder="Hasta"
                                invalido={Boolean(error('zona_destino'))}
                                onCambiar={(valor) =>
                                    cambiarParada(indice, {
                                        zona_destino: valor,
                                    })
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar parada"
                                onClick={() => quitarParada(indice)}
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <SelectOpciones
                                valor={parada.tipo_carga}
                                opciones={opciones.cargas}
                                placeholder="Carga"
                                invalido={Boolean(error('tipo_carga'))}
                                onCambiar={(valor) =>
                                    cambiarParada(indice, {
                                        tipo_carga: valor,
                                    })
                                }
                            />
                            {conCliente && (
                                <CampoSelector
                                    etiqueta="Cliente (opcional)"
                                    elegida={opcionesClientes.find(
                                        (opcion) =>
                                            opcion.valor === parada.cliente_id,
                                    )}
                                    invalido={Boolean(error('cliente_id'))}
                                    onAbrir={() =>
                                        setEligiendo({
                                            campo: 'cliente',
                                            indice,
                                        })
                                    }
                                />
                            )}
                        </div>
                    </div>
                );
            })}

            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={() => agregarParada(sentido)}
            >
                <Plus className="size-4" />
                Agregar parada
            </Button>
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[90dvh] md:max-w-2xl">
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
                                {vuelta
                                    ? `Editar vuelta ${vuelta.codigo ?? ''}`
                                    : 'Programar vuelta'}
                            </DialogTitle>
                            <DialogDescription>
                                Elige una plantilla y ajusta las paradas. Las GR
                                que se emitan caen solas en su parada.
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            id="programar-vuelta"
                            onSubmit={enviar}
                            className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-1"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Unidad"
                                    error={errores.tracto_id}
                                    required
                                    ayuda={
                                        conGuias
                                            ? 'Ya tiene GR: la unidad no se cambia.'
                                            : undefined
                                    }
                                >
                                    {(id) => (
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Unidad"
                                            elegida={opcionesUnidades.find(
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
                                    label="Día de salida"
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

                            <Field
                                label="Plantilla"
                                ayuda="Reemplaza las paradas que todavía no tienen GR."
                            >
                                {() => (
                                    <SelectOpciones
                                        valor=""
                                        opciones={opciones.plantillas}
                                        placeholder="Elegir una vuelta típica…"
                                        onCambiar={aplicarPlantilla}
                                    />
                                )}
                            </Field>

                            {errores.paradas && (
                                <p className="text-sm text-destructive">
                                    {errores.paradas}
                                </p>
                            )}

                            <div className="grid gap-4 md:grid-cols-2">
                                {tramo('ida')}
                                {tramo('regreso')}
                            </div>
                        </form>

                        <DialogFooter className="gap-2 sm:justify-between">
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
                                disabled={processing}
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
