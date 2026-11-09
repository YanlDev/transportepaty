import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Package, X } from 'lucide-react';
import { useState } from 'react';
import articulos, {
    crearCategoria,
    store,
    update,
} from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
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
import type { ArticuloEditable, Opcion } from '@/types/almacen';
import type { EnumOption } from '@/types/fleet';

type Props = {
    articulo: ArticuloEditable | null;
    categorias: Opcion[];
    unidades: EnumOption[];
    marcasFlota: string[];
};

/** Sin unidad de compra: se compra igual que como se despacha. */
const MISMA_UNIDAD = '__misma__';

/**
 * Alta y edición de un artículo. Lo esencial es el nombre y la unidad; si
 * se compra en otra unidad (cilindro, caja) se dice cuántas del almacén trae.
 */
export default function ArticuloForm({
    articulo,
    categorias,
    unidades,
    marcasFlota,
}: Props) {
    const editando = articulo !== null;
    const [marcaEscrita, setMarcaEscrita] = useState('');

    setLayoutProps({
        breadcrumbs: migasAlmacen(
            ...(articulo
                ? [
                      {
                          title: articulo.codigo,
                          href: articulos.show(articulo.id).url,
                      },
                      {
                          title: 'Editar',
                          href: articulos.edit(articulo.id).url,
                      },
                  ]
                : [{ title: 'Artículo nuevo', href: articulos.create().url }]),
        ),
    });

    const { data, setData, post, put, processing, errors } = useForm({
        codigo: articulo?.codigo ?? '',
        nombre: articulo?.nombre ?? '',
        numero_parte: articulo?.numero_parte ?? '',
        marca: articulo?.marca ?? '',
        categoria_articulo_id: articulo?.categoria_articulo_id ?? null,
        unidad: articulo?.unidad ?? 'und',
        unidad_compra: articulo?.unidad_compra ?? '',
        factor_compra: articulo?.unidad_compra
            ? String(articulo.factor_compra)
            : '',
        stock_minimo:
            articulo?.stock_minimo === null || articulo === null
                ? ''
                : String(articulo.stock_minimo),
        ubicacion: articulo?.ubicacion ?? '',
        marcas_compatibles: articulo?.marcas_compatibles ?? ([] as string[]),
        observacion: articulo?.observacion ?? '',
        activo: articulo?.activo ?? true,
    });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        if (articulo) {
            put(update(articulo.id).url);
        } else {
            post(store().url);
        }
    };

    const alternarMarca = (marca: string) => {
        const limpia = marca.trim().toUpperCase();

        if (!limpia) {
            return;
        }

        setData(
            'marcas_compatibles',
            data.marcas_compatibles.includes(limpia)
                ? data.marcas_compatibles.filter((m) => m !== limpia)
                : [...data.marcas_compatibles, limpia],
        );
    };

    const unidad = unidades.find((u) => u.value === data.unidad);
    const unidadCompra = unidades.find((u) => u.value === data.unidad_compra);

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head
                title={
                    editando ? `Editar ${articulo.codigo}` : 'Artículo nuevo'
                }
            />

            <EncabezadoPantalla
                titulo={
                    editando ? `Editar ${articulo.nombre}` : 'Artículo nuevo'
                }
                descripcion="Lo que se guarda en el almacén: cómo se llama, cómo se cuenta y dónde está."
                icono={Package}
            />

            <form onSubmit={guardar} className="flex flex-col gap-6">
                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <Field label="Nombre" error={errors.nombre} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.nombre}
                                    onChange={(e) =>
                                        setData('nombre', e.target.value)
                                    }
                                    placeholder="Filtro de aceite Scania DC13"
                                />
                            )}
                        </Field>
                    </div>
                    <Field
                        label="Código"
                        error={errors.codigo}
                        ayuda="Vacío, se le pone el siguiente (A-00001…)."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.codigo}
                                onChange={(e) =>
                                    setData('codigo', e.target.value)
                                }
                                placeholder="Automático"
                            />
                        )}
                    </Field>
                    <Field label="N° de parte" error={errors.numero_parte}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.numero_parte}
                                onChange={(e) =>
                                    setData('numero_parte', e.target.value)
                                }
                                placeholder="El código del fabricante"
                            />
                        )}
                    </Field>
                    <Field label="Marca del repuesto" error={errors.marca}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.marca}
                                onChange={(e) =>
                                    setData('marca', e.target.value)
                                }
                                placeholder="Fleetguard, Bosch, original…"
                            />
                        )}
                    </Field>
                    <Field
                        label="Categoría"
                        error={errors.categoria_articulo_id}
                    >
                        {(id) => (
                            <SelectorBuscable
                                id={id}
                                valor={data.categoria_articulo_id}
                                onCambio={(valor) =>
                                    setData('categoria_articulo_id', valor)
                                }
                                etiqueta="Categoría"
                                opciones={categorias.map((categoria) => ({
                                    valor: categoria.id,
                                    etiqueta: categoria.nombre,
                                }))}
                                crear={{
                                    etiqueta: 'Crear categoría',
                                    onCrear: (texto) =>
                                        router.post(
                                            crearCategoria().url,
                                            { nombre: texto },
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        ),
                                }}
                            />
                        )}
                    </Field>
                    <Field label="Ubicación" error={errors.ubicacion}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.ubicacion}
                                onChange={(e) =>
                                    setData('ubicacion', e.target.value)
                                }
                                placeholder="Estante A-3, nivel 2"
                            />
                        )}
                    </Field>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-3">
                    <div className="sm:col-span-3">
                        <h2 className="text-sm font-semibold">
                            Cómo se cuenta
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            La unidad del almacén es la del stock y los vales.
                            Si se compra en otra (el aceite en cilindro), di
                            cuántas trae cada una.
                        </p>
                    </div>
                    <Field
                        label="Unidad del almacén"
                        error={errors.unidad}
                        required
                        ayuda={
                            articulo?.tiene_movimientos
                                ? 'Ya tiene movimientos: no se cambia.'
                                : undefined
                        }
                    >
                        {(id) => (
                            <Select
                                value={data.unidad}
                                onValueChange={(valor) =>
                                    setData('unidad', valor)
                                }
                                disabled={articulo?.tiene_movimientos}
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {unidades.map((opcion) => (
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
                    <Field label="Se compra por" error={errors.unidad_compra}>
                        {(id) => (
                            <Select
                                value={data.unidad_compra || MISMA_UNIDAD}
                                onValueChange={(valor) =>
                                    setData(
                                        'unidad_compra',
                                        valor === MISMA_UNIDAD ? '' : valor,
                                    )
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={MISMA_UNIDAD}>
                                        La misma unidad
                                    </SelectItem>
                                    {unidades
                                        .filter(
                                            (opcion) =>
                                                opcion.value !== data.unidad,
                                        )
                                        .map((opcion) => (
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
                    {data.unidad_compra && (
                        <Field
                            label={`${unidad?.label.split(' (')[0] ?? 'Unidades'} por ${unidadCompra?.label.split(' (')[0].toLowerCase() ?? 'unidad de compra'}`}
                            error={errors.factor_compra}
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="decimal"
                                    value={data.factor_compra}
                                    onChange={(e) =>
                                        setData('factor_compra', e.target.value)
                                    }
                                    placeholder="208"
                                />
                            )}
                        </Field>
                    )}
                    <Field
                        label="Stock mínimo"
                        error={errors.stock_minimo}
                        ayuda="Al llegar a este número avisa que hay que comprar."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                inputMode="decimal"
                                value={data.stock_minimo}
                                onChange={(e) =>
                                    setData('stock_minimo', e.target.value)
                                }
                                placeholder="Sin mínimo"
                            />
                        )}
                    </Field>
                </section>

                <section className="flex flex-col gap-3 rounded-xl border bg-card p-5">
                    <div>
                        <h2 className="text-sm font-semibold">
                            A qué unidades le sirve
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Las marcas de la flota que lo usan. Vacío, sirve
                            para todas.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {[
                            ...new Set([
                                ...marcasFlota,
                                ...data.marcas_compatibles,
                            ]),
                        ].map((marca) => {
                            const elegida =
                                data.marcas_compatibles.includes(marca);

                            return (
                                <button
                                    key={marca}
                                    type="button"
                                    onClick={() => alternarMarca(marca)}
                                    className={
                                        elegida
                                            ? 'flex items-center gap-1 rounded-full border border-primary bg-primary/10 px-3 py-1 text-sm text-primary'
                                            : 'rounded-full border px-3 py-1 text-sm text-muted-foreground hover:bg-accent'
                                    }
                                >
                                    {marca}
                                    {elegida && <X className="size-3" />}
                                </button>
                            );
                        })}
                    </div>
                    <div className="flex max-w-sm gap-2">
                        <Input
                            value={marcaEscrita}
                            onChange={(e) => setMarcaEscrita(e.target.value)}
                            placeholder="Otra marca o modelo"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    alternarMarca(marcaEscrita);
                                    setMarcaEscrita('');
                                }
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                alternarMarca(marcaEscrita);
                                setMarcaEscrita('');
                            }}
                        >
                            Agregar
                        </Button>
                    </div>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5">
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
                    {editando && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="articulo-activo"
                                checked={data.activo}
                                onCheckedChange={(valor) =>
                                    setData('activo', valor === true)
                                }
                            />
                            <Label htmlFor="articulo-activo">
                                Activo (desmarcado, ya no se compra ni se elige;
                                su historial se queda)
                            </Label>
                        </div>
                    )}
                </section>

                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                articulo
                                    ? articulos.show(articulo.id)
                                    : articulos.create()
                            }
                            onClick={(e) => {
                                if (!articulo) {
                                    e.preventDefault();
                                    window.history.back();
                                }
                            }}
                        >
                            Cancelar
                        </Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Guardar
                    </Button>
                </div>
            </form>
        </div>
    );
}
