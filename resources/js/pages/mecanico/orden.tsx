import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CircleDot,
    PackageOpen,
    Play,
    Plus,
    Send,
    TriangleAlert,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    agregarTarea,
    eliminarFoto,
    enviar,
    fotos as subirFotos,
    index,
    iniciar,
    marcarTarea,
    pedirRepuesto,
    quitarRepuesto,
    quitarTarea,
    terminar,
} from '@/actions/App/Http/Controllers/MisOrdenesController';
import InputError from '@/components/input-error';
import { SelectorFotos } from '@/components/mantenimiento/selector-fotos';
import { EstadoOrden } from '@/components/ordenes/estado-orden';
import { GaleriaFotos } from '@/components/ordenes/galeria-fotos';
import { LineaDeTiempo } from '@/components/ordenes/linea-de-tiempo';
import { Placa } from '@/components/placa';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearCantidad } from '@/lib/almacen';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type {
    EjecucionDeOrden,
    FotoOrden,
    OrdenDeMecanico,
    TareaDeOrden,
} from '@/types/mantenimiento';

type Orden = OrdenDeMecanico &
    EjecucionDeOrden & {
        kilometraje: number | null;
        /** Una preventiva necesita el km para cerrar. */
        pide_km: boolean;
        observacion: string | null;
        materiales: {
            id: number;
            nombre: string;
            cantidad: number;
            unidad: string;
            vale: string;
        }[];
        /** Lo que el servicio pide y el almacén todavía no entregó. */
        faltantes: string[];
    };

/** Un artículo del almacén que se puede pedir: sin costos. */
type ArticuloPedible = {
    id: number;
    nombre: string;
    codigo: string;
    unidad: string;
    hay: boolean;
};

type Props = {
    orden: Orden;
    maximo_fotos: number;
    articulos: ArticuloPedible[];
    unidades: EnumOption[];
};

/** La barra del botón principal: fija sobre la navegación en el celular. */
const BARRA =
    'z-30 flex gap-2 border-t bg-background/95 py-3 backdrop-blur max-md:fixed max-md:inset-x-0 max-md:bottom-[calc(3.5rem+env(safe-area-inset-bottom))] max-md:px-4 md:border-0 md:py-0';

/**
 * Una orden, como la trabaja el mecánico desde el celular. La pantalla
 * cambia con el paso en que está:
 *
 * - pre-orden: arma el requerimiento (tareas, repuestos, fotos de cómo
 *   llegó) y lo envía a operaciones;
 * - requerimiento enviado: espera la aprobación;
 * - por empezar: «Empezar» (las fotos del requerimiento valen);
 * - en proceso (u observada): marcar tareas, sumar fotos y «Terminar» con
 *   las fotos de cómo quedó;
 * - por revisar o finalizada: solo se mira.
 */
export default function OrdenDeMecanicoPagina({
    orden,
    articulos,
    unidades,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Mis órdenes', href: index().url },
            { title: orden.numero, href: '#' },
        ],
    });

    const armando = orden.estado === 'solicitada';
    const porEmpezar = orden.estado === 'pendiente';
    const enEjecucion =
        orden.estado === 'en_proceso' || orden.estado === 'observada';

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4 max-md:pb-28 md:p-6">
            <Head title={`${orden.numero} · ${orden.placa}`} />

            <Link
                href={index()}
                className="flex min-h-11 items-center gap-2 self-start text-sm text-muted-foreground"
            >
                <ArrowLeft className="size-4" />
                Mis órdenes
            </Link>

            <header className="flex items-center gap-3">
                <Placa placa={orden.placa} tamano="lg" />
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <h1 className="text-lg leading-tight font-semibold">
                        {orden.numero} · {orden.tipo}
                        {orden.servicio && ` ${orden.servicio}`}
                    </h1>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <EstadoOrden
                            estado={orden.estado}
                            etiqueta={orden.estado_label}
                        />
                        {orden.urgente && (
                            <span className="rounded-full bg-red-600 px-2 py-0.5 font-semibold text-white">
                                Urgente
                            </span>
                        )}
                        <span>
                            {orden.tipo_unidad}
                            {orden.marca && ` ${orden.marca}`}
                        </span>
                    </div>
                </div>
            </header>

            {orden.estado === 'observada' && orden.observacion_revision && (
                <p className="flex items-start gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                    <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-600" />
                    <span>
                        <span className="font-semibold">
                            Operaciones la devolvió:{' '}
                        </span>
                        {orden.observacion_revision}
                    </span>
                </p>
            )}

            {orden.estado === 'por_revisar' && (
                <p className="rounded-xl border bg-muted/50 p-3 text-sm text-muted-foreground">
                    Ya la terminaste. Operaciones la está validando: si falta
                    algo, vuelve a tu lista.
                </p>
            )}

            {armando && (
                <p className="rounded-xl border border-violet-500/40 bg-violet-500/10 p-3 text-sm">
                    <span className="font-semibold">Es una pre-orden. </span>
                    Revisa la unidad y arma tu requerimiento: qué vas a hacer,
                    qué repuestos necesitas y las fotos de cómo llegó. Después
                    envíalo a operaciones.
                </p>
            )}

            {armando && orden.devolucion_requerimiento && (
                <p className="flex items-start gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                    <TriangleAlert className="mt-0.5 size-5 shrink-0 text-amber-600" />
                    <span>
                        <span className="font-semibold">
                            Operaciones devolvió tu requerimiento:{' '}
                        </span>
                        {orden.devolucion_requerimiento}
                    </span>
                </p>
            )}

            {orden.estado === 'requerimiento' && (
                <p className="rounded-xl border bg-muted/50 p-3 text-sm text-muted-foreground">
                    Enviaste el requerimiento. Cuando operaciones lo apruebe, la
                    orden aparece en tu lista para empezar.
                </p>
            )}

            <Seccion titulo="Qué hay que hacer">
                <p className="text-sm whitespace-pre-line">{orden.trabajo}</p>
                {orden.observacion && (
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {orden.observacion}
                    </p>
                )}
                {orden.companeros.length > 0 && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Users className="size-4" />
                        Lo haces con {orden.companeros.join(', ')}
                    </p>
                )}
            </Seccion>

            {armando && (
                <TareasDelRequerimiento
                    ordenId={orden.id}
                    tareas={orden.tareas}
                />
            )}

            {!armando && orden.tareas.length > 0 && (
                <Tareas
                    ordenId={orden.id}
                    tareas={orden.tareas}
                    activas={enEjecucion}
                    porEmpezar={porEmpezar}
                />
            )}

            <RepuestosPedidos
                orden={orden}
                articulos={articulos}
                unidades={unidades}
                puedePedir={armando || porEmpezar || enEjecucion}
                adicional={!armando}
            />

            {(orden.materiales.length > 0 || orden.faltantes.length > 0) && (
                <Seccion
                    titulo="Entregado por el almacén"
                    icono={
                        <PackageOpen className="size-4 text-muted-foreground" />
                    }
                >
                    {orden.materiales.length > 0 && (
                        <ul className="divide-y text-sm">
                            {orden.materiales.map((linea) => (
                                <li
                                    key={linea.id}
                                    className="flex items-start justify-between gap-3 py-2"
                                >
                                    <span className="flex flex-col">
                                        {linea.nombre}
                                        <span className="text-xs text-muted-foreground">
                                            {linea.vale}
                                        </span>
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {formatearCantidad(linea.cantidad)}{' '}
                                        {linea.unidad}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {orden.faltantes.length > 0 && (
                        <div className="rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm">
                            <p className="font-medium">
                                Pídele al almacén lo que falta:
                            </p>
                            <ul className="mt-1 list-disc pl-5 text-muted-foreground">
                                {orden.faltantes.map((faltante) => (
                                    <li key={faltante}>{faltante}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </Seccion>
            )}

            {orden.llantas.length > 0 && (
                <Seccion
                    titulo="Llantas"
                    icono={
                        <CircleDot className="size-4 text-muted-foreground" />
                    }
                >
                    <ul className="divide-y text-sm">
                        {orden.llantas.map((movimiento) => (
                            <li
                                key={movimiento.id}
                                className="flex items-start justify-between gap-3 py-2"
                            >
                                <span className="flex flex-col">
                                    <span>
                                        {movimiento.tipo} · posición{' '}
                                        {movimiento.posicion}
                                        {movimiento.posicion_destino &&
                                            ` → ${movimiento.posicion_destino}`}
                                    </span>
                                    {movimiento.llanta && (
                                        <span className="text-xs text-muted-foreground">
                                            {movimiento.llanta}
                                        </span>
                                    )}
                                </span>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {formatearFecha(movimiento.fecha)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Seccion>
            )}

            {armando && (
                <>
                    <FotosDurante orden={orden} />
                    <EnviarRequerimiento orden={orden} />
                </>
            )}

            {porEmpezar && <Empezar orden={orden} />}

            {enEjecucion && (
                <>
                    <FotosDurante orden={orden} />
                    <Terminar orden={orden} />
                </>
            )}

            {!armando && !porEmpezar && !enEjecucion && (
                <Seccion titulo="Fotos">
                    <GaleriaFotos
                        titulo="Antes"
                        fotos={deColeccion(orden.fotos, 'antes')}
                    />
                    <GaleriaFotos
                        titulo="Después"
                        fotos={deColeccion(orden.fotos, 'despues')}
                    />
                </Seccion>
            )}

            <Seccion titulo="Historial">
                <LineaDeTiempo pasos={orden.pasos} />
            </Seccion>
        </div>
    );
}

/**
 * La lista de tareas. Cada fila es un botón de 56 px: se marca con el pulgar
 * y queda quién la hizo.
 */
function Tareas({
    ordenId,
    tareas,
    activas,
    porEmpezar,
}: {
    ordenId: number;
    tareas: TareaDeOrden[];
    activas: boolean;
    porEmpezar: boolean;
}) {
    const [enviando, setEnviando] = useState<number | null>(null);
    const hechas = tareas.filter((tarea) => tarea.hecha).length;

    const marcar = (tarea: TareaDeOrden) => {
        setEnviando(tarea.id);
        router.post(
            marcarTarea({ orden: ordenId, tarea: tarea.id }).url,
            { hecha: !tarea.hecha },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['orden', 'toast', 'errors'],
                onFinish: () => setEnviando(null),
            },
        );
    };

    return (
        <Seccion
            titulo="Tareas"
            extra={
                <span className="text-xs text-muted-foreground tabular-nums">
                    {hechas} de {tareas.length}
                </span>
            }
        >
            <ul className="grid gap-2">
                {tareas.map((tarea) => (
                    <li key={tarea.id}>
                        <button
                            type="button"
                            disabled={!activas || enviando === tarea.id}
                            onClick={() => marcar(tarea)}
                            aria-pressed={tarea.hecha}
                            className={cn(
                                'flex min-h-14 w-full items-center gap-3 rounded-lg border px-3 py-2 text-left text-sm transition-colors',
                                tarea.hecha
                                    ? 'border-emerald-500/40 bg-emerald-500/10'
                                    : 'bg-background',
                                activas && 'active:bg-accent',
                                !activas && 'opacity-70',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-md border-2',
                                    tarea.hecha
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-muted-foreground/40',
                                )}
                            >
                                {enviando === tarea.id ? (
                                    <Spinner />
                                ) : (
                                    tarea.hecha && <Check className="size-5" />
                                )}
                            </span>
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span
                                    className={cn(
                                        tarea.hecha && 'text-muted-foreground',
                                    )}
                                >
                                    {tarea.descripcion}
                                </span>
                                {tarea.hecha && tarea.hecha_por && (
                                    <span className="text-xs text-muted-foreground">
                                        {tarea.hecha_por}
                                    </span>
                                )}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
            {porEmpezar && (
                <p className="text-xs text-muted-foreground">
                    Las tareas se marcan después de empezar el trabajo.
                </p>
            )}
        </Seccion>
    );
}

/**
 * Empezar: la foto de cómo llegó la unidad es parte del paso. Sin ella el
 * botón no se habilita (y el servidor tampoco lo acepta).
 */
function Empezar({ orden }: { orden: Orden }) {
    const [preparando, setPreparando] = useState(false);
    const { data, setData, post, processing, progress, errors } = useForm<{
        fotos: File[];
        kilometraje: string;
    }>({
        fotos: [],
        kilometraje:
            orden.kilometraje === null ? '' : String(orden.kilometraje),
    });

    const guardadas = deColeccion(orden.fotos, 'antes');
    const conFoto = guardadas.length + data.fotos.length > 0;

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(iniciar(orden.id).url, {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <form onSubmit={enviar} className="flex flex-col gap-4">
            <Seccion titulo="1. Fotos de cómo llegó">
                <p className="text-sm text-muted-foreground">
                    Toma la falla o la pieza antes de tocarla. Sin al menos una
                    foto no se puede empezar.
                </p>
                <SelectorFotos
                    titulo="Antes"
                    nuevas={data.fotos}
                    onNuevas={(fotos) => setData('fotos', fotos)}
                    guardadas={conBorrado(orden.id, guardadas)}
                    progreso={progress?.percentage ?? null}
                    error={errorDe(errors, 'fotos')}
                    onPreparando={setPreparando}
                />
                <CampoKm
                    valor={data.kilometraje}
                    onCambio={(valor) => setData('kilometraje', valor)}
                    error={errors.kilometraje}
                    opcional={!orden.pide_km}
                />
                <InputError
                    message={(errors as Record<string, string>).estado}
                />
            </Seccion>

            <div className={BARRA}>
                <Button
                    type="submit"
                    className="h-14 flex-1 text-base"
                    disabled={processing || preparando || !conFoto}
                >
                    {processing ? <Spinner /> : <Play className="size-5" />}
                    Empezar trabajo
                </Button>
            </div>
        </form>
    );
}

/** Fotos que se suman mientras se trabaja, al grupo del antes. */
function FotosDurante({ orden }: { orden: Orden }) {
    const [preparando, setPreparando] = useState(false);
    const { data, setData, post, processing, progress, errors, reset } =
        useForm<{ coleccion: 'antes'; fotos: File[] }>({
            coleccion: 'antes',
            fotos: [],
        });

    const subir = (evento?: React.FormEvent) => {
        evento?.preventDefault();
        post(subirFotos(orden.id).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => reset('fotos'),
        });
    };

    // En el celular la foto se guarda sola apenas se toma: un toque menos, y
    // no se pierde si se cierra la pantalla.
    const listas = data.fotos.length > 0 && !preparando && !processing;
    const conError = Object.keys(errors).length > 0;
    useEffect(() => {
        if (listas && !conError) {
            subir();
        }
        // Solo cuando hay fotos listas para subir.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [listas, conError]);

    return (
        <form onSubmit={subir}>
            <Seccion
                titulo={
                    orden.estado === 'solicitada'
                        ? '3. Fotos de cómo llegó'
                        : 'Fotos de cómo llegó'
                }
            >
                <SelectorFotos
                    titulo="Antes"
                    nuevas={data.fotos}
                    onNuevas={(fotos) => setData('fotos', fotos)}
                    guardadas={conBorrado(
                        orden.id,
                        deColeccion(orden.fotos, 'antes'),
                    )}
                    progreso={progress?.percentage ?? null}
                    error={errorDe(errors, 'fotos')}
                    onPreparando={setPreparando}
                />
                {data.fotos.length > 0 && conError && (
                    <Button
                        type="submit"
                        variant="outline"
                        className="h-12"
                        disabled={processing || preparando}
                    >
                        {processing && <Spinner />}
                        Reintentar: guardar{' '}
                        {data.fotos.length === 1
                            ? 'la foto'
                            : `las ${data.fotos.length} fotos`}
                    </Button>
                )}
            </Seccion>
        </form>
    );
}

/**
 * Terminar: fotos de cómo quedó, una nota para operaciones y, en una preventiva,
 * el km. Si quedan tareas sin marcar, la nota es obligatoria.
 */
function Terminar({ orden }: { orden: Orden }) {
    const [preparando, setPreparando] = useState(false);
    const { data, setData, post, processing, progress, errors } = useForm<{
        fotos: File[];
        nota: string;
        kilometraje: string;
    }>({
        fotos: [],
        nota: orden.nota_mecanico ?? '',
        kilometraje:
            orden.kilometraje === null ? '' : String(orden.kilometraje),
    });

    const guardadas = deColeccion(orden.fotos, 'despues');
    const conFoto = guardadas.length + data.fotos.length > 0;
    const sinHacer = orden.tareas.filter((tarea) => !tarea.hecha).length;

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(terminar(orden.id).url, {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <form onSubmit={enviar} className="flex flex-col gap-4">
            <Seccion titulo="Para terminar: fotos de cómo quedó">
                <p className="text-sm text-muted-foreground">
                    Toma el trabajo terminado. Sin al menos una foto no se puede
                    terminar.
                </p>
                <SelectorFotos
                    titulo="Después"
                    nuevas={data.fotos}
                    onNuevas={(fotos) => setData('fotos', fotos)}
                    guardadas={conBorrado(orden.id, guardadas)}
                    progreso={progress?.percentage ?? null}
                    error={errorDe(errors, 'fotos')}
                    onPreparando={setPreparando}
                />

                {orden.pide_km && (
                    <CampoKm
                        valor={data.kilometraje}
                        onCambio={(valor) => setData('kilometraje', valor)}
                        error={errors.kilometraje}
                    />
                )}

                <div className="grid gap-1.5">
                    <Label htmlFor="orden-nota">
                        Nota para operaciones
                        {sinHacer === 0 && ' (opcional)'}
                    </Label>
                    {sinHacer > 0 && (
                        <p className="text-xs text-amber-700 dark:text-amber-400">
                            {sinHacer === 1
                                ? 'Queda 1 tarea sin marcar: márcala o explica por qué no se hizo.'
                                : `Quedan ${sinHacer} tareas sin marcar: márcalas o explica por qué no se hicieron.`}
                        </p>
                    )}
                    <Textarea
                        id="orden-nota"
                        rows={3}
                        value={data.nota}
                        onChange={(evento) =>
                            setData('nota', evento.target.value)
                        }
                        placeholder="Qué se hizo, qué quedó pendiente"
                    />
                    <InputError message={errors.nota} />
                </div>
                <InputError
                    message={(errors as Record<string, string>).estado}
                />
            </Seccion>

            <div className={BARRA}>
                <Button
                    type="submit"
                    className="h-14 flex-1 bg-emerald-600 text-base text-white hover:bg-emerald-700"
                    disabled={processing || preparando || !conFoto}
                >
                    {processing ? <Spinner /> : <Check className="size-5" />}
                    Terminar trabajo
                </Button>
            </div>
        </form>
    );
}

/**
 * Las tareas del requerimiento: el mecánico escribe qué le va a hacer a la
 * unidad, una por una. Se pueden quitar hasta que se envía.
 */
function TareasDelRequerimiento({
    ordenId,
    tareas,
}: {
    ordenId: number;
    tareas: TareaDeOrden[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        descripcion: '',
    });

    const agregar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(agregarTarea(ordenId).url, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Seccion
            titulo="1. Qué vas a hacer"
            extra={
                <span className="text-xs text-muted-foreground tabular-nums">
                    {tareas.length === 1
                        ? '1 tarea'
                        : `${tareas.length} tareas`}
                </span>
            }
        >
            {tareas.length > 0 && (
                <ul className="grid gap-2">
                    {tareas.map((tarea) => (
                        <li
                            key={tarea.id}
                            className="flex min-h-12 items-center gap-2 rounded-lg border bg-background pr-1 pl-3 text-sm"
                        >
                            <span className="flex-1 py-2">
                                {tarea.descripcion}
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-11 shrink-0"
                                aria-label={`Quitar la tarea ${tarea.descripcion}`}
                                onClick={() =>
                                    router.delete(
                                        quitarTarea({
                                            orden: ordenId,
                                            tarea: tarea.id,
                                        }).url,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <X className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <form onSubmit={agregar} className="flex items-start gap-2">
                <div className="grid flex-1 gap-1">
                    <Input
                        className="h-12"
                        aria-label="Nueva tarea"
                        value={data.descripcion}
                        onChange={(evento) =>
                            setData('descripcion', evento.target.value)
                        }
                        placeholder="Ej.: cambiar la válvula relé"
                    />
                    <InputError
                        message={
                            errors.descripcion ??
                            (errors as Record<string, string>).tarea
                        }
                    />
                </div>
                <Button
                    type="submit"
                    className="h-12 shrink-0"
                    disabled={processing || data.descripcion.trim() === ''}
                >
                    {processing ? <Spinner /> : <Plus className="size-5" />}
                    Agregar
                </Button>
            </form>
        </Seccion>
    );
}

/**
 * Los repuestos que el mecánico pide. Se eligen del almacén si están ahí, o
 * se escriben. En el requerimiento van con él; con la orden ya aprobada son
 * adicionales que operaciones aprueba aparte.
 */
function RepuestosPedidos({
    orden,
    articulos,
    unidades,
    puedePedir,
    adicional,
}: {
    orden: Orden;
    articulos: ArticuloPedible[];
    unidades: EnumOption[];
    puedePedir: boolean;
    adicional: boolean;
}) {
    const hayCatalogo = articulos.length > 0;
    const [abierto, setAbierto] = useState(false);
    const [delAlmacen, setDelAlmacen] = useState(hayCatalogo);
    const { data, setData, post, processing, errors, reset, transform } =
        useForm<{
            articulo_id: number | null;
            descripcion: string;
            cantidad: string;
            unidad: string;
        }>({
            articulo_id: null,
            descripcion: '',
            cantidad: '1',
            unidad: 'und',
        });

    if (orden.repuestos.length === 0 && !puedePedir) {
        return null;
    }

    const elegido = articulos.find((a) => a.id === data.articulo_id);

    const pedir = (evento: React.FormEvent) => {
        evento.preventDefault();
        transform((datos) =>
            delAlmacen
                ? { articulo_id: datos.articulo_id, cantidad: datos.cantidad }
                : {
                      descripcion: datos.descripcion,
                      cantidad: datos.cantidad,
                      unidad: datos.unidad,
                  },
        );
        post(pedirRepuesto(orden.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAbierto(false);
            },
        });
    };

    return (
        <Seccion
            titulo={
                adicional ? 'Repuestos pedidos' : '2. Qué repuestos necesitas'
            }
            icono={<PackageOpen className="size-4 text-muted-foreground" />}
        >
            {orden.repuestos.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {adicional
                        ? 'No se pidieron repuestos.'
                        : 'Si el trabajo no necesita repuestos, déjalo vacío.'}
                </p>
            ) : (
                <ul className="divide-y text-sm">
                    {orden.repuestos.map((repuesto) => (
                        <li
                            key={repuesto.id}
                            className="flex items-center gap-2 py-2"
                        >
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span>{repuesto.descripcion}</span>
                                <span className="text-xs text-muted-foreground">
                                    {formatearCantidad(repuesto.cantidad)}{' '}
                                    {repuesto.unidad ?? ''}
                                    {!repuesto.del_catalogo &&
                                        ' · no está en el almacén'}
                                    {repuesto.adicional && ' · adicional'}
                                </span>
                            </span>
                            <EstadoRepuesto repuesto={repuesto} />
                            {puedePedir && !repuesto.aprobado && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-11 shrink-0"
                                    aria-label={`Quitar ${repuesto.descripcion}`}
                                    onClick={() =>
                                        router.delete(
                                            quitarRepuesto({
                                                orden: orden.id,
                                                repuesto: repuesto.id,
                                            }).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <X className="size-4" />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {puedePedir && !abierto && (
                <Button
                    type="button"
                    variant="outline"
                    className="h-12"
                    onClick={() => setAbierto(true)}
                >
                    <Plus className="size-5" />
                    {adicional
                        ? 'Pedir repuesto adicional'
                        : 'Agregar repuesto'}
                </Button>
            )}

            {puedePedir && abierto && (
                <form
                    onSubmit={pedir}
                    className="grid gap-3 rounded-lg border bg-background p-3"
                >
                    {hayCatalogo && (
                        <div className="grid grid-cols-2 gap-2">
                            {[
                                { valor: true, texto: 'Del almacén' },
                                { valor: false, texto: 'No está en la lista' },
                            ].map((opcion) => (
                                <button
                                    key={opcion.texto}
                                    type="button"
                                    aria-pressed={delAlmacen === opcion.valor}
                                    onClick={() => setDelAlmacen(opcion.valor)}
                                    className={cn(
                                        'h-11 rounded-lg border text-sm font-medium transition-colors',
                                        delAlmacen === opcion.valor
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'hover:bg-accent',
                                    )}
                                >
                                    {opcion.texto}
                                </button>
                            ))}
                        </div>
                    )}

                    {delAlmacen ? (
                        <div className="grid gap-1.5">
                            <Label htmlFor="repuesto-articulo">Repuesto</Label>
                            <SelectorBuscable
                                id="repuesto-articulo"
                                valor={data.articulo_id}
                                onCambio={(valor) =>
                                    setData('articulo_id', valor)
                                }
                                etiqueta="Repuesto"
                                opciones={articulos.map((articulo) => ({
                                    valor: articulo.id,
                                    etiqueta: articulo.nombre,
                                    detalle: `${articulo.codigo} ${articulo.hay ? '' : 'sin stock'}`,
                                }))}
                                invalido={!!errors.articulo_id}
                            />
                            {elegido && (
                                <p
                                    className={cn(
                                        'text-xs',
                                        elegido.hay
                                            ? 'text-emerald-700 dark:text-emerald-400'
                                            : 'text-amber-700 dark:text-amber-400',
                                    )}
                                >
                                    {elegido.hay
                                        ? 'Hay en el almacén.'
                                        : 'No hay stock: se tendrá que comprar.'}
                                </p>
                            )}
                            <InputError
                                message={
                                    errors.articulo_id ?? errors.descripcion
                                }
                            />
                        </div>
                    ) : (
                        <div className="grid gap-1.5">
                            <Label htmlFor="repuesto-descripcion">
                                ¿Qué repuesto es?
                            </Label>
                            <Input
                                id="repuesto-descripcion"
                                className="h-12"
                                value={data.descripcion}
                                onChange={(evento) =>
                                    setData('descripcion', evento.target.value)
                                }
                                placeholder="Nombre, medida, marca"
                            />
                            <InputError message={errors.descripcion} />
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="repuesto-cantidad">Cantidad</Label>
                            <Input
                                id="repuesto-cantidad"
                                inputMode="decimal"
                                className="h-12 text-lg tabular-nums"
                                value={data.cantidad}
                                onChange={(evento) =>
                                    setData(
                                        'cantidad',
                                        evento.target.value.replace(
                                            /[^0-9.]/g,
                                            '',
                                        ),
                                    )
                                }
                            />
                            <InputError message={errors.cantidad} />
                        </div>
                        {!delAlmacen && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="repuesto-unidad">Unidad</Label>
                                <select
                                    id="repuesto-unidad"
                                    value={data.unidad}
                                    onChange={(evento) =>
                                        setData('unidad', evento.target.value)
                                    }
                                    className="h-12 rounded-md border bg-background px-3 text-sm"
                                >
                                    {unidades.map((unidad) => (
                                        <option
                                            key={unidad.value}
                                            value={unidad.value}
                                        >
                                            {unidad.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    {adicional && (
                        <p className="text-xs text-muted-foreground">
                            Es un adicional: operaciones lo tiene que aprobar
                            antes de que el almacén lo entregue.
                        </p>
                    )}

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12"
                            onClick={() => setAbierto(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            className="h-12 flex-1"
                            disabled={
                                processing ||
                                (delAlmacen
                                    ? data.articulo_id === null
                                    : data.descripcion.trim() === '')
                            }
                        >
                            {processing && <Spinner />}
                            {adicional ? 'Pedir' : 'Agregar'}
                        </Button>
                    </div>
                </form>
            )}
        </Seccion>
    );
}

/** En qué va un repuesto pedido: por aprobar, aprobado o ya entregado. */
function EstadoRepuesto({
    repuesto,
}: {
    repuesto: EjecucionDeOrden['repuestos'][number];
}) {
    const entregado =
        repuesto.entregado !== null && repuesto.entregado >= repuesto.cantidad;
    const [texto, tono] = !repuesto.aprobado
        ? ['Por aprobar', 'bg-amber-500/20 text-amber-800 dark:text-amber-300']
        : entregado
          ? [
                'Entregado',
                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
            ]
          : repuesto.entregado !== null && repuesto.entregado > 0
            ? [
                  `Entregado ${formatearCantidad(repuesto.entregado)}`,
                  'bg-sky-500/15 text-sky-700 dark:text-sky-300',
              ]
            : ['Aprobado', 'bg-sky-500/15 text-sky-700 dark:text-sky-300'];

    return (
        <span
            className={cn(
                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                tono,
            )}
        >
            {texto}
        </span>
    );
}

/**
 * Enviar el requerimiento a operaciones. Hace falta al menos una tarea y una
 * foto de cómo llegó la unidad; el botón dice qué falta.
 */
function EnviarRequerimiento({ orden }: { orden: Orden }) {
    const { post, processing, errors } = useForm({});
    const sinTareas = orden.tareas.length === 0;
    const sinFotos = deColeccion(orden.fotos, 'antes').length === 0;
    const errores = errors as Record<string, string | undefined>;

    return (
        <form
            onSubmit={(evento) => {
                evento.preventDefault();
                post(enviar(orden.id).url, { preserveScroll: true });
            }}
            className="flex flex-col gap-2"
        >
            {(sinTareas || sinFotos) && (
                <p className="text-sm text-muted-foreground">
                    Para enviar falta:{' '}
                    {[
                        sinTareas && 'al menos una tarea',
                        sinFotos && 'al menos una foto de cómo llegó',
                    ]
                        .filter(Boolean)
                        .join(' y ')}
                    .
                </p>
            )}
            <InputError
                message={errores.tareas ?? errores.fotos ?? errores.estado}
            />
            <div className={BARRA}>
                <Button
                    type="submit"
                    className="h-14 flex-1 text-base"
                    disabled={processing || sinTareas || sinFotos}
                >
                    {processing ? <Spinner /> : <Send className="size-5" />}
                    Enviar a operaciones
                </Button>
            </div>
        </form>
    );
}

function CampoKm({
    valor,
    onCambio,
    error,
    opcional = false,
}: {
    valor: string;
    onCambio: (valor: string) => void;
    error?: string;
    opcional?: boolean;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor="orden-km">
                Km del tablero{opcional && ' (opcional)'}
            </Label>
            <Input
                id="orden-km"
                inputMode="numeric"
                pattern="[0-9]*"
                className="h-12 text-lg tabular-nums"
                value={valor}
                onChange={(evento) =>
                    onCambio(evento.target.value.replace(/\D/g, ''))
                }
            />
            <InputError message={error} />
        </div>
    );
}

function Seccion({
    titulo,
    icono,
    extra,
    children,
}: {
    titulo: string;
    icono?: React.ReactNode;
    extra?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
            <div className="flex items-center justify-between gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    {icono}
                    {titulo}
                </h2>
                {extra}
            </div>
            {children}
        </section>
    );
}

function deColeccion(
    fotos: FotoOrden[],
    coleccion: FotoOrden['coleccion'],
): FotoOrden[] {
    return fotos.filter((foto) => foto.coleccion === coleccion);
}

function conBorrado(
    ordenId: number,
    fotos: FotoOrden[],
): (FotoOrden & { urlBorrar: string })[] {
    return fotos.map((foto) => ({
        ...foto,
        urlBorrar: eliminarFoto({ orden: ordenId, foto: foto.id }).url,
    }));
}

/** El primer error del grupo, sea del arreglo o de una foto suelta. */
function errorDe(
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
