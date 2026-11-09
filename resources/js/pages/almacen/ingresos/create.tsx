import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ArrowDownToLine,
    CircleCheck,
    Gift,
    Plus,
    Trash2,
    TriangleAlert,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import ingresos, {
    store,
} from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { ArticuloRapidoDialog } from '@/components/almacen/articulo-rapido-dialog';
import { ProveedorDialog } from '@/components/almacen/proveedor-dialog';
import { CampoArchivo } from '@/components/campo-archivo';
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
    calcularCompra,
    errorDeLinea,
    formatearCantidad,
    formatearMonto,
    formatearUnitario,
    opcionesArticulo,
    TIPOS_CON_IGV,
} from '@/lib/almacen';
import { cn } from '@/lib/utils';
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
    toleranciaTotal: number;
    /** El primer día que se puede registrar: el mes anterior ya cerró. */
    inicioPeriodo: string;
    maximoKb: number;
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
    bonificacion: boolean;
};

const lineaVacia = (): Linea => ({
    articulo_id: null,
    cantidad: '',
    precio_unitario: '',
    bonificacion: false,
});

/**
 * Registrar una compra tal como dice el comprobante: el proveedor, la serie
 * y el número, la moneda y, línea por línea, cantidad y precio; además el
 * descuento, el flete y lo que vino de regalo. El total que se digita se
 * compara al toque con lo que suman los ítems, y abajo se ve lo que entra al
 * kardex: en soles y, con factura, sin IGV.
 */
export default function IngresoCreate({
    proveedores,
    articulos,
    comprobantes,
    monedas,
    hoy,
    tasaIgv,
    toleranciaTotal,
    inicioPeriodo,
    maximoKb,
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

    const [preparando, setPreparando] = useState(false);
    const { data, setData, post, processing, errors, progress } = useForm<{
        fecha: string;
        fecha_emision: string;
        proveedor_id: number | null;
        tipo_comprobante: string;
        serie_numero: string;
        moneda: string;
        tipo_cambio: string;
        precios_con_igv: boolean;
        descuento: string;
        flete: string;
        total_comprobante: string;
        observacion: string;
        comprobante: File | null;
        detalles: Linea[];
    }>({
        fecha: hoy,
        fecha_emision: hoy,
        proveedor_id: null,
        tipo_comprobante: 'factura',
        serie_numero: '',
        moneda: 'PEN',
        tipo_cambio: '',
        precios_con_igv: false,
        descuento: '',
        flete: '',
        total_comprobante: '',
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

    const llevaIgv = TIPOS_CON_IGV.includes(data.tipo_comprobante);
    const simbolo = data.moneda === 'USD' ? 'US$' : 'S/';
    const calculo = calcularCompra({
        tipoComprobante: data.tipo_comprobante,
        preciosConIgv: data.precios_con_igv,
        tasaIgv,
        tipoCambio: data.moneda === 'USD' ? Number(data.tipo_cambio) || 0 : 1,
        lineas: data.detalles.map((linea) => ({
            cantidad: Number(linea.cantidad) || 0,
            precio_unitario: Number(linea.precio_unitario) || 0,
            bonificacion: linea.bonificacion,
        })),
        descuento: Number(data.descuento) || 0,
        flete: Number(data.flete) || 0,
    });
    const declarado =
        data.total_comprobante === '' ? null : Number(data.total_comprobante);
    const diferencia =
        declarado === null
            ? null
            : Math.round((declarado - calculo.total) * 100) / 100;
    const cuadra =
        diferencia !== null && Math.abs(diferencia) <= toleranciaTotal;

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(store().url, { forceFormData: true });
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
                                    onCreado={(id) =>
                                        setData('proveedor_id', id)
                                    }
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
                    <Field
                        label="Serie y número"
                        error={errors.serie_numero}
                        required={llevaIgv}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.serie_numero}
                                onChange={(e) =>
                                    setData('serie_numero', e.target.value)
                                }
                                placeholder={
                                    llevaIgv ? 'F001-00012345' : 'Si tiene'
                                }
                            />
                        )}
                    </Field>
                    <Field
                        label="Fecha del comprobante"
                        error={errors.fecha_emision}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                max={data.fecha}
                                value={data.fecha_emision}
                                onChange={(e) =>
                                    setData('fecha_emision', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        label="Ingresa al almacén"
                        error={errors.fecha}
                        required
                        ayuda="Es la fecha del kardex. Los meses anteriores ya cerraron."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                min={inicioPeriodo}
                                max={hoy}
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                        )}
                    </Field>
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
                                comprobante, en{' '}
                                {data.moneda === 'USD' ? 'dólares' : 'soles'}.
                                Lo que vino de regalo se marca como bonificación
                                y se le pone su valor.
                            </p>
                        </div>
                        {puedeCrearArticulo && (
                            <ArticuloRapidoDialog
                                categorias={categorias}
                                ubicaciones={ubicaciones}
                                unidades={unidades}
                                onCreado={(id) =>
                                    // Va a la primera línea vacía, o a una nueva.
                                    setData((actual) => {
                                        const vacia = actual.detalles.findIndex(
                                            (linea) =>
                                                linea.articulo_id === null,
                                        );

                                        return {
                                            ...actual,
                                            detalles:
                                                vacia === -1
                                                    ? [
                                                          ...actual.detalles,
                                                          {
                                                              ...lineaVacia(),
                                                              articulo_id: id,
                                                          },
                                                      ]
                                                    : actual.detalles.map(
                                                          (linea, i) =>
                                                              i === vacia
                                                                  ? {
                                                                        ...linea,
                                                                        articulo_id:
                                                                            id,
                                                                    }
                                                                  : linea,
                                                      ),
                                        };
                                    })
                                }
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
                                    className={cn(
                                        'grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_8rem_9rem_8rem_auto] sm:items-start',
                                        linea.bonificacion &&
                                            'border-emerald-300 bg-emerald-50/50 dark:border-emerald-800 dark:bg-emerald-950/20',
                                    )}
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
                                        <label className="mt-1.5 flex w-fit cursor-pointer items-center gap-1.5 text-xs">
                                            <Checkbox
                                                checked={linea.bonificacion}
                                                onCheckedChange={(valor) =>
                                                    cambiarLinea(indice, {
                                                        bonificacion:
                                                            valor === true,
                                                    })
                                                }
                                                aria-label="Bonificación"
                                            />
                                            <Gift className="size-3.5 text-emerald-700 dark:text-emerald-400" />
                                            Bonificación (vino de regalo)
                                        </label>
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
                                            placeholder={
                                                linea.bonificacion
                                                    ? `Valor ${simbolo}`
                                                    : `P. unit. ${simbolo}`
                                            }
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
                                    <p
                                        className={cn(
                                            'self-center text-right text-sm font-medium tabular-nums',
                                            linea.bonificacion &&
                                                'text-emerald-700 dark:text-emerald-400',
                                        )}
                                    >
                                        {linea.bonificacion ? 'Regalo · ' : ''}
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
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5 lg:grid-cols-[1fr_20rem]">
                    <div className="grid content-start gap-4 sm:grid-cols-2">
                        <Field
                            label={`Descuento (${simbolo})`}
                            error={errors.descuento}
                            ayuda="El descuento global del comprobante."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="decimal"
                                    value={data.descuento}
                                    onChange={(e) =>
                                        setData('descuento', e.target.value)
                                    }
                                    placeholder="0.00"
                                />
                            )}
                        </Field>
                        <Field
                            label={`Flete, pasajes, envío (${simbolo})`}
                            error={errors.flete}
                            ayuda="Se reparte en el costo de los artículos."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="decimal"
                                    value={data.flete}
                                    onChange={(e) =>
                                        setData('flete', e.target.value)
                                    }
                                    placeholder="0.00"
                                />
                            )}
                        </Field>
                        {llevaIgv && (
                            <p className="text-xs text-muted-foreground sm:col-span-2">
                                Descuento y flete se escriben igual que los
                                precios: {data.precios_con_igv ? 'con' : 'sin'}{' '}
                                IGV.
                            </p>
                        )}
                        <Field
                            label={`Total del comprobante (${simbolo})`}
                            error={errors.total_comprobante}
                            required
                            ayuda="El total a pagar que dice el papel."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="decimal"
                                    value={data.total_comprobante}
                                    onChange={(e) =>
                                        setData(
                                            'total_comprobante',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                    aria-label="Total del comprobante"
                                    className={cn(
                                        diferencia !== null &&
                                            (cuadra
                                                ? 'border-emerald-500'
                                                : 'border-amber-500'),
                                    )}
                                />
                            )}
                        </Field>
                        <div className="self-end pb-2 text-sm">
                            {diferencia === null ? null : cuadra ? (
                                <span className="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                                    <CircleCheck className="size-4" />
                                    Cuadra con los ítems
                                </span>
                            ) : (
                                <span className="flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                                    <TriangleAlert className="size-4" />
                                    No cuadra: los ítems dan{' '}
                                    {formatearMonto(calculo.total, data.moneda)}
                                </span>
                            )}
                        </div>
                    </div>

                    <dl className="grid grid-cols-2 content-start gap-x-4 gap-y-1 rounded-lg bg-muted/40 p-4 text-sm">
                        <dt className="text-muted-foreground">Ítems</dt>
                        <dd className="text-right tabular-nums">
                            {formatearMonto(calculo.sumaItems, data.moneda)}
                        </dd>
                        {Number(data.descuento) > 0 && (
                            <>
                                <dt className="text-muted-foreground">
                                    Descuento
                                </dt>
                                <dd className="text-right tabular-nums">
                                    −
                                    {formatearMonto(
                                        Number(data.descuento),
                                        data.moneda,
                                    )}
                                </dd>
                            </>
                        )}
                        {Number(data.flete) > 0 && (
                            <>
                                <dt className="text-muted-foreground">Flete</dt>
                                <dd className="text-right tabular-nums">
                                    {formatearMonto(
                                        Number(data.flete),
                                        data.moneda,
                                    )}
                                </dd>
                            </>
                        )}
                        {llevaIgv && (
                            <>
                                <dt className="text-muted-foreground">
                                    Subtotal
                                </dt>
                                <dd className="text-right tabular-nums">
                                    {formatearMonto(
                                        calculo.subtotal,
                                        data.moneda,
                                    )}
                                </dd>
                                <dt className="text-muted-foreground">IGV</dt>
                                <dd className="text-right tabular-nums">
                                    {formatearMonto(calculo.igv, data.moneda)}
                                </dd>
                            </>
                        )}
                        <dt className="font-medium">Total</dt>
                        <dd className="text-right font-medium tabular-nums">
                            {formatearMonto(calculo.total, data.moneda)}
                        </dd>
                        <dt className="mt-2 border-t pt-2 text-muted-foreground">
                            Entra al kardex
                        </dt>
                        <dd className="mt-2 border-t pt-2 text-right tabular-nums">
                            {formatearMonto(calculo.costoKardex, 'PEN')}
                        </dd>
                        <dd className="col-span-2 text-xs text-muted-foreground">
                            {data.tipo_comprobante === 'factura'
                                ? 'Con factura el IGV es crédito fiscal: el costo va sin IGV.'
                                : llevaIgv
                                  ? 'Con boleta el IGV no se recupera: es parte del costo.'
                                  : 'Sin IGV aparte: el costo es lo pagado.'}
                            {calculo.bonificado > 0 &&
                                ` Incluye ${formatearMonto(calculo.bonificado, 'PEN')} de bonificaciones.`}
                        </dd>
                    </dl>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <div className="grid content-start gap-1.5">
                        <span className="text-sm font-medium">
                            Foto o PDF del comprobante{' '}
                            <span className="text-destructive">*</span>
                        </span>
                        <CampoArchivo
                            archivos={
                                data.comprobante ? [data.comprobante] : []
                            }
                            onArchivos={(lista) =>
                                setData('comprobante', lista[0] ?? null)
                            }
                            perfil="comprobante"
                            maximoKb={maximoKb}
                            progreso={progress?.percentage ?? null}
                            error={errors.comprobante}
                            onPreparando={setPreparando}
                        />
                    </div>
                    <Field label="Observación" error={errors.observacion}>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={3}
                                value={data.observacion}
                                onChange={(e) =>
                                    setData('observacion', e.target.value)
                                }
                            />
                        )}
                    </Field>
                </section>

                <div className="flex items-center justify-end gap-3">
                    <Button type="submit" disabled={processing || preparando}>
                        {processing && <Spinner />}
                        Registrar compra
                    </Button>
                </div>
            </form>
        </div>
    );
}
