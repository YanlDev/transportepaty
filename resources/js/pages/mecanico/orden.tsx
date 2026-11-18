import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CircleDot,
    PackageOpen,
    Play,
    TriangleAlert,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import {
    eliminarFoto,
    fotos as subirFotos,
    index,
    iniciar,
    marcarTarea,
    terminar,
} from '@/actions/App/Http/Controllers/MisOrdenesController';
import InputError from '@/components/input-error';
import { SelectorFotos } from '@/components/mantenimiento/selector-fotos';
import { EstadoOrden } from '@/components/ordenes/estado-orden';
import { GaleriaFotos } from '@/components/ordenes/galeria-fotos';
import { LineaDeTiempo } from '@/components/ordenes/linea-de-tiempo';
import { Placa } from '@/components/placa';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearCantidad } from '@/lib/almacen';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
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

type Props = { orden: Orden; maximo_fotos: number };

/** La barra del botón principal: fija sobre la navegación en el celular. */
const BARRA =
    'z-30 flex gap-2 border-t bg-background/95 py-3 backdrop-blur max-md:fixed max-md:inset-x-0 max-md:bottom-[calc(3.5rem+env(safe-area-inset-bottom))] max-md:px-4 md:border-0 md:py-0';

/**
 * Una orden, como la trabaja el mecánico desde el celular. La pantalla
 * cambia con el paso en que está:
 *
 * - por empezar: fotos de cómo llegó la unidad y «Empezar»;
 * - en proceso (u observada): marcar tareas, sumar fotos y «Terminar» con
 *   las fotos de cómo quedó;
 * - por revisar o finalizada: solo se mira.
 */
export default function OrdenDeMecanicoPagina({ orden }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Mis órdenes', href: index().url },
            { title: orden.numero, href: '#' },
        ],
    });

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
                            Taller la devolvió:{' '}
                        </span>
                        {orden.observacion_revision}
                    </span>
                </p>
            )}

            {orden.estado === 'por_revisar' && (
                <p className="rounded-xl border bg-muted/50 p-3 text-sm text-muted-foreground">
                    Ya la terminaste. Taller la está revisando: si falta algo,
                    vuelve a tu lista.
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

            {orden.tareas.length > 0 && (
                <Tareas
                    ordenId={orden.id}
                    tareas={orden.tareas}
                    activas={enEjecucion}
                    porEmpezar={porEmpezar}
                />
            )}

            {(orden.materiales.length > 0 || orden.faltantes.length > 0) && (
                <Seccion
                    titulo="Repuestos"
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

            {porEmpezar && <Empezar orden={orden} />}

            {enEjecucion && (
                <>
                    <FotosDurante orden={orden} />
                    <Terminar orden={orden} />
                </>
            )}

            {!porEmpezar && !enEjecucion && (
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

    const subir = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(subirFotos(orden.id).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => reset('fotos'),
        });
    };

    return (
        <form onSubmit={subir}>
            <Seccion titulo="Fotos de cómo llegó">
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
                {data.fotos.length > 0 && (
                    <Button
                        type="submit"
                        variant="outline"
                        className="h-12"
                        disabled={processing || preparando}
                    >
                        {processing && <Spinner />}
                        Guardar{' '}
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
 * Terminar: fotos de cómo quedó, una nota para taller y, en una preventiva,
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
                        Nota para taller
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
