import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { ArrowUpFromLine, Plus, Trash2 } from 'lucide-react';
import salidas, {
    store,
} from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    errorDeLinea,
    formatearCantidad,
    opcionesArticulo,
} from '@/lib/almacen';
import { formatearPlaca, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ArticuloElegible,
    OrdenElegible,
    VehiculoElegible,
} from '@/types/almacen';
import type { EnumOption } from '@/types/fleet';

type Props = {
    articulos: ArticuloElegible[];
    vehiculos: VehiculoElegible[];
    ordenes: OrdenElegible[];
    destinos: EnumOption[];
    inicial: {
        destino: string;
        orden_trabajo_id: number | null;
        vehiculo_id: number | null;
        entregado_a: string | null;
    };
    hoy: string;
};

type Linea = { articulo_id: number | null; cantidad: string };

/**
 * El vale de salida, pensado para el mostrador: a dónde va, quién recibe y
 * qué se lleva. Solo se ofrece lo que tiene stock, y la cantidad no puede
 * pasar lo que hay.
 */
export default function SalidaCreate({
    articulos,
    vehiculos,
    ordenes,
    destinos,
    inicial,
    hoy,
}: Props) {
    setLayoutProps({
        breadcrumbs: migasAlmacen(
            { title: 'Vales de salida', href: salidas.index().url },
            { title: 'Nuevo vale', href: salidas.create().url },
        ),
    });

    const { data, setData, post, processing, errors } = useForm<{
        fecha: string;
        destino: string;
        orden_trabajo_id: number | null;
        vehiculo_id: number | null;
        entregado_a: string;
        observacion: string;
        detalles: Linea[];
    }>({
        fecha: hoy,
        destino: inicial.destino,
        orden_trabajo_id: inicial.orden_trabajo_id,
        vehiculo_id: inicial.vehiculo_id,
        entregado_a: inicial.entregado_a ?? '',
        observacion: '',
        detalles: [{ articulo_id: null, cantidad: '' }],
    });
    const errores = errors as Record<string, string | undefined>;

    const cambiarLinea = (indice: number, cambios: Partial<Linea>) =>
        setData(
            'detalles',
            data.detalles.map((linea, i) =>
                i === indice ? { ...linea, ...cambios } : linea,
            ),
        );

    const elegidos = new Set(data.detalles.map((l) => l.articulo_id));
    const estimado = data.detalles.reduce((total, linea) => {
        const articulo = articulos.find((a) => a.id === linea.articulo_id);

        return (
            total +
            (Number(linea.cantidad) || 0) * (articulo?.costo_promedio ?? 0)
        );
    }, 0);
    const conCostos = articulos.some((a) => a.costo_promedio !== null);

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(store().url);
    };

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Nuevo vale de salida" />

            <EncabezadoPantalla
                titulo="Nuevo vale de salida"
                descripcion="Lo que se entrega al taller. Sale al costo promedio del día."
                icono={ArrowUpFromLine}
            />

            <form onSubmit={guardar} className="flex flex-col gap-6">
                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <div className="grid gap-1.5 sm:col-span-2">
                        <span className="text-sm font-medium">A dónde va</span>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={data.destino}
                            onValueChange={(valor) =>
                                valor && setData('destino', valor)
                            }
                            className="w-full flex-wrap justify-start"
                        >
                            {destinos.map((destino) => (
                                <ToggleGroupItem
                                    key={destino.value}
                                    value={destino.value}
                                    className="px-4"
                                >
                                    {destino.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </div>

                    {data.destino === 'orden_trabajo' && (
                        <div className="sm:col-span-2">
                            <Field
                                label="Orden de trabajo"
                                error={errors.orden_trabajo_id}
                                required
                                ayuda="Las abiertas y las cerradas en el último mes."
                            >
                                {(id) => (
                                    <SelectorBuscable
                                        id={id}
                                        valor={data.orden_trabajo_id}
                                        onCambio={(valor) =>
                                            setData('orden_trabajo_id', valor)
                                        }
                                        etiqueta="Orden de trabajo"
                                        opciones={ordenes.map((orden) => ({
                                            valor: orden.id,
                                            etiqueta: orden.etiqueta,
                                            detalle: orden.placa,
                                        }))}
                                        invalido={!!errors.orden_trabajo_id}
                                    />
                                )}
                            </Field>
                        </div>
                    )}

                    {data.destino === 'unidad' && (
                        <Field
                            label="Unidad"
                            error={errors.vehiculo_id}
                            required
                        >
                            {(id) => (
                                <SelectorBuscable
                                    id={id}
                                    valor={data.vehiculo_id}
                                    onCambio={(valor) =>
                                        setData('vehiculo_id', valor)
                                    }
                                    etiqueta="Placa"
                                    opciones={vehiculos.map((v) => ({
                                        valor: v.id,
                                        etiqueta: `${formatearPlaca(v.placa)} · ${v.tipo}`,
                                        detalle: `${v.placa} ${v.marca ?? ''}`,
                                    }))}
                                    invalido={!!errors.vehiculo_id}
                                />
                            )}
                        </Field>
                    )}

                    <Field label="Fecha" error={errors.fecha} required>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                max={hoy}
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field label="Recibe" error={errors.entregado_a}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.entregado_a}
                                onChange={(e) =>
                                    setData('entregado_a', e.target.value)
                                }
                                placeholder="El técnico que se lo lleva"
                            />
                        )}
                    </Field>
                </section>

                <section className="flex flex-col gap-3 rounded-xl border bg-card p-5">
                    <h2 className="text-sm font-semibold">Qué sale</h2>
                    <InputError message={errors.detalles} />
                    <InputError message={errores.stock} />

                    <ul className="flex flex-col gap-3">
                        {data.detalles.map((linea, indice) => {
                            const articulo = articulos.find(
                                (a) => a.id === linea.articulo_id,
                            );
                            const pasa =
                                articulo !== undefined &&
                                Number(linea.cantidad) > articulo.stock;

                            return (
                                <li
                                    key={indice}
                                    className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_9rem_auto] sm:items-start"
                                >
                                    <div className="min-w-0">
                                        <SelectorBuscable
                                            valor={linea.articulo_id}
                                            onCambio={(valor) =>
                                                cambiarLinea(indice, {
                                                    articulo_id: valor,
                                                })
                                            }
                                            etiqueta="Artículo"
                                            opciones={opcionesArticulo(
                                                articulos.filter(
                                                    (a) =>
                                                        a.id ===
                                                            linea.articulo_id ||
                                                        !elegidos.has(a.id),
                                                ),
                                                true,
                                            )}
                                            invalido={
                                                !!errorDeLinea(
                                                    errores,
                                                    indice,
                                                    'articulo_id',
                                                )
                                            }
                                        />
                                        {articulo && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {articulo.codigo} · hay{' '}
                                                {formatearCantidad(
                                                    articulo.stock,
                                                    articulo.unidad,
                                                )}
                                            </p>
                                        )}
                                        <InputError
                                            message={errorDeLinea(
                                                errores,
                                                indice,
                                                'articulo_id',
                                            )}
                                        />
                                    </div>
                                    <div>
                                        <Input
                                            inputMode="decimal"
                                            value={linea.cantidad}
                                            onChange={(e) =>
                                                cambiarLinea(indice, {
                                                    cantidad: e.target.value,
                                                })
                                            }
                                            placeholder={`Cant. ${articulo?.unidad ?? ''}`}
                                            aria-label="Cantidad"
                                            aria-invalid={pasa}
                                            className={cn(
                                                pasa && 'border-destructive',
                                            )}
                                        />
                                        {pasa && (
                                            <p className="mt-1 text-xs text-destructive">
                                                Solo hay{' '}
                                                {formatearCantidad(
                                                    articulo.stock,
                                                )}
                                            </p>
                                        )}
                                        <InputError
                                            message={errorDeLinea(
                                                errores,
                                                indice,
                                                'cantidad',
                                            )}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar línea"
                                        disabled={data.detalles.length === 1}
                                        onClick={() =>
                                            setData(
                                                'detalles',
                                                data.detalles.filter(
                                                    (_, i) => i !== indice,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>

                    <Button
                        type="button"
                        variant="outline"
                        className="self-start"
                        onClick={() =>
                            setData('detalles', [
                                ...data.detalles,
                                { articulo_id: null, cantidad: '' },
                            ])
                        }
                    >
                        <Plus className="size-4" />
                        Otro artículo
                    </Button>

                    {conCostos && estimado > 0 && (
                        <p className="text-right text-sm text-muted-foreground">
                            Costo estimado:{' '}
                            <strong className="text-foreground">
                                {formatearSoles(estimado)}
                            </strong>
                        </p>
                    )}
                </section>

                <section className="rounded-xl border bg-card p-5">
                    <Field label="Observación" error={errors.observacion}>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                value={data.observacion}
                                onChange={(e) =>
                                    setData('observacion', e.target.value)
                                }
                                placeholder="Para qué se lleva, si no es obvio"
                            />
                        )}
                    </Field>
                </section>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Emitir vale
                    </Button>
                </div>
            </form>
        </div>
    );
}
