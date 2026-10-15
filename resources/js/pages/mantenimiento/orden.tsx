import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import {
    destroy,
    eliminarFoto,
    store,
    update,
} from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { ConfirmarBorradoDialog } from '@/components/confirmar-borrado-dialog';
import InputError from '@/components/input-error';
import { formatearKm } from '@/components/mantenimiento/estado-mantenimiento';
import { SelectorFotos } from '@/components/mantenimiento/selector-fotos';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type { FotoOrden, ServicioPlan } from '@/types/mantenimiento';

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
};

type Props = {
    /** Null al crear. */
    orden: { id: number; vehiculo_id: number; fotos: FotoOrden[] } | null;
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
    inicial,
    tractos,
    servicios,
    tipos,
    estados,
}: Props) {
    const editando = orden !== null;

    const { data, setData, post, transform, processing, errors } =
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
            fotos_antes: [],
            fotos_despues: [],
        });

    const tracto = tractos.find((opcion) => opcion.id === data.vehiculo_id);
    const delPlan = tracto?.marca ? (servicios[tracto.marca] ?? []) : [];
    const preventiva = data.tipo === 'preventiva';

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
                        ? 'Editar orden de trabajo'
                        : 'Nueva orden de trabajo'}
                </h1>
                {editando && (
                    <ConfirmarBorradoDialog
                        url={destroy(orden.id).url}
                        titulo="¿Eliminar esta orden?"
                        descripcion="Se borra con sus fotos. Si era una preventiva, el plan vuelve a contar desde el servicio anterior."
                        trigger={
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Eliminar la orden"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        }
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
                />

                <SelectorFotos
                    titulo="Fotos de después"
                    nuevas={data.fotos_despues}
                    onNuevas={(fotos) => setData('fotos_despues', fotos)}
                    guardadas={fotosDe(orden, 'despues')}
                    error={errores(errors, 'fotos_despues')}
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

                <Segmentado
                    etiqueta="Estado"
                    opciones={estados}
                    valor={data.estado}
                    onCambio={(estado) => setData('estado', estado)}
                />
                <InputError message={errors.estado} />

                <Button
                    type="submit"
                    className="h-12 text-base"
                    disabled={processing}
                >
                    {processing && <Spinner />}
                    {editando ? 'Guardar cambios' : 'Registrar orden'}
                </Button>
            </form>
        </div>
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
