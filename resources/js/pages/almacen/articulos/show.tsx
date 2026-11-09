import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Download, Package, Pencil } from 'lucide-react';
import { useState } from 'react';
import articulos from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import ingresos from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import inventarios from '@/actions/App/Http/Controllers/Almacen/InventarioAlmacenController';
import salidas from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import {
    CifraAlmacen,
    EstadoDocumento,
} from '@/components/almacen/estado-documento';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Paginacion } from '@/components/ui/paginacion';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearCantidad, formatearUnitario } from '@/lib/almacen';
import { formatearFecha, formatearPlaca, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ArticuloFicha, MovimientoKardex } from '@/types/almacen';
import type { Paginator } from '@/types/fleet';

type Props = {
    articulo: ArticuloFicha;
    movimientos: Paginator<MovimientoKardex>;
    consumo: { placa: string; cantidad: number; costo: number | null }[];
};

/**
 * La ficha de un artículo: cuánto hay, cuánto vale, a qué unidades se fue y
 * su kardex, del movimiento más nuevo al más viejo.
 */
export default function ArticuloShow({
    articulo,
    movimientos,
    consumo,
}: Props) {
    const { puede } = usePermisos();
    const veCostos = puede('almacen.ver_costos');
    const anio = new Date().getFullYear();
    const [desde, setDesde] = useState(`${anio}-01-01`);
    const [hasta, setHasta] = useState(new Date().toISOString().slice(0, 10));

    setLayoutProps({
        breadcrumbs: migasAlmacen({
            title: articulo.codigo,
            href: articulos.show(articulo.id).url,
        }),
    });

    return (
        <div className="mx-auto flex w-full max-w-[1400px] flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={articulo.nombre} />

            <EncabezadoPantalla
                titulo={articulo.nombre}
                descripcion={[
                    articulo.codigo,
                    articulo.categoria,
                    articulo.marca,
                    articulo.numero_parte && `N° ${articulo.numero_parte}`,
                ]
                    .filter(Boolean)
                    .join(' · ')}
                icono={Package}
                acciones={
                    puede('almacen.catalogo') && (
                        <Button variant="outline" asChild>
                            <Link href={articulos.edit(articulo.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )
                }
            />

            {!articulo.activo && (
                <p className="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                    Dado de baja: ya no se compra ni se elige en los vales.
                </p>
            )}

            <div
                className={cn(
                    'grid grid-cols-2 gap-2',
                    veCostos ? 'sm:grid-cols-4' : 'sm:grid-cols-2',
                )}
            >
                <CifraAlmacen
                    valor={formatearCantidad(articulo.stock, articulo.unidad)}
                    etiqueta={
                        articulo.stock_minimo !== null
                            ? `Stock · mínimo ${formatearCantidad(articulo.stock_minimo)}`
                            : 'Stock'
                    }
                    className={
                        articulo.stock <= 0
                            ? 'text-red-700 dark:text-red-400'
                            : articulo.bajo_minimo
                              ? 'text-amber-700 dark:text-amber-400'
                              : undefined
                    }
                />
                <CifraAlmacen
                    valor={articulo.ubicacion ?? '—'}
                    etiqueta="Ubicación"
                    className="text-base"
                />
                {veCostos && (
                    <>
                        <CifraAlmacen
                            valor={formatearUnitario(articulo.costo_promedio)}
                            etiqueta={`Costo promedio por ${articulo.unidad}`}
                        />
                        <CifraAlmacen
                            valor={formatearSoles(articulo.valor)}
                            etiqueta="Valor en almacén (sin IGV)"
                        />
                    </>
                )}
            </div>

            <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                <span>
                    Se cuenta en {articulo.unidad_label.toLowerCase()}
                    {articulo.unidad_compra &&
                        ` · se compra por ${articulo.unidad_compra} de ${formatearCantidad(articulo.factor_compra, articulo.unidad)}`}
                </span>
                {articulo.marcas_compatibles.length > 0 && (
                    <span>
                        · Sirve para {articulo.marcas_compatibles.join(', ')}
                    </span>
                )}
            </div>

            {articulo.observacion && (
                <p className="text-sm whitespace-pre-line">
                    {articulo.observacion}
                </p>
            )}

            {consumo.length > 0 && (
                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        A qué unidades se fue (último año)
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        {consumo.map((fila) => (
                            <span
                                key={fila.placa}
                                className="rounded-lg border bg-card px-3 py-1.5 text-sm"
                            >
                                <strong>{formatearPlaca(fila.placa)}</strong>{' '}
                                {formatearCantidad(
                                    fila.cantidad,
                                    articulo.unidad,
                                )}
                                {fila.costo !== null &&
                                    ` · ${formatearSoles(fila.costo)}`}
                            </span>
                        ))}
                    </div>
                </section>
            )}

            <section className="flex flex-col gap-3">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <h2 className="text-base font-semibold">Kardex</h2>
                    {veCostos && (
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="grid gap-1">
                                <Label
                                    htmlFor="kardex-desde"
                                    className="text-xs"
                                >
                                    Desde
                                </Label>
                                <Input
                                    id="kardex-desde"
                                    type="date"
                                    value={desde}
                                    onChange={(e) => setDesde(e.target.value)}
                                    className="h-9"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor="kardex-hasta"
                                    className="text-xs"
                                >
                                    Hasta
                                </Label>
                                <Input
                                    id="kardex-hasta"
                                    type="date"
                                    value={hasta}
                                    onChange={(e) => setHasta(e.target.value)}
                                    className="h-9"
                                />
                            </div>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        articulos.exportarKardex(articulo.id, {
                                            query: { desde, hasta },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" />
                                    Excel (formato 13.1)
                                </a>
                            </Button>
                        </div>
                    )}
                </div>

                {movimientos.data.length === 0 ? (
                    <EmptyState
                        icono={<Package className="size-7" />}
                        titulo="Sin movimientos todavía"
                        descripcion="Entra con un conteo (saldo inicial) o con una compra."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Documento</TableHead>
                                        <TableHead>Operación</TableHead>
                                        <TableHead className="text-right">
                                            Entra
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Sale
                                        </TableHead>
                                        {veCostos && (
                                            <>
                                                <TableHead className="text-right">
                                                    Costo unit.
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Costo total
                                                </TableHead>
                                            </>
                                        )}
                                        <TableHead className="text-right">
                                            Saldo
                                        </TableHead>
                                        {veCostos && (
                                            <TableHead className="text-right">
                                                Valor
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {movimientos.data.map((movimiento) => {
                                        const entra = movimiento.entrada > 0;

                                        return (
                                            <TableRow key={movimiento.id}>
                                                <TableCell className="whitespace-nowrap">
                                                    {formatearFecha(
                                                        movimiento.fecha,
                                                    )}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    <EnlaceDocumento
                                                        movimiento={movimiento}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <EstadoDocumento
                                                        tono={
                                                            entra
                                                                ? 'vigente'
                                                                : 'neutro'
                                                        }
                                                    >
                                                        {movimiento.tipo_label}
                                                    </EstadoDocumento>
                                                    {movimiento.detalle && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            {movimiento.detalle}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-emerald-700 tabular-nums dark:text-emerald-400">
                                                    {entra
                                                        ? formatearCantidad(
                                                              movimiento.entrada,
                                                          )
                                                        : ''}
                                                </TableCell>
                                                <TableCell className="text-right text-red-700 tabular-nums dark:text-red-400">
                                                    {entra
                                                        ? ''
                                                        : formatearCantidad(
                                                              movimiento.salida,
                                                          )}
                                                </TableCell>
                                                {veCostos && (
                                                    <>
                                                        <TableCell className="text-right tabular-nums">
                                                            {formatearUnitario(
                                                                movimiento.costo_unitario,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {formatearSoles(
                                                                movimiento.costo_total,
                                                            )}
                                                        </TableCell>
                                                    </>
                                                )}
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatearCantidad(
                                                        movimiento.saldo_cantidad,
                                                    )}
                                                </TableCell>
                                                {veCostos && (
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatearSoles(
                                                            movimiento.saldo_valor,
                                                        )}
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                        <Paginacion paginador={movimientos} />
                    </>
                )}
            </section>
        </div>
    );
}

function EnlaceDocumento({ movimiento }: { movimiento: MovimientoKardex }) {
    const enlace = movimiento.enlace;

    if (enlace === null) {
        return <span>{movimiento.documento}</span>;
    }

    const href =
        enlace.tipo === 'ingreso'
            ? ingresos.show(enlace.id)
            : enlace.tipo === 'salida'
              ? salidas.show(enlace.id)
              : inventarios.show(enlace.id);

    return (
        <Link href={href} className="font-medium text-primary hover:underline">
            {movimiento.documento}
        </Link>
    );
}
