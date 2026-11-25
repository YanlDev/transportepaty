import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowLeftRight,
    CalendarClock,
    ClipboardList,
    PackagePlus,
    Tag,
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
import piezas from '@/actions/App/Http/Controllers/PiezaEquipamientoController';
import reposicionesRutas from '@/actions/App/Http/Controllers/ReposicionEquipamientoController';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { ConfirmarBorradoDialog } from '@/components/confirmar-borrado-dialog';
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
    FotoEquipamiento,
    ItemCarreta,
    MovimientoEquipamiento,
    PiezaEquipamiento,
} from '@/types/equipamiento';

type Props = {
    carreta: EquipamientoCarreta;
    /** Las fotos más recientes de cada ítem, por su id. */
    fotos: Record<number, FotoEquipamiento[]>;
    /** Lo pedido al almacén que todavía no llegó, por id de ítem. */
    reposiciones: Record<
        number,
        {
            id: number;
            pendiente: number;
            hay: number;
            en_compra: string | null;
        }
    >;
    conteos: ConteoEquipamiento[];
    movimientos: MovimientoEquipamiento[];
    /** Las demás unidades, para los traslados. */
    unidades: { id: number; placa: string; tipo: string }[];
    puedeAnular: boolean;
    hoy: string;
};

type Accion = {
    tipo: 'traslado' | 'baja' | 'vencimiento' | 'reposicion';
    item: ItemCarreta;
};

/**
 * La ficha de equipamiento de una unidad: lo que tiene frente a lo que debe
 * llevar, con sus fotos y vencimientos, y de dónde salió cada cosa. Lo nuevo
 * llega del almacén con un vale a nombre de la unidad; acá se hace el
 * levantamiento, se pasa a otra unidad y se da de baja.
 */
export default function EquipamientoCarretaShow({
    carreta,
    fotos,
    reposiciones,
    conteos,
    movimientos,
    unidades,
    puedeAnular,
    hoy,
}: Props) {
    const { puede } = usePermisos();
    const puedeContar = puede('mantenimiento.equipamiento');
    const [accion, setAccion] = useState<Accion | null>(null);
    const [pieza, setPieza] = useState<PiezaEquipamiento | null>(null);
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
                            ? `levantamiento del ${formatearFecha(carreta.conteo_fecha)}`
                            : 'sin levantamiento'}
                    </p>
                </div>
                {puedeContar && (
                    <Button asChild>
                        <Link href={equipamiento.levantamiento(carreta.id)}>
                            <ClipboardList className="size-4" />
                            {carreta.conteo_fecha
                                ? 'Revisar de nuevo'
                                : 'Hacer levantamiento'}
                        </Link>
                    </Button>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                Lo que la unidad tiene sale de su levantamiento y de los vales
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
                                        ? `Debe llevar ${item.minimo}`
                                        : 'Sin cantidad fija'}
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
                                {item.lleva_vencimiento && !item.por_pieza && (
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
                        <Fotos fotos={fotos[item.id] ?? []} de={item.nombre} />
                        {item.por_pieza &&
                            (item.piezas.length > 0 || item.sin_codigo > 0) && (
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {item.piezas.map((una) => (
                                        <button
                                            key={una.id}
                                            type="button"
                                            disabled={!puedeContar}
                                            onClick={() => setPieza(una)}
                                            className={cn(
                                                'flex flex-col rounded-lg border px-2.5 py-1.5 text-left text-xs transition-colors enabled:hover:bg-accent/50',
                                                una.vencido &&
                                                    'border-red-300 dark:border-red-800',
                                                una.por_vencer &&
                                                    'border-amber-300 dark:border-amber-800',
                                            )}
                                            aria-label={`Datos de ${una.codigo}`}
                                        >
                                            <span className="font-mono text-sm font-semibold text-primary">
                                                {una.codigo}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {[
                                                    una.descripcion,
                                                    una.serie &&
                                                        `serie ${una.serie}`,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') ||
                                                    (puedeContar
                                                        ? 'Anota qué es'
                                                        : 'Sin datos')}
                                            </span>
                                            {item.lleva_vencimiento && (
                                                <span
                                                    className={cn(
                                                        una.vencido
                                                            ? 'font-medium text-red-700 dark:text-red-400'
                                                            : una.por_vencer
                                                              ? 'text-amber-700 dark:text-amber-400'
                                                              : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {una.vence_el
                                                        ? `${una.vencido ? 'Venció' : 'Vence'} el ${formatearFecha(una.vence_el)}`
                                                        : 'Sin vencimiento anotado'}
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                    {puedeContar && item.sin_codigo > 0 && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    piezas.store(carreta.id)
                                                        .url,
                                                    {
                                                        item_equipamiento_id:
                                                            item.id,
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Tag className="size-3.5" />
                                            Poner código
                                            {item.sin_codigo > 1 &&
                                                ` (${item.sin_codigo})`}
                                        </Button>
                                    )}
                                </div>
                            )}
                        {reposiciones[item.id] ? (
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-sky-500/40 bg-sky-500/5 px-3 py-2 text-xs">
                                <span>
                                    <span className="font-medium">
                                        Reposición pedida al almacén:{' '}
                                        {reposiciones[item.id].pendiente}
                                    </span>
                                    <span className="block text-muted-foreground">
                                        {reposiciones[item.id].hay >=
                                        reposiciones[item.id].pendiente
                                            ? 'Hay en el almacén: falta que la entreguen'
                                            : reposiciones[item.id].en_compra
                                              ? `En compra · ${reposiciones[item.id].en_compra}`
                                              : 'No hay en el almacén: hay que comprarla'}
                                    </span>
                                </span>
                                {puedeContar && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                reposicionesRutas.destroy(
                                                    reposiciones[item.id].id,
                                                ).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Retirar pedido
                                    </Button>
                                )}
                            </div>
                        ) : (
                            puedeContar &&
                            (item.faltan > 0 || item.minimo === null) && (
                                <Button
                                    variant={
                                        item.faltan > 0 ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    className="self-start"
                                    onClick={() =>
                                        setAccion({ tipo: 'reposicion', item })
                                    }
                                >
                                    <PackagePlus className="size-3.5" />
                                    Pedir reposición
                                </Button>
                            )
                        )}
                        {puedeContar &&
                            (item.asignado > 0 ||
                                (item.lleva_vencimiento &&
                                    !item.por_pieza)) && (
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
                                    {item.lleva_vencimiento &&
                                        !item.por_pieza && (
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
                        unidad está en sus entregas.
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
                                        {movimiento.codigo &&
                                            ` · ${movimiento.codigo}`}
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
                <h2 className="font-medium">Levantamientos</h2>
                {conteos.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                        Todavía no se hizo el levantamiento de esta unidad. Lo
                        que se encuentre queda como su saldo inicial.
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
                                    {conteo.detalles
                                        .filter((d) => d.nota)
                                        .map((d) => (
                                            <span
                                                key={d.item}
                                                className="text-xs"
                                            >
                                                {d.item}: {d.nota}
                                            </span>
                                        ))}
                                    <Fotos
                                        fotos={conteo.detalles.flatMap(
                                            (d) => d.fotos,
                                        )}
                                        de="el levantamiento"
                                    />
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
                                        titulo="¿Eliminar este levantamiento?"
                                        descripcion="Se borra del historial. Si fue el que fijó el saldo inicial y ya hubo traslados o bajas, no se puede."
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="-my-1 size-8"
                                                aria-label="Eliminar el levantamiento"
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

            {pieza && (
                <PiezaDialog
                    key={pieza.id}
                    pieza={pieza}
                    onCerrar={() => setPieza(null)}
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

/** Los datos de una pieza con código: qué es, su serie y cuándo vence. */
function PiezaDialog({
    pieza,
    onCerrar,
}: {
    pieza: PiezaEquipamiento;
    onCerrar: () => void;
}) {
    const { data, setData, put, processing, errors } = useForm({
        descripcion: pieza.descripcion ?? '',
        serie: pieza.serie ?? '',
        vence_el: pieza.vence_el ?? '',
    });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        evento.stopPropagation();

        put(piezas.update(pieza.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle className="font-mono">
                            {pieza.codigo}
                        </DialogTitle>
                        <DialogDescription>
                            Este código va marcado en la pieza. Anota qué es
                            para reconocerla.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-1.5">
                        <Label htmlFor="pieza-descripcion">Qué es</Label>
                        <Input
                            id="pieza-descripcion"
                            placeholder="PQS de 6 kg, marca…"
                            maxLength={120}
                            value={data.descripcion}
                            onChange={(e) =>
                                setData('descripcion', e.target.value)
                            }
                        />
                        <InputError message={errors.descripcion} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="pieza-serie">
                                Serie (opcional)
                            </Label>
                            <Input
                                id="pieza-serie"
                                maxLength={60}
                                value={data.serie}
                                onChange={(e) =>
                                    setData('serie', e.target.value)
                                }
                            />
                            <InputError message={errors.serie} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="pieza-vence">Vence el</Label>
                            <Input
                                id="pieza-vence"
                                type="date"
                                value={data.vence_el}
                                onChange={(e) =>
                                    setData('vence_el', e.target.value)
                                }
                            />
                            <InputError message={errors.vence_el} />
                        </div>
                    </div>

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

/** Las fotos en miniatura; tocar una la abre entera. */
function Fotos({ fotos, de }: { fotos: FotoEquipamiento[]; de: string }) {
    if (fotos.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {fotos.map((foto) => (
                <a
                    key={foto.id}
                    href={foto.url}
                    target="_blank"
                    rel="noreferrer"
                    className="size-16 overflow-hidden rounded-md border"
                >
                    <img
                        src={foto.url}
                        alt={`Foto de ${de}`}
                        loading="lazy"
                        className="size-full object-cover"
                    />
                </a>
            ))}
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
            pieza_equipamiento_id: number | null;
            fecha: string;
            vehiculo_destino_id: number | null;
            motivo: string;
            nota: string;
            vence_el: string;
        }>({
            item_equipamiento_id: item.id,
            cantidad:
                tipo === 'reposicion' ? String(Math.max(1, item.faltan)) : '1',
            pieza_equipamiento_id: item.piezas[0]?.id ?? null,
            fecha: hoy,
            vehiculo_destino_id: null,
            motivo: '',
            nota: '',
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

        if (tipo === 'reposicion') {
            transform((datos) => ({
                item_equipamiento_id: datos.item_equipamiento_id,
                cantidad: datos.cantidad,
                nota: datos.nota || null,
            }));
            post(reposicionesRutas.store(unidadId).url, opciones);

            return;
        }

        transform((datos) => ({
            item_equipamiento_id: datos.item_equipamiento_id,
            cantidad: datos.cantidad,
            ...(item.por_pieza && {
                pieza_equipamiento_id: datos.pieza_equipamiento_id,
            }),
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
        reposicion: 'Pedir reposición al almacén',
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
                            {tipo === 'reposicion' &&
                                item.minimo !== null &&
                                ` y debe llevar ${item.minimo}`}
                        </DialogDescription>
                    </DialogHeader>

                    {tipo === 'reposicion' ? (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="reposicion-cantidad">
                                    Cuántas pides
                                </Label>
                                <Input
                                    id="reposicion-cantidad"
                                    inputMode="numeric"
                                    value={data.cantidad}
                                    onChange={(e) =>
                                        setData(
                                            'cantidad',
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                    className="w-28"
                                />
                                <InputError
                                    message={
                                        errors.cantidad ??
                                        errors.item_equipamiento_id
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="reposicion-nota">
                                    Nota para el almacén (opcional)
                                </Label>
                                <Textarea
                                    id="reposicion-nota"
                                    rows={2}
                                    maxLength={300}
                                    placeholder="Capacidad, tipo, por qué se pide…"
                                    value={data.nota}
                                    onChange={(e) =>
                                        setData('nota', e.target.value)
                                    }
                                />
                                <InputError message={errors.nota} />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                El almacén lo ve en su «Por hacer»: si hay, lo
                                entrega con vale a nombre de {placa}; si no,
                                pide su compra.
                            </p>
                        </>
                    ) : tipo === 'vencimiento' ? (
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
                                {item.por_pieza ? (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="equipo-pieza">
                                            Cuál
                                        </Label>
                                        <select
                                            id="equipo-pieza"
                                            value={
                                                data.pieza_equipamiento_id ?? ''
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'pieza_equipamiento_id',
                                                    Number(e.target.value) ||
                                                        null,
                                                )
                                            }
                                            className="h-9 rounded-md border bg-background px-2 text-sm"
                                        >
                                            {item.piezas.length === 0 && (
                                                <option value="">
                                                    Sin código todavía
                                                </option>
                                            )}
                                            {item.piezas.map((una) => (
                                                <option
                                                    key={una.id}
                                                    value={una.id}
                                                >
                                                    {una.codigo}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string | undefined
                                                    >
                                                ).pieza_equipamiento_id ??
                                                errors.cantidad
                                            }
                                        />
                                    </div>
                                ) : (
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
                                )}
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
