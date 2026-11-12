import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { Plus, ShoppingCart, Trash2, UserPlus, Wrench } from 'lucide-react';
import { useState } from 'react';
import compras, {
    store,
    update,
} from '@/actions/App/Http/Controllers/Almacen/ValeCompraController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { ArticuloRapidoDialog } from '@/components/almacen/articulo-rapido-dialog';
import { ProveedorDialog } from '@/components/almacen/proveedor-dialog';
import { CampoArchivo } from '@/components/campo-archivo';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { errorDeLinea, opcionesArticulo } from '@/lib/almacen';
import { formatearPlaca } from '@/lib/format';
import type {
    ArticuloElegible,
    Opcion,
    OrdenElegible,
    ProveedorElegible,
    VehiculoElegible,
} from '@/types/almacen';
import type { EnumOption } from '@/types/fleet';

type LineaInicial = {
    es_servicio: boolean;
    articulo_id: number | null;
    descripcion: string;
    cantidad: number;
    precio_unitario: number;
};

type Props = {
    /** Null al pedir uno nuevo. */
    vale: {
        id: number;
        numero: string;
        aprobado: boolean;
        cotizaciones: number;
    } | null;
    inicial: {
        tipo: string;
        proveedor_id: number | null;
        moneda: string;
        motivo: string;
        sustento_precio: string;
        orden_trabajo_id: number | null;
        vehiculo_id: number | null;
        detalles: LineaInicial[];
    };
    tipos: EnumOption[];
    monedas: EnumOption[];
    proveedores: ProveedorElegible[];
    /** Puede registrar un proveedor nuevo sin salir del pedido. */
    puedeCrearProveedor: boolean;
    articulos: ArticuloElegible[];
    /** Puede dar de alta un artículo que todavía no está en el catálogo. */
    puedeCrearArticulo: boolean;
    categorias: Opcion[];
    ubicaciones: Opcion[];
    unidades: EnumOption[];
    vehiculos: VehiculoElegible[];
    ordenes: OrdenElegible[];
    maximoKb: number;
};

type Linea = {
    es_servicio: boolean;
    articulo_id: number | null;
    descripcion: string;
    cantidad: string;
    precio_unitario: string;
};

const lineaVacia = (esServicio = false): Linea => ({
    es_servicio: esServicio,
    articulo_id: null,
    descripcion: '',
    cantidad: esServicio ? '1' : '',
    precio_unitario: '',
});

/**
 * Pedir una compra: a quién, qué, a cuánto y para qué. Queda por aprobar; al
 * aprobarse sale el vale firmado con que se compra. Corregir un vale ya
 * aprobado lo devuelve a aprobación.
 */
export default function CompraForm({
    vale,
    inicial,
    tipos,
    monedas,
    proveedores,
    puedeCrearProveedor,
    articulos,
    puedeCrearArticulo,
    categorias,
    ubicaciones,
    unidades,
    vehiculos,
    ordenes,
    maximoKb,
}: Props) {
    const editando = vale !== null;
    const [preparando, setPreparando] = useState(false);

    setLayoutProps({
        breadcrumbs: migasAlmacen(
            { title: 'Compras', href: compras.index().url },
            {
                title: editando ? vale.numero : 'Pedir compra',
                href: '#',
            },
        ),
    });

    const { data, setData, post, transform, processing, errors, progress } =
        useForm<{
            tipo: string;
            proveedor_id: number | null;
            moneda: string;
            motivo: string;
            sustento_precio: string;
            orden_trabajo_id: number | null;
            vehiculo_id: number | null;
            cotizaciones: File[];
            detalles: Linea[];
        }>({
            tipo: inicial.tipo,
            proveedor_id: inicial.proveedor_id,
            moneda: inicial.moneda,
            motivo: inicial.motivo,
            sustento_precio: inicial.sustento_precio,
            orden_trabajo_id: inicial.orden_trabajo_id,
            vehiculo_id: inicial.vehiculo_id,
            cotizaciones: [],
            detalles:
                inicial.detalles.length > 0
                    ? inicial.detalles.map((linea) => ({
                          es_servicio: linea.es_servicio,
                          articulo_id: linea.articulo_id,
                          descripcion: linea.descripcion,
                          cantidad: String(linea.cantidad),
                          precio_unitario: String(linea.precio_unitario),
                      }))
                    : [lineaVacia()],
        });
    const errores = errors as Record<string, string | undefined>;

    const emergencia = data.tipo === 'emergencia';
    const simbolo = data.moneda === 'USD' ? 'US$' : 'S/';
    const elegidos = new Set(data.detalles.map((l) => l.articulo_id));
    const total = data.detalles.reduce(
        (suma, linea) =>
            suma +
            Math.round(
                (Number(linea.cantidad) || 0) *
                    (Number(linea.precio_unitario) || 0) *
                    100,
            ) /
                100,
        0,
    );

    const cambiarLinea = (indice: number, cambios: Partial<Linea>) =>
        setData(
            'detalles',
            data.detalles.map((linea, i) =>
                i === indice ? { ...linea, ...cambios } : linea,
            ),
        );

    /** El artículo recién creado va a la primera línea vacía, o a una nueva. */
    const ponerArticuloNuevo = (id: number) =>
        setData((actual) => {
            const vacia = actual.detalles.findIndex(
                (linea) => !linea.es_servicio && linea.articulo_id === null,
            );

            return {
                ...actual,
                detalles:
                    vacia === -1
                        ? [
                              ...actual.detalles,
                              { ...lineaVacia(), articulo_id: id },
                          ]
                        : actual.detalles.map((linea, i) =>
                              i === vacia
                                  ? { ...linea, articulo_id: id }
                                  : linea,
                          ),
            };
        });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        // PHP no lee archivos de un PUT: la corrección viaja como POST con
        // `_method`, que Laravel convierte en PUT.
        transform((datos) => (editando ? { ...datos, _method: 'put' } : datos));

        post(editando ? update(vale.id).url : store().url, {
            preserveScroll: true,
        });
    };

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head
                title={editando ? `Corregir ${vale.numero}` : 'Pedir compra'}
            />

            <EncabezadoPantalla
                titulo={editando ? `Corregir ${vale.numero}` : 'Pedir compra'}
                descripcion={
                    editando && vale.aprobado
                        ? 'Este vale ya está aprobado: al guardar pierde la aprobación y la firma, y vuelve a quien aprueba.'
                        : 'Queda por aprobar. Recién con el vale aprobado se compra.'
                }
                icono={ShoppingCart}
            />

            <form onSubmit={guardar} className="flex flex-col gap-6">
                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <div className="grid gap-1.5 sm:col-span-2">
                        <span className="text-sm font-medium">
                            Tipo de compra
                        </span>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={data.tipo}
                            onValueChange={(valor) =>
                                valor && setData('tipo', valor)
                            }
                            className="w-full flex-wrap justify-start"
                        >
                            {tipos.map((tipo) => (
                                <ToggleGroupItem
                                    key={tipo.value}
                                    value={tipo.value}
                                    className="px-4"
                                >
                                    {tipo.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <p className="text-xs text-muted-foreground">
                            {emergencia
                                ? 'Para una unidad parada en ruta: no espera cotizaciones y también la aprueba el jefe de operaciones.'
                                : 'La aprueba gerencia. Necesita una cotización o decir cómo se verificó el precio.'}
                        </p>
                    </div>

                    <Field
                        label="Proveedor"
                        error={errors.proveedor_id}
                        required
                    >
                        {(id) => (
                            <div className="flex gap-2">
                                <div className="min-w-0 flex-1">
                                    <SelectorBuscable
                                        id={id}
                                        valor={data.proveedor_id}
                                        onCambio={(valor) =>
                                            setData('proveedor_id', valor)
                                        }
                                        etiqueta="Busca por nombre o RUC"
                                        opciones={proveedores.map((p) => ({
                                            valor: p.id,
                                            etiqueta: p.nombre,
                                            // Se busca también por RUC y por
                                            // razón social.
                                            detalle: [p.ruc, p.razon_social]
                                                .filter(Boolean)
                                                .join(' · '),
                                        }))}
                                        invalido={!!errors.proveedor_id}
                                    />
                                </div>
                                {puedeCrearProveedor && (
                                    <ProveedorDialog
                                        onCreado={(nuevo) =>
                                            setData('proveedor_id', nuevo)
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
                        )}
                    </Field>

                    <div className="grid gap-1.5">
                        <span className="text-sm font-medium">Moneda</span>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={data.moneda}
                            onValueChange={(valor) =>
                                valor && setData('moneda', valor)
                            }
                            className="justify-start"
                        >
                            {monedas.map((moneda) => (
                                <ToggleGroupItem
                                    key={moneda.value}
                                    value={moneda.value}
                                    className="px-4"
                                >
                                    {moneda.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <InputError message={errors.moneda} />
                    </div>

                    <Field
                        label="Orden de trabajo (si es para una)"
                        error={errors.orden_trabajo_id}
                    >
                        {(id) => (
                            <div className="flex gap-2">
                                <div className="min-w-0 flex-1">
                                    <SelectorBuscable
                                        id={id}
                                        valor={data.orden_trabajo_id}
                                        onCambio={(valor) =>
                                            setData((previo) => ({
                                                ...previo,
                                                orden_trabajo_id: valor,
                                                vehiculo_id:
                                                    ordenes.find(
                                                        (o) => o.id === valor,
                                                    )?.vehiculo_id ??
                                                    previo.vehiculo_id,
                                            }))
                                        }
                                        etiqueta="Sin orden"
                                        opciones={ordenes.map((orden) => ({
                                            valor: orden.id,
                                            etiqueta: orden.etiqueta,
                                            detalle: orden.placa,
                                        }))}
                                        invalido={!!errors.orden_trabajo_id}
                                    />
                                </div>
                                {data.orden_trabajo_id !== null && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            setData('orden_trabajo_id', null)
                                        }
                                    >
                                        Quitar
                                    </Button>
                                )}
                            </div>
                        )}
                    </Field>

                    <Field
                        label={
                            emergencia ? 'Unidad' : 'Unidad (si es para una)'
                        }
                        error={errors.vehiculo_id}
                        required={emergencia}
                    >
                        {(id) => (
                            <div className="flex gap-2">
                                <div className="min-w-0 flex-1">
                                    <SelectorBuscable
                                        id={id}
                                        valor={data.vehiculo_id}
                                        onCambio={(valor) =>
                                            setData('vehiculo_id', valor)
                                        }
                                        etiqueta="Para el almacén"
                                        opciones={vehiculos.map((v) => ({
                                            valor: v.id,
                                            etiqueta: `${formatearPlaca(v.placa)} · ${v.tipo}`,
                                            detalle: `${v.placa} ${v.marca ?? ''}`,
                                        }))}
                                        invalido={!!errors.vehiculo_id}
                                    />
                                </div>
                                {data.vehiculo_id !== null &&
                                    data.orden_trabajo_id === null && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() =>
                                                setData('vehiculo_id', null)
                                            }
                                        >
                                            Quitar
                                        </Button>
                                    )}
                            </div>
                        )}
                    </Field>

                    <div className="sm:col-span-2">
                        <Field
                            label="Para qué es"
                            error={errors.motivo}
                            required
                        >
                            {(id) => (
                                <Textarea
                                    id={id}
                                    rows={2}
                                    value={data.motivo}
                                    onChange={(e) =>
                                        setData('motivo', e.target.value)
                                    }
                                    placeholder="Qué falta y para qué: quien aprueba lo lee acá"
                                />
                            )}
                        </Field>
                    </div>
                </section>

                <section className="flex flex-col gap-3 rounded-xl border bg-card p-5">
                    <h2 className="text-sm font-semibold">Qué se compra</h2>
                    <p className="text-xs text-muted-foreground">
                        El precio es lo que se paga por unidad, con IGV si lo
                        lleva. El comprobante tendrá que ser por el mismo total:
                        si cambia, el vale vuelve a aprobación.
                    </p>
                    <InputError message={errors.detalles} />

                    <ul className="flex flex-col gap-3">
                        {data.detalles.map((linea, indice) => {
                            const articulo = articulos.find(
                                (a) => a.id === linea.articulo_id,
                            );

                            return (
                                <li
                                    key={indice}
                                    className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_7rem_8rem_auto] sm:items-start"
                                >
                                    <div className="min-w-0">
                                        {linea.es_servicio ? (
                                            <Input
                                                value={linea.descripcion}
                                                onChange={(e) =>
                                                    cambiarLinea(indice, {
                                                        descripcion:
                                                            e.target.value,
                                                    })
                                                }
                                                placeholder="Servicio de un tercero: qué va a hacer"
                                                aria-label="Descripción del servicio"
                                            />
                                        ) : (
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
                                        )}
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {linea.es_servicio
                                                ? 'No entra al almacén: suma al costo de la orden cuando se confirme.'
                                                : articulo
                                                  ? `${articulo.codigo} · se compra por ${articulo.unidad_compra ?? articulo.unidad}`
                                                  : puedeCrearArticulo
                                                    ? 'Si no está en el catálogo, créalo con «Artículo nuevo».'
                                                    : 'Si no está en el catálogo, pide al almacén que lo registre.'}
                                        </p>
                                        <InputError
                                            message={
                                                errorDeLinea(
                                                    errores,
                                                    indice,
                                                    'articulo_id',
                                                ) ??
                                                errorDeLinea(
                                                    errores,
                                                    indice,
                                                    'descripcion',
                                                )
                                            }
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
                                            placeholder="Cantidad"
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
                                            placeholder={`Precio ${simbolo}`}
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

                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setData('detalles', [
                                    ...data.detalles,
                                    lineaVacia(),
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Otro artículo
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setData('detalles', [
                                    ...data.detalles,
                                    lineaVacia(true),
                                ])
                            }
                        >
                            <Wrench className="size-4" />
                            Servicio de un tercero
                        </Button>
                        {puedeCrearArticulo && (
                            <ArticuloRapidoDialog
                                categorias={categorias}
                                ubicaciones={ubicaciones}
                                unidades={unidades}
                                onCreado={ponerArticuloNuevo}
                            />
                        )}
                    </div>

                    <p className="text-right text-sm text-muted-foreground">
                        Total a pagar:{' '}
                        <strong className="text-base text-foreground tabular-nums">
                            {simbolo}{' '}
                            {total.toLocaleString('es-PE', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}
                        </strong>
                    </p>
                </section>

                {!emergencia && (
                    <section className="flex flex-col gap-4 rounded-xl border bg-card p-5">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Sustento del precio
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Al menos una cotización. Si no hay, explica cómo
                                se verificó el precio.
                                {editando &&
                                    vale.cotizaciones > 0 &&
                                    ` Este vale ya tiene ${vale.cotizaciones} ${vale.cotizaciones === 1 ? 'cotización adjunta' : 'cotizaciones adjuntas'}.`}
                            </p>
                        </div>
                        <CampoArchivo
                            archivos={data.cotizaciones}
                            onArchivos={(lista) =>
                                setData('cotizaciones', lista)
                            }
                            multiple
                            perfil="comprobante"
                            maximoKb={maximoKb}
                            progreso={progress?.percentage ?? null}
                            error={
                                errores.cotizaciones ??
                                Object.entries(errores).find(([clave]) =>
                                    clave.startsWith('cotizaciones.'),
                                )?.[1]
                            }
                            onPreparando={setPreparando}
                        />
                        <Field
                            label="Sin cotización: cómo se verificó el precio"
                            error={errors.sustento_precio}
                        >
                            {(id) => (
                                <Textarea
                                    id={id}
                                    rows={2}
                                    value={data.sustento_precio}
                                    onChange={(e) =>
                                        setData(
                                            'sustento_precio',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Mismo precio que la última compra, lista de precios del proveedor, llamada a…"
                                />
                            )}
                        </Field>
                    </section>
                )}

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing || preparando}>
                        {processing && <Spinner />}
                        {editando
                            ? 'Guardar y mandar a aprobación'
                            : 'Pedir la compra'}
                    </Button>
                </div>
            </form>
        </div>
    );
}
