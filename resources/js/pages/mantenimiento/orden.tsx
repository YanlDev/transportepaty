import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Check, CircleDot, PackageOpen, Plus, Star, X } from 'lucide-react';
import { useState } from 'react';
import {
    create as crearVale,
    show as verVale,
} from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import compras from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import { show as llantasDeUnidad } from '@/actions/App/Http/Controllers/LlantaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import {
    anular,
    agregarRepuesto,
    aprobarRepuesto,
    aprobarRequerimiento,
    devolverRequerimiento,
    eliminarFoto,
    index as listaDeOrdenes,
    observar,
    pasarAlCatalogo,
    quitarRepuesto,
    rechazar,
    store,
    update,
} from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import InputError from '@/components/input-error';
import { formatearKm } from '@/components/mantenimiento/estado-mantenimiento';
import { SelectorFotos } from '@/components/mantenimiento/selector-fotos';
import { EstadoOrden } from '@/components/ordenes/estado-orden';
import { FormularioRepuesto } from '@/components/ordenes/formulario-repuesto';
import type { ArticuloPedible } from '@/components/ordenes/formulario-repuesto';
import { GaleriaFotos } from '@/components/ordenes/galeria-fotos';
import { LineaDeTiempo } from '@/components/ordenes/linea-de-tiempo';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearCantidad } from '@/lib/almacen';
import { formatearFecha, formatearPlaca, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type {
    EjecucionDeOrden,
    FotoOrden,
    MaterialesOrden,
    ServicioPlan,
} from '@/types/mantenimiento';

type Inicial = {
    vehiculo_id: number | null;
    tipo: string;
    servicio_mantenimiento_id: number | null;
    estado?: string;
    fecha: string;
    kilometraje: number | null;
    inicio?: string | null;
    termino?: string | null;
    trabajo?: string;
    tecnico?: string | null;
    observacion?: string | null;
    justificacion_materiales?: string | null;
    prioridad?: string;
    mecanicos?: number[];
    responsable_id?: number | null;
    tareas?: { id: number | null; descripcion: string }[];
};

type Props = {
    /** Null al crear. */
    orden: {
        id: number;
        numero: string;
        vehiculo_id: number;
        puede_anular: boolean;
        /** El mecánico la terminó y taller puede devolvérsela. */
        puede_observar: boolean;
        /** Es una pre-orden que se puede rechazar. */
        puede_rechazar: boolean;
        /** El mecánico envió su requerimiento y se puede aprobar o devolver. */
        puede_aprobar_requerimiento: boolean;
        estado: string;
        estado_label: string;
        fotos: FotoOrden[];
    } | null;
    /** Lo que hicieron los mecánicos; null al crear. */
    ejecucion: EjecucionDeOrden | null;
    /** Para agregarle repuestos a la orden; solo al editar. */
    repuestos?: {
        puede_agregar: boolean;
        puede_catalogar: boolean;
        articulos: ArticuloPedible[];
        unidades: EnumOption[];
    };
    /** Lo que salió del almacén para la orden; solo al editar. */
    materiales?: MaterialesOrden;
    inicial: Inicial;
    /** Tractos y carretas (el nombre quedó de cuando eran solo tractos). */
    tractos: {
        id: number;
        placa: string;
        marca: string | null;
        tipo: string;
        tipo_label: string;
    }[];
    /** Los servicios del plan de cada marca, por marca en mayúsculas. */
    servicios: Record<string, ServicioPlan[]>;
    tipos: EnumOption[];
    estados: EnumOption[];
    prioridades: EnumOption[];
    /** Los usuarios a los que se les puede asignar la orden. */
    mecanicos: { id: number; nombre: string }[];
};

type Formulario = {
    vehiculo_id: number | null;
    tipo: string;
    servicio_mantenimiento_id: number | null;
    estado: string;
    fecha: string;
    kilometraje: string;
    inicio: string;
    termino: string;
    trabajo: string;
    tecnico: string;
    observacion: string;
    justificacion_materiales: string;
    prioridad: string;
    mecanicos: number[];
    responsable_id: number | null;
    tareas: { id: number | null; descripcion: string }[];
    fotos_antes: File[];
    fotos_despues: File[];
};

/** Los tres momentos de una orden, en el orden en que pasan en el taller. */
const PASOS = ['Unidad y servicio', 'Trabajo', 'Cierre'];

/** En qué paso está cada campo, para llevar ahí cuando el servidor lo rechaza. */
const PASO_DE_CAMPO: Record<string, number> = {
    vehiculo_id: 0,
    tipo: 0,
    servicio_mantenimiento_id: 0,
    fecha: 0,
    kilometraje: 0,
    prioridad: 0,
    trabajo: 1,
    tecnico: 1,
    mecanicos: 1,
    tareas: 1,
    fotos_antes: 1,
    estado: 2,
    justificacion_materiales: 2,
    fotos_despues: 2,
    inicio: 2,
    termino: 2,
    observacion: 2,
};

/** Lo que cada estado le dice al resto: taller, almacén y el OK de salida. */
const QUE_SIGNIFICA: Record<string, string> = {
    solicitada:
        'Pre-orden: el mecánico arma el requerimiento (tareas, repuestos y fotos) y te lo envía. La unidad no recibe el apto.',
    requerimiento: 'El mecánico envió su requerimiento y espera tu aprobación.',
    pendiente:
        'Orden aprobada y lista para que el mecánico la empiece. La unidad no recibe el apto.',
    en_proceso: 'Se está trabajando. La unidad no recibe el apto.',
    por_revisar:
        'El mecánico la terminó y espera tu validación. Elige «Finalizada» para aprobarla.',
    observada:
        'Devuelta al mecánico para que corrija. La unidad no recibe el apto.',
    finalizada:
        'El trabajo terminó y queda tu usuario como quien la validó: la unidad puede volver a salir.',
};

/**
 * La orden de trabajo, para crear y para editar. Es el «Cuadro de trabajos de
 * taller» del Excel, ordenado para llenarse desde el celular: primero qué
 * unidad y qué servicio, después el trabajo y las fotos, y al final las
 * horas, que suelen completarse cuando la unidad sale.
 */
export default function OrdenTrabajo({
    orden,
    materiales,
    inicial,
    tractos,
    servicios,
    tipos,
    estados,
    prioridades,
    mecanicos,
    ejecucion,
    repuestos,
}: Props) {
    const { puede } = usePermisos();
    const [agregandoRepuesto, setAgregandoRepuesto] = useState(false);
    const editando = orden !== null;

    // Cada grupo de fotos avisa mientras comprime las suyas.
    const [preparandoAntes, setPreparandoAntes] = useState(false);
    const [preparandoDespues, setPreparandoDespues] = useState(false);

    // Una orden abierta se retoma donde se trabaja: el trabajo y sus
    // materiales. Una nueva empieza por la unidad.
    // La que el mecánico terminó se abre en el cierre: ahí están sus fotos
    // y la decisión de taller.
    const [paso, setPaso] = useState(
        orden === null || inicial.estado === 'finalizada'
            ? 0
            : inicial.estado === 'por_revisar' ||
                inicial.estado === 'requerimiento'
              ? 2
              : 1,
    );

    const { data, setData, post, transform, processing, progress, errors } =
        useForm<Formulario>({
            vehiculo_id: inicial.vehiculo_id,
            tipo: inicial.tipo,
            servicio_mantenimiento_id: inicial.servicio_mantenimiento_id,
            estado: inicial.estado ?? 'finalizada',
            fecha: inicial.fecha,
            kilometraje:
                inicial.kilometraje === null ? '' : String(inicial.kilometraje),
            inicio: inicial.inicio ?? '',
            termino: inicial.termino ?? '',
            trabajo: inicial.trabajo ?? '',
            tecnico: inicial.tecnico ?? '',
            observacion: inicial.observacion ?? '',
            justificacion_materiales: inicial.justificacion_materiales ?? '',
            prioridad: inicial.prioridad ?? 'normal',
            mecanicos: inicial.mecanicos ?? [],
            responsable_id: inicial.responsable_id ?? null,
            tareas: inicial.tareas ?? [],
            fotos_antes: [],
            fotos_despues: [],
        });

    const tracto = tractos.find((opcion) => opcion.id === data.vehiculo_id);
    const delPlan = tracto?.marca ? (servicios[tracto.marca] ?? []) : [];
    const preventiva = data.tipo === 'preventiva';

    // Lo que el nivel pide y todavía no salió del almacén. Al crear no hay
    // vales todavía: si se registra ya finalizada, hay que decir por qué.
    const faltantes = (materiales?.requeridos ?? []).filter(
        (requerido) => requerido.falta,
    );
    const pideJustificacion =
        preventiva &&
        data.estado === 'finalizada' &&
        (!editando ||
            faltantes.length > 0 ||
            data.justificacion_materiales !== '' ||
            !!errors.justificacion_materiales);

    // Sin unidad, o una preventiva sin su servicio, no se pasa del primer
    // paso: el servidor lo rechazaría al final, cuando ya se escribió todo.
    const pasoUnoListo =
        data.vehiculo_id !== null &&
        (!preventiva || data.servicio_mantenimiento_id !== null);

    // Si el servidor rechaza algo, se marca el paso donde está el campo.
    const pasosConError = PASOS.map((_, indice) =>
        Object.keys(errors).some(
            (campo) => PASO_DE_CAMPO[campo.split('.')[0]] === indice,
        ),
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Órdenes', href: listaDeOrdenes().url },
            {
                title: editando ? 'Editar orden' : 'Nueva orden',
                href: '#',
            },
        ],
    });

    const elegirTipo = (tipo: string) => {
        setData((previo) => ({
            ...previo,
            tipo,
            // Una correctiva no lleva servicio del plan.
            servicio_mantenimiento_id:
                tipo === 'preventiva' ? previo.servicio_mantenimiento_id : null,
        }));
    };

    const elegirServicio = (servicio: ServicioPlan) => {
        setData((previo) => ({
            ...previo,
            servicio_mantenimiento_id: servicio.id,
            // Lo que se escribe casi siempre: se propone, se puede cambiar.
            trabajo: previo.trabajo === '' ? servicio.nombre : previo.trabajo,
        }));
    };

    // Con un solo mecánico él es el responsable; con varios se elige uno.
    const alternarMecanico = (id: number) => {
        setData((previo) => {
            const elegidos = previo.mecanicos.includes(id)
                ? previo.mecanicos.filter((otro) => otro !== id)
                : [...previo.mecanicos, id];

            return {
                ...previo,
                mecanicos: elegidos,
                responsable_id: elegidos.includes(previo.responsable_id ?? -1)
                    ? previo.responsable_id
                    : (elegidos[0] ?? null),
            };
        });
    };

    const tareasHechas = new Map(
        (ejecucion?.tareas ?? []).map((tarea) => [tarea.id, tarea]),
    );
    const porRevisar = orden?.estado === 'por_revisar';

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        // PHP no lee archivos de un PUT: la edición viaja como POST con
        // `_method`, que Laravel convierte en PUT.
        // Las tareas en blanco no viajan: son filas que se agregaron y no se
        // llenaron.
        transform((datos) => {
            const limpio = {
                ...datos,
                tareas: datos.tareas.filter(
                    (tarea) => tarea.descripcion.trim() !== '',
                ),
            };

            return editando ? { ...limpio, _method: 'put' } : limpio;
        });

        post(editando ? update(orden.id).url : store().url, {
            preserveScroll: true,
            // Lleva al primer paso con un campo rechazado.
            onError: (rechazados) => {
                const pasosRechazados = Object.keys(rechazados)
                    .map((campo) => PASO_DE_CAMPO[campo.split('.')[0]])
                    .filter((indice) => indice !== undefined);

                if (pasosRechazados.length > 0) {
                    setPaso(Math.min(...pasosRechazados));
                }
            },
        });
    };

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={editando ? 'Editar orden' : 'Nueva orden'} />

            <div className="flex items-center justify-between gap-2">
                <h1 className="text-xl font-semibold">
                    {editando
                        ? `Orden de trabajo ${orden.numero}`
                        : 'Nueva orden de trabajo'}
                </h1>
                <div className="flex flex-wrap items-center gap-2">
                    {editando && (
                        <EstadoOrden
                            estado={orden.estado}
                            etiqueta={orden.estado_label}
                        />
                    )}
                    {editando && orden.puede_anular && (
                        <AnularDialog
                            url={anular(orden.id).url}
                            titulo={`¿Anular la ${orden.numero}?`}
                            descripcion="No se borra: queda a la vista con el motivo. Si era una preventiva, el plan vuelve a contar desde el servicio anterior. Con entregas de almacén vigentes no se puede anular."
                        />
                    )}
                </div>
            </div>

            {editando && orden.puede_aprobar_requerimiento && (
                <div className="flex flex-col gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    <p>
                        <span className="font-semibold">
                            El mecánico envió su requerimiento.{' '}
                        </span>
                        Abajo están las tareas, los repuestos que pide y las
                        fotos de cómo llegó la unidad. Si está bien, apruébalo:
                        ahí nace la orden de trabajo.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            className="h-11"
                            onClick={() =>
                                router.post(
                                    aprobarRequerimiento(orden.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Check className="size-4" />
                            Aprobar y generar la orden
                        </Button>
                        <AnularDialog
                            url={devolverRequerimiento(orden.id).url}
                            titulo={`¿Devolver el requerimiento de la ${orden.numero}?`}
                            descripcion="Vuelve al mecánico como pre-orden, con lo que escribas acá, para que lo corrija y lo envíe de nuevo."
                            etiqueta="Devolver"
                            ayuda="Qué tiene que cambiar"
                        />
                    </div>
                </div>
            )}

            {editando &&
                orden.puede_rechazar &&
                orden.estado === 'solicitada' && (
                    <div className="flex flex-col gap-3 rounded-xl border border-violet-500/40 bg-violet-500/10 p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            <span className="font-semibold">
                                Es una pre-orden.{' '}
                            </span>
                            {(inicial.mecanicos ?? []).length === 0
                                ? 'Todavía no tiene mecánico: elígelo en «Trabajo» para que arme el requerimiento.'
                                : 'El mecánico está armando el requerimiento: te llega cuando lo envíe.'}
                        </p>
                        <AnularDialog
                            url={rechazar(orden.id).url}
                            titulo={`¿Rechazar la pre-orden ${orden.numero}?`}
                            descripcion="Queda anulada con el motivo, a la vista de quien la creó."
                            etiqueta="Rechazar"
                            ayuda="Por qué no se va a atender"
                        />
                    </div>
                )}

            {editando && orden.puede_observar && (
                <div className="flex flex-col gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        <span className="font-semibold">
                            El mecánico la terminó.{' '}
                        </span>
                        Revisa las fotos y la nota en «Cierre». Si está bien,
                        apruébala; si falta algo, devuélvela.
                    </p>
                    <AnularDialog
                        url={observar(orden.id).url}
                        titulo={`¿Devolver la ${orden.numero} al mecánico?`}
                        descripcion="Vuelve a su lista como «Observada», con lo que escribas acá."
                        etiqueta="Observar"
                        ayuda="Qué tiene que corregir"
                    />
                </div>
            )}

            <form
                onSubmit={guardar}
                className="flex flex-col gap-5 max-md:pb-20"
            >
                <Pasos
                    pasos={PASOS}
                    actual={paso}
                    conError={pasosConError}
                    onIr={setPaso}
                />

                {paso === 0 && (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="orden-unidad">Unidad</Label>
                            {editando ? (
                                <p className="text-lg font-semibold">
                                    {tracto
                                        ? formatearPlaca(tracto.placa)
                                        : '—'}
                                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                                        {tracto?.tipo_label} {tracto?.marca}
                                    </span>
                                </p>
                            ) : (
                                <SelectorBuscable
                                    id="orden-unidad"
                                    valor={data.vehiculo_id}
                                    onCambio={(valor) =>
                                        setData((previo) => ({
                                            ...previo,
                                            vehiculo_id: valor,
                                            servicio_mantenimiento_id: null,
                                        }))
                                    }
                                    etiqueta="Placa"
                                    opciones={tractos.map((opcion) => ({
                                        valor: opcion.id,
                                        etiqueta: formatearPlaca(opcion.placa),
                                        detalle: `${opcion.tipo_label} ${opcion.placa} ${opcion.marca ?? ''}`,
                                    }))}
                                    invalido={!!errors.vehiculo_id}
                                />
                            )}
                            <InputError message={errors.vehiculo_id} />
                        </div>

                        <Segmentado
                            etiqueta="Tipo"
                            opciones={tipos}
                            valor={data.tipo}
                            onCambio={elegirTipo}
                        />

                        {preventiva && (
                            <div className="grid gap-1.5">
                                <Label>Servicio del plan</Label>
                                {delPlan.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {!tracto
                                            ? 'Elige primero la unidad.'
                                            : tracto.tipo === 'carreta'
                                              ? 'Las carretas no tienen plan por kilometraje: regístrala como correctiva.'
                                              : `La marca ${tracto.marca ?? ''} todavía no tiene plan: regístrala como correctiva.`}
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-4 gap-2">
                                        {delPlan.map((servicio) => (
                                            <button
                                                key={servicio.id}
                                                type="button"
                                                onClick={() =>
                                                    elegirServicio(servicio)
                                                }
                                                aria-pressed={
                                                    data.servicio_mantenimiento_id ===
                                                    servicio.id
                                                }
                                                className={cn(
                                                    'flex h-16 flex-col items-center justify-center rounded-lg border text-sm transition-colors',
                                                    data.servicio_mantenimiento_id ===
                                                        servicio.id
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'hover:bg-accent',
                                                )}
                                            >
                                                <span className="text-xl font-semibold">
                                                    {servicio.codigo}
                                                </span>
                                                <span className="text-[11px] opacity-80">
                                                    {formatearKm(
                                                        servicio.intervalo_km,
                                                    )}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                <InputError
                                    message={errors.servicio_mantenimiento_id}
                                />
                            </div>
                        )}

                        <Segmentado
                            etiqueta="Prioridad"
                            opciones={prioridades}
                            valor={data.prioridad}
                            onCambio={(valor) => setData('prioridad', valor)}
                        />

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="orden-fecha">Fecha</Label>
                                <Input
                                    id="orden-fecha"
                                    type="date"
                                    className="h-11"
                                    value={data.fecha}
                                    onChange={(evento) =>
                                        setData('fecha', evento.target.value)
                                    }
                                />
                                <InputError message={errors.fecha} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="orden-km">Km</Label>
                                <Input
                                    id="orden-km"
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    className="h-11 tabular-nums"
                                    value={data.kilometraje}
                                    onChange={(evento) =>
                                        setData(
                                            'kilometraje',
                                            evento.target.value.replace(
                                                /\D/g,
                                                '',
                                            ),
                                        )
                                    }
                                />
                                <InputError message={errors.kilometraje} />
                            </div>
                        </div>
                    </>
                )}

                {paso === 1 && (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="orden-trabajo">
                                Trabajo: qué hay que hacer o qué se hizo
                            </Label>
                            <Textarea
                                id="orden-trabajo"
                                rows={4}
                                value={data.trabajo}
                                onChange={(evento) =>
                                    setData('trabajo', evento.target.value)
                                }
                                placeholder="Es lo que lee el mecánico en su celular"
                            />
                            <InputError message={errors.trabajo} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Mecánicos asignados</Label>
                            {mecanicos.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                                    Todavía no hay usuarios con el rol Mecánico.
                                    Créalos en Configuración → Usuarios para
                                    asignarles órdenes.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {mecanicos.map((mecanico) => {
                                        const elegido = data.mecanicos.includes(
                                            mecanico.id,
                                        );
                                        const responde =
                                            data.responsable_id === mecanico.id;

                                        return (
                                            <span
                                                key={mecanico.id}
                                                className={cn(
                                                    'flex min-h-11 items-center overflow-hidden rounded-full border text-sm font-medium transition-colors',
                                                    elegido
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'hover:bg-accent',
                                                )}
                                            >
                                                <button
                                                    type="button"
                                                    aria-pressed={elegido}
                                                    onClick={() =>
                                                        alternarMecanico(
                                                            mecanico.id,
                                                        )
                                                    }
                                                    className="min-h-11 px-4"
                                                >
                                                    {mecanico.nombre}
                                                </button>
                                                {elegido &&
                                                    data.mecanicos.length >
                                                        1 && (
                                                        <button
                                                            type="button"
                                                            aria-pressed={
                                                                responde
                                                            }
                                                            aria-label={`${mecanico.nombre} es el responsable`}
                                                            title="Responsable"
                                                            onClick={() =>
                                                                setData(
                                                                    'responsable_id',
                                                                    mecanico.id,
                                                                )
                                                            }
                                                            className="flex min-h-11 items-center border-l border-primary-foreground/30 px-3"
                                                        >
                                                            <Star
                                                                className={cn(
                                                                    'size-4',
                                                                    responde &&
                                                                        'fill-current',
                                                                )}
                                                            />
                                                        </button>
                                                    )}
                                            </span>
                                        );
                                    })}
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {data.mecanicos.length === 0
                                    ? data.estado === 'solicitada'
                                        ? 'Una pre-orden necesita mecánico: es quien arma el requerimiento.'
                                        : 'Sin mecánico, la orden no aparece en ningún celular.'
                                    : data.mecanicos.length === 1
                                      ? 'La verá en «Mis órdenes» en su celular.'
                                      : 'Todos la ven en «Mis órdenes». La estrella marca al responsable.'}
                            </p>
                            <InputError
                                message={errores(errors, 'mecanicos')}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label>Tareas (opcional)</Label>
                            {data.tareas.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    {preventiva && !editando
                                        ? 'Si no escribes ninguna, se cargan solas las del servicio del plan.'
                                        : 'Una lista de lo que el mecánico va marcando.'}
                                </p>
                            )}
                            {data.tareas.map((tarea, indice) => {
                                const hecha =
                                    tarea.id !== null &&
                                    tareasHechas.get(tarea.id)?.hecha;

                                return (
                                    <div
                                        key={tarea.id ?? `nueva-${indice}`}
                                        className="flex items-center gap-2"
                                    >
                                        <Input
                                            className={cn(
                                                'h-11',
                                                hecha &&
                                                    'border-emerald-500/50 bg-emerald-500/5',
                                            )}
                                            aria-label={`Tarea ${indice + 1}`}
                                            value={tarea.descripcion}
                                            onChange={(evento) =>
                                                setData(
                                                    'tareas',
                                                    data.tareas.map(
                                                        (otra, posicion) =>
                                                            posicion === indice
                                                                ? {
                                                                      ...otra,
                                                                      descripcion:
                                                                          evento
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : otra,
                                                    ),
                                                )
                                            }
                                            placeholder="Qué hay que hacer"
                                        />
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="size-11 shrink-0"
                                            aria-label={`Quitar la tarea ${indice + 1}`}
                                            onClick={() =>
                                                setData(
                                                    'tareas',
                                                    data.tareas.filter(
                                                        (_, posicion) =>
                                                            posicion !== indice,
                                                    ),
                                                )
                                            }
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                );
                            })}
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11 self-start"
                                onClick={() =>
                                    setData('tareas', [
                                        ...data.tareas,
                                        { id: null, descripcion: '' },
                                    ])
                                }
                            >
                                <Plus className="size-4" />
                                Agregar tarea
                            </Button>
                            <InputError message={errores(errors, 'tareas')} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="orden-tecnico">
                                Técnico externo (opcional)
                            </Label>
                            <Input
                                id="orden-tecnico"
                                className="h-11"
                                value={data.tecnico}
                                onChange={(evento) =>
                                    setData('tecnico', evento.target.value)
                                }
                            />
                            <InputError message={errors.tecnico} />
                        </div>

                        <SelectorFotos
                            titulo="Fotos de antes"
                            nuevas={data.fotos_antes}
                            onNuevas={(fotos) => setData('fotos_antes', fotos)}
                            guardadas={fotosDe(orden, 'antes')}
                            error={errores(errors, 'fotos_antes')}
                            onPreparando={setPreparandoAntes}
                        />

                        {editando && materiales && (
                            <Materiales
                                ordenId={orden.id}
                                materiales={materiales}
                            />
                        )}

                        {!editando && (
                            <p className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                                Los repuestos los entrega el almacén a nombre de
                                la orden: se sacan después de registrarla.
                            </p>
                        )}
                    </>
                )}

                {paso === 2 && (
                    <>
                        {ejecucion && (
                            <section className="flex flex-col gap-4 rounded-xl border bg-card p-4">
                                <h2 className="text-sm font-semibold">
                                    Requerimiento y trabajo del mecánico
                                </h2>
                                {orden !== null &&
                                    repuestos?.puede_agregar &&
                                    (agregandoRepuesto ? (
                                        <FormularioRepuesto
                                            url={agregarRepuesto(orden.id).url}
                                            articulos={repuestos.articulos}
                                            unidades={repuestos.unidades}
                                            onListo={() =>
                                                setAgregandoRepuesto(false)
                                            }
                                            onCancelar={() =>
                                                setAgregandoRepuesto(false)
                                            }
                                        />
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="h-11 self-start"
                                            onClick={() =>
                                                setAgregandoRepuesto(true)
                                            }
                                        >
                                            <Plus className="size-4" />
                                            Agregar un repuesto a la orden
                                        </Button>
                                    ))}
                                {ejecucion.repuestos.length > 0 && (
                                    <div className="grid gap-1 text-sm">
                                        <span className="flex items-center gap-2 font-medium">
                                            <PackageOpen className="size-4 text-muted-foreground" />
                                            Repuestos que pidió el mecánico
                                        </span>
                                        <ul className="divide-y">
                                            {ejecucion.repuestos.map(
                                                (repuesto) => (
                                                    <li
                                                        key={repuesto.id}
                                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                                    >
                                                        <span className="flex min-w-0 flex-col">
                                                            <span>
                                                                {formatearCantidad(
                                                                    repuesto.cantidad,
                                                                )}{' '}
                                                                {repuesto.unidad ??
                                                                    ''}{' '}
                                                                ·{' '}
                                                                {
                                                                    repuesto.descripcion
                                                                }
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {repuesto.del_catalogo
                                                                    ? `Del almacén · stock ${formatearCantidad(repuesto.stock ?? 0)}`
                                                                    : 'En texto: no está en el catálogo (por comprar)'}
                                                                {repuesto.entregado !==
                                                                    null &&
                                                                    ` · entregado ${formatearCantidad(repuesto.entregado)}`}
                                                                {repuesto.adicional &&
                                                                    ' · adicional'}
                                                            </span>
                                                        </span>
                                                        {repuesto.aprobado ? (
                                                            <span className="flex flex-wrap items-center gap-2">
                                                                {orden !==
                                                                    null &&
                                                                    !repuesto.del_catalogo &&
                                                                    repuestos?.puede_catalogar && (
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant="outline"
                                                                            onClick={() =>
                                                                                router.post(
                                                                                    pasarAlCatalogo(
                                                                                        {
                                                                                            orden: orden.id,
                                                                                            repuesto:
                                                                                                repuesto.id,
                                                                                        },
                                                                                    )
                                                                                        .url,
                                                                                    {},
                                                                                    {
                                                                                        preserveScroll: true,
                                                                                    },
                                                                                )
                                                                            }
                                                                        >
                                                                            Pasar
                                                                            al
                                                                            catálogo
                                                                        </Button>
                                                                    )}
                                                                <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                                    Aprobado
                                                                </span>
                                                            </span>
                                                        ) : orden !== null &&
                                                          repuesto.adicional ? (
                                                            <span className="flex gap-2">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            aprobarRepuesto(
                                                                                {
                                                                                    orden: orden.id,
                                                                                    repuesto:
                                                                                        repuesto.id,
                                                                                },
                                                                            )
                                                                                .url,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Aprobar
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        router.delete(
                                                                            quitarRepuesto(
                                                                                {
                                                                                    orden: orden.id,
                                                                                    repuesto:
                                                                                        repuesto.id,
                                                                                },
                                                                            )
                                                                                .url,
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    No va
                                                                </Button>
                                                            </span>
                                                        ) : (
                                                            <span className="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300">
                                                                Se aprueba con
                                                                el requerimiento
                                                            </span>
                                                        )}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                                {ejecucion.tareas.length > 0 && (
                                    <ul className="grid gap-1 text-sm">
                                        {ejecucion.tareas.map((tarea) => (
                                            <li
                                                key={tarea.id}
                                                className="flex items-start gap-2"
                                            >
                                                <span
                                                    className={cn(
                                                        'mt-0.5 font-semibold',
                                                        tarea.hecha
                                                            ? 'text-emerald-600'
                                                            : 'text-amber-600',
                                                    )}
                                                >
                                                    {tarea.hecha ? '✓' : '○'}
                                                </span>
                                                <span>
                                                    {tarea.descripcion}
                                                    {tarea.hecha_por && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            {tarea.hecha_por}
                                                        </span>
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {orden && puede('mantenimiento.ver') && (
                                    <Link
                                        href={llantasDeUnidad(
                                            orden.vehiculo_id,
                                        )}
                                        className="flex items-center gap-2 text-sm font-medium underline underline-offset-2"
                                    >
                                        <CircleDot className="size-4 text-muted-foreground" />
                                        Llantas de la unidad: montar, rotar o
                                        retirar
                                    </Link>
                                )}
                                {ejecucion.llantas.length > 0 && (
                                    <div className="grid gap-1 text-sm">
                                        <span className="flex items-center gap-2 font-medium">
                                            <CircleDot className="size-4 text-muted-foreground" />
                                            Llantas movidas mientras estuvo
                                            abierta
                                        </span>
                                        <ul className="grid gap-1 text-muted-foreground">
                                            {ejecucion.llantas.map(
                                                (movimiento) => (
                                                    <li key={movimiento.id}>
                                                        {formatearFecha(
                                                            movimiento.fecha,
                                                        )}{' '}
                                                        · {movimiento.tipo} ·
                                                        posición{' '}
                                                        {movimiento.posicion}
                                                        {movimiento.posicion_destino &&
                                                            ` → ${movimiento.posicion_destino}`}
                                                        {movimiento.llanta &&
                                                            ` · ${movimiento.llanta}`}
                                                        {movimiento.usuario &&
                                                            ` · ${movimiento.usuario}`}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                                {(porRevisar ||
                                    orden?.estado === 'requerimiento') && (
                                    <GaleriaFotos
                                        titulo="Antes"
                                        fotos={(orden?.fotos ?? []).filter(
                                            (foto) =>
                                                foto.coleccion === 'antes',
                                        )}
                                    />
                                )}
                                <LineaDeTiempo pasos={ejecucion.pasos} />
                            </section>
                        )}

                        <div className="grid gap-2">
                            <Label>¿Cómo queda la orden?</Label>
                            <div className="grid gap-2">
                                {estados.map((estado) => (
                                    <button
                                        key={estado.value}
                                        type="button"
                                        onClick={() =>
                                            setData('estado', estado.value)
                                        }
                                        aria-pressed={
                                            data.estado === estado.value
                                        }
                                        className={cn(
                                            'flex min-h-14 flex-col items-start justify-center rounded-lg border px-4 py-2 text-left transition-colors',
                                            data.estado === estado.value
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'hover:bg-accent',
                                        )}
                                    >
                                        <span className="font-medium">
                                            {estado.label}
                                        </span>
                                        <span className="text-xs opacity-80">
                                            {QUE_SIGNIFICA[estado.value] ?? ''}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                        <InputError message={errors.estado} />

                        {pideJustificacion && (
                            <div className="grid gap-1.5 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3">
                                <Label htmlFor="orden-justificacion">
                                    ¿Por qué se cierra sin todos los repuestos
                                    del servicio?
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    {editando
                                        ? `Todavía no salió del almacén: ${faltantes.map((f) => f.descripcion).join(', ') || '—'}.`
                                        : 'La orden aún no tiene entregas del almacén. Si el servicio pide repuestos, regístrala «En proceso», registra la entrega y después finalízala; o explica acá por qué se cierra así.'}
                                </p>
                                <Textarea
                                    id="orden-justificacion"
                                    rows={2}
                                    value={data.justificacion_materiales}
                                    onChange={(evento) =>
                                        setData(
                                            'justificacion_materiales',
                                            evento.target.value,
                                        )
                                    }
                                    placeholder="No se cambió porque…"
                                />
                                <InputError
                                    message={errors.justificacion_materiales}
                                />
                            </div>
                        )}

                        <SelectorFotos
                            titulo="Fotos de después"
                            nuevas={data.fotos_despues}
                            onNuevas={(fotos) =>
                                setData('fotos_despues', fotos)
                            }
                            guardadas={fotosDe(orden, 'despues')}
                            error={errores(errors, 'fotos_despues')}
                            progreso={progress?.percentage ?? null}
                            onPreparando={setPreparandoDespues}
                        />

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="orden-inicio">Inicio</Label>
                                <Input
                                    id="orden-inicio"
                                    type="datetime-local"
                                    className="h-11"
                                    value={data.inicio}
                                    onChange={(evento) =>
                                        setData('inicio', evento.target.value)
                                    }
                                />
                                <InputError message={errors.inicio} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="orden-termino">Término</Label>
                                <Input
                                    id="orden-termino"
                                    type="datetime-local"
                                    className="h-11"
                                    value={data.termino}
                                    onChange={(evento) =>
                                        setData('termino', evento.target.value)
                                    }
                                />
                                <InputError message={errors.termino} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="orden-observacion">
                                Observación (opcional)
                            </Label>
                            <Textarea
                                id="orden-observacion"
                                rows={2}
                                value={data.observacion}
                                onChange={(evento) =>
                                    setData('observacion', evento.target.value)
                                }
                            />
                            <InputError message={errors.observacion} />
                        </div>
                    </>
                )}

                {/* Atrás y adelante siempre a mano del pulgar: en el celular
                    la barra queda fija sobre la navegación. Se guarda desde
                    cualquier paso al editar, y al final al crear. */}
                <div className="z-30 flex gap-2 border-t bg-background/95 py-3 backdrop-blur max-md:fixed max-md:inset-x-0 max-md:bottom-[calc(3.5rem+env(safe-area-inset-bottom))] max-md:px-4 md:border-0 md:py-0">
                    {paso > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12"
                            onClick={() => setPaso(paso - 1)}
                        >
                            Atrás
                        </Button>
                    )}
                    {paso < PASOS.length - 1 && (
                        <Button
                            type="button"
                            variant={editando ? 'outline' : 'default'}
                            className="h-12 flex-1 text-base"
                            disabled={paso === 0 && !pasoUnoListo}
                            onClick={() => setPaso(paso + 1)}
                        >
                            Siguiente: {PASOS[paso + 1].toLowerCase()}
                        </Button>
                    )}
                    {(editando || paso === PASOS.length - 1) && (
                        <Button
                            type="submit"
                            className="h-12 flex-1 text-base"
                            disabled={
                                processing ||
                                preparandoAntes ||
                                preparandoDespues
                            }
                        >
                            {processing && <Spinner />}
                            {!editando
                                ? 'Registrar orden'
                                : porRevisar && data.estado === 'finalizada'
                                  ? 'Aprobar y finalizar'
                                  : 'Guardar cambios'}
                        </Button>
                    )}
                </div>
            </form>
        </div>
    );
}

/**
 * Lo que la orden consumió del almacén. No se escribe acá: cada línea es de
 * una entrega a nombre de la orden, y de ahí sale el costo.
 */
function Materiales({
    ordenId,
    materiales,
}: {
    ordenId: number;
    materiales: MaterialesOrden;
}) {
    const { lineas, requeridos, costo } = materiales;

    return (
        <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <div className="flex items-center justify-between gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <PackageOpen className="size-4 text-muted-foreground" />
                    Repuestos del almacén
                </h2>
                {costo !== null && (
                    <span className="text-sm font-semibold tabular-nums">
                        {formatearSoles(costo)}
                    </span>
                )}
            </div>

            {requeridos.length > 0 && (
                <ul className="divide-y text-sm">
                    {requeridos.map((requerido, indice) => (
                        <li
                            key={indice}
                            className="flex items-start justify-between gap-3 py-2"
                        >
                            <span className="flex flex-col">
                                <span>
                                    <span className="mr-2 font-mono text-xs text-muted-foreground">
                                        {requerido.servicio}
                                    </span>
                                    {requerido.descripcion}
                                </span>
                                {!requerido.es_servicio &&
                                    requerido.articulo === null && (
                                        <span className="text-xs text-amber-600 dark:text-amber-400">
                                            Sin artículo de almacén ligado en el
                                            plan
                                        </span>
                                    )}
                            </span>
                            <span
                                className={cn(
                                    'shrink-0 text-right text-xs tabular-nums',
                                    requerido.es_servicio
                                        ? 'text-muted-foreground'
                                        : requerido.falta
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : 'text-emerald-600 dark:text-emerald-400',
                                )}
                            >
                                {requerido.es_servicio
                                    ? 'Servicio'
                                    : `${formatearCantidad(requerido.despachado)} de ${requerido.cantidad === null ? '—' : formatearCantidad(requerido.cantidad)} ${requerido.unidad ?? ''}`}
                                {!requerido.es_servicio &&
                                    requerido.falta &&
                                    requerido.stock !== null && (
                                        <span className="block text-muted-foreground">
                                            Stock:{' '}
                                            {formatearCantidad(requerido.stock)}
                                        </span>
                                    )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {lineas.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Todavía no salió nada del almacén para esta orden.
                </p>
            ) : (
                <ul className="divide-y border-t text-sm">
                    {lineas.map((linea) => (
                        <li
                            key={linea.id}
                            className="flex items-start justify-between gap-3 py-2"
                        >
                            <span className="flex flex-col">
                                <span>{linea.nombre}</span>
                                <span className="text-xs text-muted-foreground">
                                    {materiales.ve_almacen ? (
                                        <Link
                                            href={verVale(linea.salida_id)}
                                            className="underline underline-offset-2"
                                        >
                                            {linea.vale}
                                        </Link>
                                    ) : (
                                        linea.vale
                                    )}
                                    {' · '}
                                    {formatearFecha(linea.fecha)}
                                    {linea.devuelto > 0 &&
                                        ` · devuelto ${formatearCantidad(linea.devuelto)}`}
                                </span>
                            </span>
                            <span className="shrink-0 text-right tabular-nums">
                                {formatearCantidad(
                                    linea.cantidad - linea.devuelto,
                                )}{' '}
                                {linea.unidad}
                                {linea.costo !== null && (
                                    <span className="block text-xs text-muted-foreground">
                                        {formatearSoles(linea.costo)}
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {materiales.terceros.length > 0 && (
                <ul className="divide-y border-t text-sm">
                    {materiales.terceros.map((servicio) => (
                        <li
                            key={servicio.id}
                            className="flex items-start justify-between gap-3 py-2"
                        >
                            <span className="flex flex-col">
                                <span>{servicio.descripcion}</span>
                                <span className="text-xs text-muted-foreground">
                                    Tercero: {servicio.proveedor} ·{' '}
                                    <Link
                                        href={compras.show(servicio.vale_id)}
                                        className="underline underline-offset-2"
                                    >
                                        {servicio.vale}
                                    </Link>
                                </span>
                            </span>
                            <span className="shrink-0 text-right text-xs tabular-nums">
                                {servicio.total !== null &&
                                    formatearSoles(servicio.total)}
                                <span
                                    className={cn(
                                        'block',
                                        servicio.atendido
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-amber-600 dark:text-amber-400',
                                    )}
                                >
                                    {servicio.atendido
                                        ? 'Atendido'
                                        : 'Por confirmar: aún no es costo'}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap gap-2">
                {materiales.puede_despachar && (
                    <Button variant="outline" asChild>
                        <Link
                            href={crearVale.url({ query: { orden: ordenId } })}
                        >
                            Entregar repuestos del almacén
                        </Link>
                    </Button>
                )}
                {materiales.puede_pedir_compra && (
                    <Button variant="outline" asChild>
                        <Link
                            href={compras.create.url({
                                query: { orden: ordenId },
                            })}
                        >
                            Pedir compra o servicio de un tercero
                        </Link>
                    </Button>
                )}
            </div>
            {!materiales.puede_despachar && (
                <p className="text-xs text-muted-foreground">
                    Los repuestos los entrega el almacén a nombre de esta orden.
                    Lo que no se use se devuelve desde el vale.
                </p>
            )}
        </section>
    );
}

/** Botones grandes en fila: con el pulgar se acierta mejor que en un select. */
function Segmentado({
    etiqueta,
    opciones,
    valor,
    onCambio,
}: {
    etiqueta: string;
    opciones: EnumOption[];
    valor: string;
    onCambio: (valor: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{etiqueta}</Label>
            <div
                className="grid gap-2"
                style={{
                    gridTemplateColumns: `repeat(${opciones.length}, minmax(0, 1fr))`,
                }}
            >
                {opciones.map((opcion) => (
                    <button
                        key={opcion.value}
                        type="button"
                        onClick={() => onCambio(opcion.value)}
                        aria-pressed={valor === opcion.value}
                        className={cn(
                            'h-11 rounded-lg border px-2 text-sm font-medium transition-colors',
                            valor === opcion.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent',
                        )}
                    >
                        {opcion.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

function fotosDe(
    orden: Props['orden'],
    coleccion: FotoOrden['coleccion'],
): (FotoOrden & { urlBorrar: string })[] {
    if (orden === null) {
        return [];
    }

    return orden.fotos
        .filter((foto) => foto.coleccion === coleccion)
        .map((foto) => ({
            ...foto,
            urlBorrar: eliminarFoto({ orden: orden.id, foto: foto.id }).url,
        }));
}

/** El primer error del grupo, sea del arreglo o de una foto suelta. */
function errores(
    errors: Partial<Record<string, string>>,
    campo: string,
): string | undefined {
    return (
        errors[campo] ??
        Object.entries(errors).find(([clave]) =>
            clave.startsWith(`${campo}.`),
        )?.[1]
    );
}

/**
 * Los pasos de la orden, arriba: dónde se está, qué falta y, si el servidor
 * rechazó algo, en cuál. Se puede saltar a cualquiera: al editar no hay por
 * qué pasar por los tres.
 */
function Pasos({
    pasos,
    actual,
    conError,
    onIr,
}: {
    pasos: string[];
    actual: number;
    conError: boolean[];
    onIr: (paso: number) => void;
}) {
    return (
        <ol className="grid grid-cols-3 gap-2">
            {pasos.map((titulo, indice) => (
                <li key={titulo}>
                    <button
                        type="button"
                        onClick={() => onIr(indice)}
                        aria-current={indice === actual ? 'step' : undefined}
                        className="flex w-full flex-col gap-1.5 text-left"
                    >
                        <span
                            className={cn(
                                'h-1.5 rounded-full',
                                conError[indice]
                                    ? 'bg-destructive'
                                    : indice <= actual
                                      ? 'bg-primary'
                                      : 'bg-muted',
                            )}
                        />
                        <span
                            className={cn(
                                'text-xs leading-tight',
                                indice === actual
                                    ? 'font-semibold text-foreground'
                                    : 'text-muted-foreground',
                                conError[indice] && 'text-destructive',
                            )}
                        >
                            {indice + 1}. {titulo}
                            {conError[indice] && ' · revisar'}
                        </span>
                    </button>
                </li>
            ))}
        </ol>
    );
}
