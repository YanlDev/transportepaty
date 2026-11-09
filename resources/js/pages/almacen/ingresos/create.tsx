import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { ArrowDownToLine, Plus, Trash2, UserPlus } from 'lucide-react';
import ingresos, {
    store,
} from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { ArticuloRapidoDialog } from '@/components/almacen/articulo-rapido-dialog';
import { ProveedorDialog } from '@/components/almacen/proveedor-dialog';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    errorDeLinea,
    formatearCantidad,
    formatearMonto,
    formatearUnitario,
    opcionesArticulo,
} from '@/lib/almacen';
import type {
    ArticuloElegible,
    Opcion,
    ProveedorElegible,
} from '@/types/almacen';
import type { EnumOption } from '@/types/fleet';

type Props = {
    proveedores: ProveedorElegible[];
    articulos: ArticuloElegible[];
    comprobantes: EnumOption[];
    monedas: EnumOption[];
    hoy: string;
    tasaIgv: number;
    puedeCrearArticulo: boolean;
    puedeCrearProveedor: boolean;
    categorias: Opcion[];
    ubicaciones: Opcion[];
    unidades: EnumOption[];
};

type Linea = {
    articulo_id: number | null;
    cantidad: string;
    precio_unitario: string;
};

const lineaVacia = (): Linea => ({
    articulo_id: null,
    cantidad: '',
    precio_unitario: '',
});

/**
 * Registrar una compra tal como dice el comprobante: el proveedor, la serie
 * y el número, la moneda y, línea por línea, cantidad y precio. Abajo se ve
 * lo que entra al kardex: en soles y, con factura, sin IGV.
 */
export default function IngresoCreate({
    proveedores,
    articulos,
    comprobantes,
    monedas,
    hoy,
    tasaIgv,
    puedeCrearArticulo,
    puedeCrearProveedor,
    categorias,
    ubicaciones,
    unidades,
}: Props) {
    setLayoutProps({
        breadcrumbs: migasAlmacen(
            { title: 'Ingresos', href: ingresos.index().url },
            { title: 'Registrar compra', href: ingresos.create().url },
        ),
    });

    const { data, setData, post, processing, errors, progress } = useForm<{
        fecha: string;
        proveedor_id: number | null;
        tipo_comprobante: string;
        serie_numero: string;
        moneda: string;
        tipo_cambio: string;
        precios_con_igv: boolean;
        observacion: string;
        comprobante: File | null;
        detalles: Linea[];
    }>({
        fecha: hoy,
        proveedor_id: null,
        tipo_comprobante: 'factura',
        serie_numero: '',
        moneda: 'PEN',
        tipo_cambio: '',
        precios_con_igv: false,
        observacion: '',
        comprobante: null,
        detalles: [lineaVacia()],
    });
    const errores = errors as Record<string, string | undefined>;

    const cambiarLinea = (indice: number, cambios: Partial<Linea>) =>
        setData(
            'detalles',
            data.detalles.map((linea, i) =>
                i === indice ? { ...linea, ...cambios } : linea,
            ),
        );

    const llevaIgv = ['factura', 'boleta'].includes(data.tipo_comprobante);
    const creditoFiscal = data.tipo_comprobante === 'factura';
    const cambio = data.moneda === 'USD' ? Number(data.tipo_cambio) || 0 : 1;
    const suma = data.detalles.reduce(
        (total, linea) =>
            total +
            (Number(linea.cantidad) || 0) *
                (Number(linea.precio_unitario) || 0),
        0,
    );
    const subtotal = !llevaIgv
        ? suma
        : data.precios_con_igv
          ? suma / (1 + tasaIgv)
          : suma;
    const total = !llevaIgv
        ? suma
        : data.precios_con_igv
          ? suma
          : suma * (1 + tasaIgv);
    const costoKardex = (creditoFiscal ? subtotal : total) * cambio;

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(store().url);
    };

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Registrar compra" />

            <EncabezadoPantalla
                titulo="Registrar compra"
                descripcion="Lo que llega con su comprobante. Al guardar, el stock se actualiza."
                icono={ArrowDownToLine}
            />

            <form onSubmit={guardar} className="flex flex-col gap-6">
                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="sm:col-span-2">
                        <Label htmlFor="ingreso-proveedor">
                            Proveedor{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <div className="mt-1.5 flex gap-2">
                            <div className="min-w-0 flex-1">
                                <SelectorBuscable
                                    id="ingreso-proveedor"
                                    valor={data.proveedor_id}
                                    onCambio={(valor) =>
                                        setData('proveedor_id', valor)
                                    }
                                    etiqueta="Proveedor"
                                    opciones={proveedores.map((p) => ({
                                        valor: p.id,
                                        etiqueta: p.nombre,
                                        detalle: p.ruc ?? '',
                                    }))}
                                    invalido={!!errors.proveedor_id}
                                />
                            </div>
                            {puedeCrearProveedor && (
                                <ProveedorDialog
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            aria-label="Proveedor nuevo"
                                        >
                                            <UserPlus className="size-4" />
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                        <InputError message={errors.proveedor_id} />
                    </div>
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
                    <Field
                        label="Comprobante"
                        error={errors.tipo_comprobante}
                        required
                    >
                        {(id) => (
                            <Select
                                value={data.tipo_comprobante}
                                onValueChange={(valor) =>
                                    setData('tipo_comprobante', valor)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {comprobantes.map((opcion) => (
                                        <SelectItem
                                            key={opcion.value}
                                            value={opcion.value}
                                        >
                                            {opcion.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    {data.tipo_comprobante !== 'sin_comprobante' && (
                        <Field
                            label="Serie y número"
                            error={errors.serie_numero}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.serie_numero}
                                    onChange={(e) =>
                                        setData('serie_numero', e.target.value)
                                    }
                                    placeholder="F001-00012345"
                                />
                            )}
                        </Field>
                    )}
                    <Field label="Moneda" error={errors.moneda} required>
                        {(id) => (
                            <Select
                                value={data.moneda}
                                onValueChange={(valor) =>
                                    setData('moneda', valor)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {monedas.map((opcion) => (
                                        <SelectItem
                                            key={opcion.value}
                                            value={opcion.value}
                                        >
                                            {opcion.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    {data.moneda === 'USD' && (
                        <Field
                            label="Tipo de cambio"
                            error={errors.tipo_cambio}
                            required
                            ayuda="El del día del comprobante."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="decimal"
                                    value={data.tipo_cambio}
                                    onChange={(e) =>
                                        setData('tipo_cambio', e.target.value)
                                    }
                                    placeholder="3.750"
                                />
                            )}
                        </Field>
                    )}
                    {llevaIgv && (
                        <div className="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                id="precios-con-igv"
                                checked={data.precios_con_igv}
                                onCheckedChange={(valor) =>
                                    setData('precios_con_igv', valor === true)
                                }
                            />
                            <Label htmlFor="precios-con-igv">
                                Los precios del comprobante ya incluyen IGV
                            </Label>
                        </div>
                    )}
                </section>

                <section className="flex flex-col gap-3 rounded-xl border bg-card p-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="text-sm font-semibold">Artículos</h2>
                            <p className="text-xs text-muted-foreground">
                                Cantidad y precio unitario como vienen en el
                                comprobante.
                            </p>
                        </div>
                        {puedeCrearArticulo && (
                            <ArticuloRapidoDialog
                                categorias={categorias}
                                ubicaciones={ubicaciones}
                                unidades={unidades}
                            />
                        )}
                    </div>

                    <InputError message={errors.detalles} />

                    <ul className="flex flex-col gap-3">
                        {data.detalles.map((linea, indice) => {
                            const articulo = articulos.find(
                                (a) => a.id === linea.articulo_id,
                            );
                            const unidadCompra =
                                articulo?.unidad_compra ?? articulo?.unidad;
                            const importe =
                                (Number(linea.cantidad) || 0) *
                                (Number(linea.precio_unitario) || 0);

                            return (
                                <li
                                    key={indice}
                                    className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_8rem_9rem_8rem_auto] sm:items-start"
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
                                                articulos,
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
                                                {articulo.codigo} · stock{' '}
                                                {formatearCantidad(
                                                    articulo.stock,
                                                    articulo.unidad,
                                                )}
                                                {articulo.unidad_compra &&
                                                    ` · 1 ${articulo.unidad_compra} = ${formatearCantidad(articulo.factor_compra, articulo.unidad)}`}
                                                {articulo.costo_promedio !==
                                                    null &&
                                                    articulo.costo_promedio >
                                                        0 &&
                                                    ` · promedio ${formatearUnitario(articulo.costo_promedio)}`}
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
                                            placeholder={`Cant. ${unidadCompra ?? ''}`}
                                            aria-label="Cantidad"
                                        />
                                        <InputError
                                            message={errorDeLinea(
                                                errores,
                                                indice,
                                                'cantidad',
                                            )}
                                        />
                                    </div>
                                    <div>
                                        <Input
                                            inputMode="decimal"
                                            value={linea.precio_unitario}
                                            onChange={(e) =>
                                                cambiarLinea(indice, {
                                                    precio_unitario:
                                                        e.target.value,
                                                })
                                            }
                                            placeholder={`P. unit. ${data.moneda === 'USD' ? '$' : 'S/'}`}
                                            aria-label="Precio unitario"
                                        />
                                        <InputError
                                            message={errorDeLinea(
                                                errores,
                                                indice,
                                                'precio_unitario',
                                            )}
                                        />
                                    </div>
                                    <p className="self-center text-right text-sm font-medium tabular-nums">
                                        {formatearMonto(importe, data.moneda)}
                                    </p>
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
                                lineaVacia(),
                            ])
                        }
                    >
                        <Plus className="size-4" />
                        Otra línea
                    </Button>

                    <dl className="ml-auto grid w-full max-w-xs grid-cols-2 gap-x-4 gap-y-1 border-t pt-3 text-sm">
                        {llevaIgv && (
                            <>
                                <dt className="text-muted-foreground">
                                    Subtotal
                                </dt>
                                <dd className="text-right tabular-nums">
                                    {formatearMonto(subtotal, data.moneda)}
                                </dd>
                                <dt className="text-muted-foreground">IGV</dt>
                                <dd className="text-right tabular-nums">
                                    {formatearMonto(
                                        total - subtotal,
                                        data.moneda,
                                    )}
                                </dd>
                            </>
                        )}
                        <dt className="font-medium">Total</dt>
                        <dd className="text-right font-medium tabular-nums">
                            {formatearMonto(total, data.moneda)}
                        </dd>
                        <dt className="text-muted-foreground">
                            Entra al kardex
                        </dt>
                        <dd className="text-right tabular-nums">
                            {formatearMonto(costoKardex, 'PEN')}
                        </dd>
                    </dl>
                    <p className="text-right text-xs text-muted-foreground">
                        {creditoFiscal
                            ? 'Con factura el IGV es crédito fiscal: el costo va sin IGV.'
                            : llevaIgv
                              ? 'Con boleta el IGV no se recupera: es parte del costo.'
                              : 'Sin IGV aparte: el costo es lo pagado.'}
                    </p>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <Field
                        label="PDF o foto del comprobante"
                        error={errors.comprobante}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="file"
                                accept="application/pdf,image/*"
                                onChange={(e) =>
                                    setData(
                                        'comprobante',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        )}
                    </Field>
                    <Field label="Observación" error={errors.observacion}>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                value={data.observacion}
                                onChange={(e) =>
                                    setData('observacion', e.target.value)
                                }
                            />
                        )}
                    </Field>
                </section>

                <div className="flex items-center justify-end gap-3">
                    {progress && (
                        <span className="text-xs text-muted-foreground">
                            Subiendo {progress.percentage}%
                        </span>
                    )}
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Registrar compra
                    </Button>
                </div>
            </form>
        </div>
    );
}
