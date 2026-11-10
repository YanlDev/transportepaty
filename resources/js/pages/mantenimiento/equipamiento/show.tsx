import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowLeftRight,
    CalendarClock,
    ClipboardList,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import equipamiento, {
    anularMovimiento,
    darDeBaja,
    trasladar,
    vencimiento,
} from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { ConfirmarBorradoDialog } from '@/components/confirmar-borrado-dialog';
import { ConteoDialog } from '@/components/equipamiento/conteo-dialog';
import { EstadoEquipamientoBadge } from '@/components/equipamiento/estado-equipamiento';
import InputError from '@/components/input-error';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ConteoEquipamiento,
    EquipamientoCarreta,
    ItemCarreta,
    MovimientoEquipamiento,
} from '@/types/equipamiento';

type Props = {
    carreta: EquipamientoCarreta;
    conteos: ConteoEquipamiento[];
    movimientos: MovimientoEquipamiento[];
    /** Las demás unidades, para los traslados. */
    unidades: { id: number; placa: string; tipo: string }[];
    puedeAnular: boolean;
    hoy: string;
};

type Accion = { tipo: 'traslado' | 'baja' | 'vencimiento'; item: ItemCarreta };

/**
 * El equipamiento de una unidad: lo que tiene asignado frente al mínimo, sus
 * vencimientos, y de dónde salió cada cosa. Lo nuevo llega del almacén con un
 * vale a nombre de la unidad; acá se cuenta, se pasa a otra unidad y se da de
 * baja.
 */
export default function EquipamientoCarretaShow({
    carreta,
    conteos,
    movimientos,
    unidades,
    puedeAnular,
    hoy,
}: Props) {
    const { puede } = usePermisos();
    const puedeContar = puede('mantenimiento.equipamiento');
    const [contando, setContando] = useState(false);
    const [accion, setAccion] = useState<Accion | null>(null);
    const placa = formatearPlaca(carreta.placa);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Equipamiento', href: equipamiento.index().url },
            { title: placa, href: equipamiento.show(carreta.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Equipamiento ${placa}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <h1 className="text-xl font-semibold tracking-wide">
                            {placa}
                        </h1>
                        <EstadoEquipamientoBadge
                            estado={carreta.estado}
                            label={carreta.estado_label}
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {carreta.tipo === 'carreta' ? 'Carreta' : 'Tracto'}
                        {carreta.marca && ` · ${carreta.marca}`} ·{' '}
                        {carreta.conteo_fecha
                            ? `contada el ${formatearFecha(carreta.conteo_fecha)}`
                            : 'nunca se contó'}
                    </p>
                </div>
                {puedeContar && (
                    <Button onClick={() => setContando(true)}>
                        <ClipboardList className="size-4" />
                        Contar equipamiento
                    </Button>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                Lo que la unidad tiene sale de su primer conteo y de los vales
                de almacén a su nombre. Si falta algo, se pide al almacén: no se
                agrega acá.
            </p>

            <ul className="flex flex-col divide-y rounded-xl border bg-card">
                {carreta.items.map((item) => (
                    <li
                        key={item.id}
                        className="flex flex-col gap-2 px-4 py-3 text-sm"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <span className="font-medium">
                                    {item.nombre}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {item.minimo !== null
                                        ? `Mínimo ${item.minimo}`
                                        : 'Sin mínimo'}
                                    {(item.danadas ?? 0) > 0 && (
                                        <span className="text-amber-700 dark:text-amber-400">
                                            {' '}
                                            · {item.danadas} dañadas: dalas de
                                            baja
                                        </span>
                                    )}
                                    {item.sin_explicar > 0 && (
                                        <span className="text-red-700 dark:text-red-400">
                                            {' '}
                                            · el último conteo no encontró{' '}
                                            {item.sin_explicar}
                                        </span>
                                    )}
                                </span>
                                {item.lleva_vencimiento && (
                                    <span
                                        className={cn(
                                            'block text-xs',
                                            item.vencido
                                                ? 'font-medium text-red-700 dark:text-red-400'
                                                : item.por_vencer
                                                  ? 'text-amber-700 dark:text-amber-400'
                                                  : 'text-muted-foreground',
                                        )}
                                    >
                                        {item.vence_el
                                            ? `${item.vencido ? 'Venció' : 'Vence'} el ${formatearFecha(item.vence_el)}`
                                            : 'Sin fecha de vencimiento anotada'}
                                    </span>
                                )}
                            </div>
                            <div className="shrink-0 text-right">
                                <span className="text-lg font-semibold tabular-nums">
                                    {item.asignado}
                                </span>
                                {item.faltan > 0 && (
                                    <span className="block text-xs font-medium text-red-700 dark:text-red-400">
                                        faltan {item.faltan}
                                    </span>
                                )}
                            </div>
                        </div>
                        {puedeContar &&
                            (item.asignado > 0 || item.lleva_vencimiento) && (
                                <div className="flex flex-wrap gap-1.5">
                                    {item.asignado > 0 && (
                                        <>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setAccion({
                                                        tipo: 'traslado',
                                                        item,
                                                    })
                                                }
                                            >
                                                <ArrowLeftRight className="size-3.5" />
                                                Pasar a otra unidad
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setAccion({
                                                        tipo: 'baja',
                                                        item,
                                                    })
                                                }
                                            >
                                                <XCircle className="size-3.5" />
                                                Dar de baja
                                            </Button>
                                        </>
                                    )}
                                    {item.lleva_vencimiento && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setAccion({
                                                    tipo: 'vencimiento',
                                                    item,
                                                })
                                            }
                                        >
                                            <CalendarClock className="size-3.5" />
                                            Vencimiento
                                        </Button>
                                    )}
                                </div>
                            )}
                    </li>
                ))}
            </ul>

            <section className="flex flex-col gap-3">
                <h2 className="font-medium">
                    Traslados, bajas y saldo inicial
                </h2>
                {movimientos.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                        Sin movimientos. Lo que salió del almacén para esta
                        unidad está en sus vales de salida.
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y rounded-xl border bg-card">
                        {movimientos.map((movimiento) => (
                            <li
                                key={movimiento.id}
                                className={cn(
                                    'flex items-start gap-2 px-4 py-3 text-sm',
                                    movimiento.anulado && 'opacity-60',
                                )}
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span
                                        className={cn(
                                            'font-medium',
                                            movimiento.anulado &&
                                                'line-through',
                                        )}
                                    >
                                        {movimiento.entra ? '+' : '−'}
                                        {movimiento.cantidad} {movimiento.item}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {formatearFecha(movimiento.fecha)} ·{' '}
                                        {movimiento.tipo === 'traslado'
                                            ? movimiento.entra
                                                ? `llegó de ${movimiento.otra_placa ? formatearPlaca(movimiento.otra_placa) : '—'}`
                                                : `pasó a ${movimiento.otra_placa ? formatearPlaca(movimiento.otra_placa) : '—'}`
                                            : movimiento.tipo_label}
                                        {movimiento.usuario &&
                                            ` · ${movimiento.usuario}`}
                                    </span>
                                    {movimiento.motivo && (
                                        <span className="text-xs">
                                            {movimiento.motivo}
                                        </span>
                                    )}
                                    {movimiento.anulado && (
                                        <span className="text-xs text-destructive">
                                            Anulado:{' '}
                                            {movimiento.motivo_anulacion}
                                        </span>
                                    )}
                                </div>
                                {puedeAnular &&
                                    !movimiento.anulado &&
                                    movimiento.tipo !== 'saldo_inicial' && (
                                        <AnularDialog
                                            url={
                                                anularMovimiento(movimiento.id)
                                                    .url
                                            }
                                            titulo="Anular el movimiento"
                                            descripcion="No se borra: queda a la vista con el motivo, y las unidades vuelven a tener lo de antes."
                                        />
                                    )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="flex flex-col gap-3">
                <h2 className="font-medium">Conteos</h2>
                {conteos.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                        Todavía no se contó esta unidad. Su primer conteo es su
                        saldo inicial.
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y rounded-xl border bg-card">
                        {conteos.map((conteo, indice) => (
                            <li
                                key={conteo.id}
                                className="flex items-start gap-2 px-4 py-3 text-sm"
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="font-medium">
                                        {formatearFecha(conteo.fecha)}
                                        {indice === 0 && (
                                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                último
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {conteo.detalles
                                            .map(
                                                (d) =>
                                                    `${d.item}: ${d.buenas}${d.danadas > 0 ? ` (+${d.danadas} dañadas)` : ''}`,
                                            )
                                            .join(' · ')}
                                    </span>
                                    {(conteo.observacion || conteo.usuario) && (
                                        <span className="text-xs">
                                            {[
                                                conteo.observacion,
                                                conteo.usuario,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </div>
                                {puedeContar && (
                                    <ConfirmarBorradoDialog
                                        url={
                                            equipamiento.destroy(conteo.id).url
                                        }
                                        titulo="¿Eliminar este conteo?"
                                        descripcion="Se borra del historial. Si fue el que fijó el saldo inicial y ya hubo traslados o bajas, no se puede."
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="-my-1 size-8"
                                                aria-label="Eliminar el conteo"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        }
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {contando && (
                <ConteoDialog
                    carretaId={carreta.id}
                    placa={placa}
                    items={carreta.items}
                    hoy={hoy}
                    abierto
                    onAbiertoChange={setContando}
                />
            )}

            {accion && (
                <AccionDialog
                    key={`${accion.tipo}-${accion.item.id}`}
                    accion={accion}
                    unidadId={carreta.id}
                    placa={placa}
                    unidades={unidades}
                    hoy={hoy}
                    onCerrar={() => setAccion(null)}
                />
            )}
        </div>
    );
}

/**
 * Pasar a otra unidad, dar de baja o anotar el vencimiento de un ítem. Los
 * dos primeros salen de lo asignado; el servidor no deja sacar más.
 */
function AccionDialog({
    accion,
    unidadId,
    placa,
    unidades,
    hoy,
    onCerrar,
}: {
    accion: Accion;
    unidadId: number;
    placa: string;
    unidades: Props['unidades'];
    hoy: string;
    onCerrar: () => void;
}) {
    const { tipo, item } = accion;
    const { data, setData, post, put, transform, processing, errors } =
        useForm<{
            item_equipamiento_id: number;
            cantidad: string;
            fecha: string;
            vehiculo_destino_id: number | null;
            motivo: string;
            vence_el: string;
        }>({
            item_equipamiento_id: item.id,
            cantidad: '1',
            fecha: hoy,
            vehiculo_destino_id: null,
            motivo: '',
            vence_el: item.vence_el ?? '',
        });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        evento.stopPropagation();

        const opciones = { preserveScroll: true, onSuccess: onCerrar };

        if (tipo === 'vencimiento') {
            transform((datos) => ({
                item_equipamiento_id: datos.item_equipamiento_id,
                vence_el: datos.vence_el,
            }));
            put(vencimiento(unidadId).url, opciones);

            return;
        }

        transform((datos) => ({
            item_equipamiento_id: datos.item_equipamiento_id,
            cantidad: datos.cantidad,
            fecha: datos.fecha,
            motivo: datos.motivo || null,
            ...(tipo === 'traslado' && {
                vehiculo_destino_id: datos.vehiculo_destino_id,
            }),
        }));
        post(
            tipo === 'traslado'
                ? trasladar(unidadId).url
                : darDeBaja(unidadId).url,
            opciones,
        );
    };

    const titulo = {
        traslado: 'Pasar a otra unidad',
        baja: 'Dar de baja',
        vencimiento: 'Fecha de vencimiento',
    }[tipo];

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{titulo}</DialogTitle>
                        <DialogDescription>
                            {item.nombre} · {placa}
                            {tipo !== 'vencimiento' &&
                                ` tiene ${item.asignado}`}
                        </DialogDescription>
                    </DialogHeader>

                    {tipo === 'vencimiento' ? (
                        <div className="grid gap-1.5">
                            <Label htmlFor="equipo-vence">Vence el</Label>
                            <Input
                                id="equipo-vence"
                                type="date"
                                value={data.vence_el}
                                onChange={(e) =>
                                    setData('vence_el', e.target.value)
                                }
                            />
                            <InputError
                                message={
                                    errors.vence_el ??
                                    errors.item_equipamiento_id
                                }
                            />
                        </div>
                    ) : (
                        <>
                            {tipo === 'traslado' && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="equipo-destino">
                                        A qué unidad pasa
                                    </Label>
                                    <SelectorBuscable
                                        id="equipo-destino"
                                        valor={data.vehiculo_destino_id}
                                        onCambio={(valor) =>
                                            setData(
                                                'vehiculo_destino_id',
                                                valor,
                                            )
                                        }
                                        etiqueta="Placa"
                                        opciones={unidades.map((unidad) => ({
                                            valor: unidad.id,
                                            etiqueta: formatearPlaca(
                                                unidad.placa,
                                            ),
                                            detalle: `${unidad.placa} ${unidad.tipo}`,
                                        }))}
                                        invalido={!!errors.vehiculo_destino_id}
                                    />
                                    <InputError
                                        message={errors.vehiculo_destino_id}
                                    />
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="equipo-cantidad">
                                        Cantidad
                                    </Label>
                                    <Input
                                        id="equipo-cantidad"
                                        inputMode="numeric"
                                        value={data.cantidad}
                                        onChange={(e) =>
                                            setData(
                                                'cantidad',
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                    <InputError message={errors.cantidad} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="equipo-fecha">Fecha</Label>
                                    <Input
                                        id="equipo-fecha"
                                        type="date"
                                        max={hoy}
                                        value={data.fecha}
                                        onChange={(e) =>
                                            setData('fecha', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.fecha} />
                                </div>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="equipo-motivo">
                                    {tipo === 'baja'
                                        ? 'Por qué se da de baja'
                                        : 'Observación (opcional)'}
                                </Label>
                                <Textarea
                                    id="equipo-motivo"
                                    rows={2}
                                    value={data.motivo}
                                    onChange={(e) =>
                                        setData('motivo', e.target.value)
                                    }
                                />
                                <InputError message={errors.motivo} />
                            </div>
                        </>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onCerrar}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
