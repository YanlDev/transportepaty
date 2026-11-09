import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { CheckCheck, ClipboardList, Trash2 } from 'lucide-react';
import { useState } from 'react';
import articulosRutas from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import inventarios, {
    anular,
    cerrar,
    guardarLinea,
    quitarLinea,
} from '@/actions/App/Http/Controllers/Almacen/InventarioAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { ArticuloRapidoDialog } from '@/components/almacen/articulo-rapido-dialog';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { usePermisos } from '@/hooks/use-permisos';
import {
    formatearCantidad,
    formatearUnitario,
    opcionesArticulo,
} from '@/lib/almacen';
import { formatearFecha, formatearFechaHora } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ArticuloElegible,
    InventarioDetalle,
    Opcion,
} from '@/types/almacen';
import type { EnumOption } from '@/types/fleet';

type Props = {
    inventario: InventarioDetalle;
    articulos: ArticuloElegible[];
    puedeEditar: boolean;
    puedeAnular: boolean;
    /** El mes del conteo sigue abierto: se puede cerrar o anular. */
    periodoAbierto: boolean;
    puedeCrearArticulo: boolean;
    categorias: Opcion[];
    ubicaciones: Opcion[];
    unidades: EnumOption[];
};

const TONO = {
    abierto: 'abierto',
    cerrado: 'vigente',
    anulado: 'anulado',
} as const;

/**
 * Un conteo: arriba se anota artículo por artículo (pensado para ir con el
 * celular por los estantes), abajo lo contado contra lo que dice el sistema.
 * Cerrar pasa las diferencias al kardex.
 */
export default function InventarioShow({
    inventario,
    articulos,
    puedeEditar,
    puedeAnular,
    periodoAbierto,
    puedeCrearArticulo,
    categorias,
    ubicaciones,
    unidades,
}: Props) {
    const { puede } = usePermisos();
    const veCostos = puede('almacen.ver_costos');
    const abierto = inventario.estado === 'abierto';
    // El estante que se está recorriendo: la lista se acota a lo que va ahí.
    const [estante, setEstante] = useState<string>('');
    const delEstante =
        estante === ''
            ? articulos
            : estante === 'sin'
              ? articulos.filter((a) => a.ubicacion_id === null)
              : articulos.filter((a) => a.ubicacion_id === Number(estante));

    setLayoutProps({
        breadcrumbs: migasAlmacen(
            { title: 'Conteos', href: inventarios.index().url },
            {
                title: inventario.numero,
                href: inventarios.show(inventario.id).url,
            },
        ),
    });

    const { data, setData, post, processing, errors, reset } = useForm({
        articulo_id: null as number | null,
        cantidad_contada: '',
        costo_unitario: '',
        costo_estimado: false,
        observacion: '',
    });

    const elegido = articulos.find((a) => a.id === data.articulo_id);
    const sinHistorial = elegido !== undefined && !elegido.con_historial;

    const elegir = (id: number) => {
        const anotado = inventario.detalles.find((d) => d.articulo_id === id);

        setData({
            articulo_id: id,
            cantidad_contada: anotado ? String(anotado.cantidad_contada) : '',
            costo_unitario:
                anotado?.costo_unitario != null
                    ? String(anotado.costo_unitario)
                    : '',
            costo_estimado: anotado?.costo_estimado ?? false,
            observacion: anotado?.observacion ?? '',
        });
    };

    const anotar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(guardarLinea(inventario.id).url, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const conDiferencia = inventario.detalles.filter(
        (d) => Math.abs(d.diferencia) > 0.0005,
    ).length;

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={inventario.numero} />

            <EncabezadoPantalla
                titulo={`Conteo ${inventario.numero}`}
                descripcion={`Al ${formatearFecha(inventario.fecha)}${inventario.observacion ? ` · ${inventario.observacion}` : ''}`}
                icono={ClipboardList}
                acciones={
                    <>
                        <EstadoDocumento tono={TONO[inventario.estado]}>
                            {inventario.estado_label}
                        </EstadoDocumento>
                        {puedeEditar && periodoAbierto && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        cerrar(inventario.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                disabled={inventario.detalles.length === 0}
                            >
                                <CheckCheck className="size-4" />
                                Cerrar conteo
                            </Button>
                        )}
                        {puedeAnular && (abierto || periodoAbierto) && (
                            <AnularDialog
                                url={anular(inventario.id).url}
                                etiqueta={abierto ? 'Descartar' : 'Anular'}
                                titulo={
                                    abierto
                                        ? `Descartar ${inventario.numero}`
                                        : `Anular ${inventario.numero}`
                                }
                                descripcion={
                                    abierto
                                        ? 'El conteo no movió nada todavía: se descarta.'
                                        : 'Sus ajustes salen del kardex y el stock vuelve a como estaba.'
                                }
                            />
                        )}
                    </>
                }
            />

            <InputError
                message={(errors as Record<string, string>).inventario}
            />

            {!periodoAbierto && inventario.estado !== 'anulado' && (
                <p className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    El mes de este conteo ya cerró:{' '}
                    {abierto
                        ? 'no se puede cerrar. Descártalo y abre uno con fecha de este mes.'
                        : 'ya no se puede anular.'}
                </p>
            )}

            {inventario.estado === 'anulado' && (
                <p className="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                    Anulado
                    {inventario.anulado_por && ` por ${inventario.anulado_por}`}
                    {inventario.motivo_anulacion &&
                        `: ${inventario.motivo_anulacion}`}
                </p>
            )}

            {inventario.cerrado_at && (
                <p className="text-sm text-muted-foreground">
                    Cerrado
                    {inventario.cerrado_por &&
                        ` por ${inventario.cerrado_por}`}{' '}
                    el {formatearFechaHora(inventario.cerrado_at)} ·{' '}
                    {conDiferencia}{' '}
                    {conDiferencia === 1
                        ? 'artículo con diferencia'
                        : 'artículos con diferencia'}
                </p>
            )}

            {puedeEditar && (
                <form
                    onSubmit={anotar}
                    className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                >
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <h2 className="text-sm font-semibold">Anotar</h2>
                        <div className="flex flex-wrap gap-2">
                            {ubicaciones.length > 0 && (
                                <select
                                    aria-label="Estante"
                                    value={estante}
                                    onChange={(e) => setEstante(e.target.value)}
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option value="">Todos los estantes</option>
                                    {ubicaciones.map((ubicacion) => (
                                        <option
                                            key={ubicacion.id}
                                            value={ubicacion.id}
                                        >
                                            {ubicacion.nombre}
                                        </option>
                                    ))}
                                    <option value="sin">Sin ubicación</option>
                                </select>
                            )}
                            {puedeCrearArticulo && (
                                <ArticuloRapidoDialog
                                    key={estante}
                                    categorias={categorias}
                                    ubicaciones={ubicaciones}
                                    unidades={unidades}
                                    ubicacionInicial={
                                        estante !== '' && estante !== 'sin'
                                            ? Number(estante)
                                            : null
                                    }
                                />
                            )}
                        </div>
                    </div>
                    {estante !== '' && (
                        <p className="text-xs text-muted-foreground">
                            {delEstante.length}{' '}
                            {delEstante.length === 1 ? 'artículo' : 'artículos'}{' '}
                            en este estante ·{' '}
                            {
                                delEstante.filter((a) =>
                                    inventario.detalles.some(
                                        (d) => d.articulo_id === a.id,
                                    ),
                                ).length
                            }{' '}
                            ya contados
                        </p>
                    )}
                    <div className="grid gap-3 sm:grid-cols-[1fr_8rem] sm:items-start">
                        <div className="min-w-0">
                            <SelectorBuscable
                                valor={data.articulo_id}
                                onCambio={elegir}
                                etiqueta="Artículo"
                                opciones={opcionesArticulo(delEstante)}
                                invalido={!!errors.articulo_id}
                            />
                            {elegido && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {elegido.codigo} · el sistema dice{' '}
                                    {formatearCantidad(
                                        elegido.stock,
                                        elegido.unidad,
                                    )}
                                </p>
                            )}
                            <InputError message={errors.articulo_id} />
                        </div>
                        <div>
                            <Input
                                inputMode="decimal"
                                value={data.cantidad_contada}
                                onChange={(e) =>
                                    setData('cantidad_contada', e.target.value)
                                }
                                placeholder={`Hay ${elegido?.unidad ?? ''}`}
                                aria-label="Cantidad contada"
                            />
                            <InputError message={errors.cantidad_contada} />
                        </div>
                    </div>
                    {(veCostos || sinHistorial) && (
                        <div className="grid gap-3 sm:grid-cols-[12rem_1fr] sm:items-center">
                            <div>
                                <Input
                                    inputMode="decimal"
                                    value={data.costo_unitario}
                                    onChange={(e) =>
                                        setData(
                                            'costo_unitario',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Costo unit. S/ sin IGV"
                                    aria-label="Costo unitario"
                                />
                                <InputError message={errors.costo_unitario} />
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="costo-estimado"
                                    checked={data.costo_estimado}
                                    onCheckedChange={(valor) =>
                                        setData(
                                            'costo_estimado',
                                            valor === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="costo-estimado"
                                    className="font-normal"
                                >
                                    Costo estimado (sin factura a mano)
                                </Label>
                            </div>
                            <p className="text-xs text-muted-foreground sm:col-span-2">
                                {sinHistorial
                                    ? 'Es el saldo inicial: anota el costo de su última factura o uno estimado.'
                                    : 'Solo cuenta si sobra; vacío, entra al costo promedio. Lo que falta sale siempre al promedio.'}
                            </p>
                        </div>
                    )}
                    <Input
                        value={data.observacion}
                        onChange={(e) => setData('observacion', e.target.value)}
                        placeholder="Observación (opcional)"
                    />
                    <Button
                        type="submit"
                        disabled={processing || data.articulo_id === null}
                        className="self-end"
                    >
                        {processing && <Spinner />}
                        Anotar
                    </Button>
                </form>
            )}

            <section className="flex flex-col gap-2">
                <h2 className="text-sm font-medium text-muted-foreground">
                    {inventario.detalles.length}{' '}
                    {inventario.detalles.length === 1
                        ? 'artículo contado'
                        : 'artículos contados'}
                </h2>
                <ul className="flex flex-col gap-2">
                    {inventario.detalles.map((detalle) => {
                        const diferencia = detalle.diferencia;

                        return (
                            <li
                                key={detalle.id}
                                className="flex items-center gap-3 rounded-xl border bg-card p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={articulosRutas.show(
                                            detalle.articulo_id,
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {detalle.nombre}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {detalle.codigo}
                                        {detalle.ubicacion &&
                                            ` · ${detalle.ubicacion}`}
                                        {veCostos &&
                                            detalle.costo_unitario !== null &&
                                            ` · ${formatearUnitario(detalle.costo_unitario)}${detalle.costo_estimado ? ' (estimado)' : ''}`}
                                        {detalle.observacion &&
                                            ` · ${detalle.observacion}`}
                                    </p>
                                </div>
                                <div className="text-right text-sm">
                                    <p className="font-semibold tabular-nums">
                                        {formatearCantidad(
                                            detalle.cantidad_contada,
                                            detalle.unidad,
                                        )}
                                    </p>
                                    <p
                                        className={cn(
                                            'text-xs tabular-nums',
                                            diferencia > 0
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : diferencia < 0
                                                  ? 'text-red-700 dark:text-red-400'
                                                  : 'text-muted-foreground',
                                        )}
                                    >
                                        sistema{' '}
                                        {formatearCantidad(
                                            detalle.stock_sistema,
                                        )}
                                        {Math.abs(diferencia) > 0.0005 &&
                                            ` · ${diferencia > 0 ? '+' : ''}${formatearCantidad(diferencia)}`}
                                    </p>
                                </div>
                                {puedeEditar && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar"
                                        onClick={() =>
                                            router.delete(
                                                quitarLinea({
                                                    inventario: inventario.id,
                                                    detalle: detalle.id,
                                                }).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </section>
        </div>
    );
}
