import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { PackageOpen } from 'lucide-react';
import { useState } from 'react';
import {
    create as crearVale,
    show as verVale,
} from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import compras from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import {
    anular,
    eliminarFoto,
    store,
    update,
} from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import InputError from '@/components/input-error';
import { formatearKm } from '@/components/mantenimiento/estado-mantenimiento';
import { SelectorFotos } from '@/components/mantenimiento/selector-fotos';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearCantidad } from '@/lib/almacen';
import { formatearFecha, formatearPlaca, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type {
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
};

type Props = {
    /** Null al crear. */
    orden: {
        id: number;
        numero: string;
        vehiculo_id: number;
        puede_anular: boolean;
        fotos: FotoOrden[];
    } | null;
    /** Lo que salió del almacén para la orden; solo al editar. */
    materiales?: MaterialesOrden;
    inicial: Inicial;
    tractos: { id: number; placa: string; marca: string | null }[];
    /** Los servicios del plan de cada marca, por marca en mayúsculas. */
    servicios: Record<string, ServicioPlan[]>;
    tipos: EnumOption[];
    estados: EnumOption[];
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
    fotos_antes: File[];
    fotos_despues: File[];
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
}: Props) {
    const editando = orden !== null;

    // Cada grupo de fotos avisa mientras comprime las suyas.
    const [preparandoAntes, setPreparandoAntes] = useState(false);
    const [preparandoDespues, setPreparandoDespues] = useState(false);

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

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
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

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        // PHP no lee archivos de un PUT: la edición viaja como POST con
        // `_method`, que Laravel convierte en PUT.
        transform((datos) => (editando ? { ...datos, _method: 'put' } : datos));

        post(editando ? update(orden.id).url : store().url, {
            preserveScroll: true,
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
                {editando && orden.puede_anular && (
                    <AnularDialog
                        url={anular(orden.id).url}
                        titulo={`¿Anular la ${orden.numero}?`}
                        descripcion="No se borra: queda a la vista con el motivo. Si era una preventiva, el plan vuelve a contar desde el servicio anterior. Con vales de almacén vigentes no se puede anular."
                    />
                )}
            </div>

            <form onSubmit={guardar} className="flex flex-col gap-5">
                <div className="grid gap-1.5">
                    <Label htmlFor="orden-unidad">Unidad</Label>
                    {editando ? (
                        <p className="text-lg font-semibold">
                            {tracto ? formatearPlaca(tracto.placa) : '—'}
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                {tracto?.marca}
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
                                detalle: `${opcion.placa} ${opcion.marca ?? ''}`,
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
                                {tracto
                                    ? `La marca ${tracto.marca ?? ''} todavía no tiene plan: regístrala como correctiva.`
                                    : 'Elige primero la unidad.'}
                            </p>
                        ) : (
                            <div className="grid grid-cols-4 gap-2">
                                {delPlan.map((servicio) => (
                                    <button
                                        key={servicio.id}
                                        type="button"
                                        onClick={() => elegirServicio(servicio)}
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
                                            {formatearKm(servicio.intervalo_km)}
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
                                    evento.target.value.replace(/\D/g, ''),
                                )
                            }
                        />
                        <InputError message={errors.kilometraje} />
                    </div>
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="orden-trabajo">Trabajo realizado</Label>
                    <Textarea
                        id="orden-trabajo"
                        rows={4}
                        value={data.trabajo}
                        onChange={(evento) =>
                            setData('trabajo', evento.target.value)
                        }
                        placeholder="Qué se hizo, qué se cambió"
                    />
                    <InputError message={errors.trabajo} />
                </div>

                <SelectorFotos
                    titulo="Fotos de antes"
                    nuevas={data.fotos_antes}
                    onNuevas={(fotos) => setData('fotos_antes', fotos)}
                    guardadas={fotosDe(orden, 'antes')}
                    error={errores(errors, 'fotos_antes')}
                    onPreparando={setPreparandoAntes}
                />

                <SelectorFotos
                    titulo="Fotos de después"
                    nuevas={data.fotos_despues}
                    onNuevas={(fotos) => setData('fotos_despues', fotos)}
                    guardadas={fotosDe(orden, 'despues')}
                    error={errores(errors, 'fotos_despues')}
                    progreso={progress?.percentage ?? null}
                    onPreparando={setPreparandoDespues}
                />

                <div className="grid gap-1.5">
                    <Label htmlFor="orden-tecnico">Técnico</Label>
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

                {editando && materiales && (
                    <Materiales ordenId={orden.id} materiales={materiales} />
                )}

                <Segmentado
                    etiqueta="Estado"
                    opciones={estados}
                    valor={data.estado}
                    onCambio={(estado) => setData('estado', estado)}
                />
                <InputError message={errors.estado} />

                {pideJustificacion && (
                    <div className="grid gap-1.5 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3">
                        <Label htmlFor="orden-justificacion">
                            ¿Por qué se cierra sin todos los repuestos del
                            servicio?
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {editando
                                ? `Todavía no salió del almacén: ${faltantes.map((f) => f.descripcion).join(', ') || '—'}.`
                                : 'La orden aún no tiene vales de almacén. Si el servicio pide repuestos, regístrala «En proceso», saca el vale y después finalízala; o explica acá por qué se cierra así.'}
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
                        <InputError message={errors.justificacion_materiales} />
                    </div>
                )}

                <Button
                    type="submit"
                    className="h-12 text-base"
                    disabled={
                        processing || preparandoAntes || preparandoDespues
                    }
                >
                    {processing && <Spinner />}
                    {editando ? 'Guardar cambios' : 'Registrar orden'}
                </Button>
            </form>
        </div>
    );
}

/**
 * Lo que la orden consumió del almacén. No se escribe acá: cada línea es de
 * un vale de salida a nombre de la orden, y de ahí sale el costo.
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
                            Sacar repuestos del almacén
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
                    Los repuestos los entrega el almacén con un vale a nombre de
                    esta orden. Lo que no se use se devuelve desde el vale.
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
