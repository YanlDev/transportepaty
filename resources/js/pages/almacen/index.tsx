import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    ClipboardList,
    Download,
    FileSpreadsheet,
    Package,
    Plus,
    Settings,
    Warehouse,
} from 'lucide-react';
import { useState } from 'react';
import almacen from '@/actions/App/Http/Controllers/Almacen/AlmacenController';
import articulos from '@/actions/App/Http/Controllers/Almacen/ArticuloController';
import { index as configuracionAlmacen } from '@/actions/App/Http/Controllers/Almacen/ConfiguracionAlmacenController';
import ingresos from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import inventarios from '@/actions/App/Http/Controllers/Almacen/InventarioAlmacenController';
import salidas from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { AlmacenNav, migasAlmacen } from '@/components/almacen/almacen-nav';
import { CifraAlmacen } from '@/components/almacen/estado-documento';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { FiltroSelect } from '@/components/filtro-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
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
import { useFiltros } from '@/hooks/use-filtros';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearCantidad, formatearUnitario } from '@/lib/almacen';
import { formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ArticuloStock, Opcion } from '@/types/almacen';
import type { Paginator } from '@/types/fleet';

type Filtros = {
    buscar: string;
    categoria: string;
    ubicacion: string;
    estado: string;
};

type Props = {
    articulos: Paginator<ArticuloStock>;
    filtros: Filtros;
    categorias: Opcion[];
    ubicaciones: Opcion[];
    resumen: {
        articulos: number;
        bajo_minimo: number;
        sin_stock: number;
        valor: number | null;
        ingresos_mes: number;
        /** Dados de baja que todavía tienen stock: suman al valor. */
        baja_con_stock: number;
        salidas_mes: number;
    };
};

const ESTADOS = [
    { value: 'con_stock', label: 'Con stock' },
    { value: 'bajo_minimo', label: 'En el mínimo o debajo' },
    { value: 'sin_stock', label: 'Sin stock' },
    { value: 'inactivos', label: 'Dados de baja' },
];

/**
 * El stock del almacén: cuánto hay de cada artículo, cuánto vale y qué está
 * por acabarse. Desde acá se entra a todo lo demás del módulo.
 */
export default function AlmacenIndex({
    articulos: paginador,
    filtros,
    categorias,
    ubicaciones,
    resumen,
}: Props) {
    const { puede } = usePermisos();
    const veCostos = puede('almacen.ver_costos');
    const { buscar, setBuscar, aplicar } = useFiltros(
        filtros,
        almacen.index().url,
        { conservar: ['categorias', 'ubicaciones'] },
    );

    setLayoutProps({ breadcrumbs: migasAlmacen() });

    const query = new URLSearchParams(
        Object.entries(filtros).filter(([, valor]) => valor) as [
            string,
            string,
        ][],
    ).toString();

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Almacén" />

            <EncabezadoPantalla
                titulo="Almacén"
                descripcion="El stock del taller: lo que entra con su factura, lo que sale con su vale y cuánto vale."
                icono={Warehouse}
                acciones={
                    <>
                        {puede('almacen.despachar') && (
                            <Button
                                variant="outline"
                                asChild
                                className="max-md:hidden"
                            >
                                <Link href={salidas.create()}>
                                    <ArrowUpFromLine className="size-4" />
                                    Vale de salida
                                </Link>
                            </Button>
                        )}
                        {puede('almacen.ingresar') && (
                            <Button asChild className="max-md:hidden">
                                <Link href={ingresos.create()}>
                                    <ArrowDownToLine className="size-4" />
                                    Registrar compra
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />

            <AlmacenNav actual="stock" />

            <div
                className={cn(
                    'grid grid-cols-2 gap-2',
                    veCostos ? 'sm:grid-cols-4' : 'sm:grid-cols-3',
                )}
            >
                {veCostos && (
                    <CifraAlmacen
                        valor={formatearSoles(resumen.valor)}
                        etiqueta="Valor del inventario (sin IGV)"
                    />
                )}
                <CifraAlmacen
                    valor={resumen.articulos}
                    etiqueta="Artículos en el catálogo"
                />
                <button
                    type="button"
                    onClick={() => aplicar({ estado: 'bajo_minimo' })}
                    className="text-left"
                >
                    <CifraAlmacen
                        valor={resumen.bajo_minimo}
                        etiqueta="En el mínimo o debajo"
                        className={
                            resumen.bajo_minimo > 0
                                ? 'text-amber-700 dark:text-amber-400'
                                : undefined
                        }
                    />
                </button>
                <button
                    type="button"
                    onClick={() => aplicar({ estado: 'sin_stock' })}
                    className="text-left"
                >
                    <CifraAlmacen
                        valor={resumen.sin_stock}
                        etiqueta="Sin stock"
                        className={
                            resumen.sin_stock > 0
                                ? 'text-red-700 dark:text-red-400'
                                : undefined
                        }
                    />
                </button>
            </div>

            {resumen.baja_con_stock > 0 && (
                <button
                    type="button"
                    onClick={() => aplicar({ estado: 'inactivos' })}
                    className="self-start rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    {resumen.baja_con_stock === 1
                        ? '1 artículo dado de baja todavía tiene stock'
                        : `${resumen.baja_con_stock} artículos dados de baja todavía tienen stock`}
                    : suma al valor y se puede despachar.
                </button>
            )}

            {resumen.articulos === 0 && (
                <div className="flex flex-col gap-3 rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-900 sm:flex-row sm:items-center sm:justify-between dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                    <p>
                        <strong>Para empezar:</strong> abre un conteo y anota lo
                        que hay en cada estante. Lo contado es el saldo inicial;
                        desde ahí, todo entra con su factura y sale con su vale.
                    </p>
                    {puede('almacen.inventariar') && (
                        <Button asChild variant="outline" className="shrink-0">
                            <Link href={inventarios.index()}>
                                <ClipboardList className="size-4" />
                                Ir a conteos
                            </Link>
                        </Button>
                    )}
                </div>
            )}

            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <Input
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    placeholder="Buscar por nombre, código, N° de parte o marca..."
                    className="h-11 sm:max-w-sm md:h-9"
                />
                <FiltroSelect
                    valor={filtros.categoria || null}
                    onCambio={(valor) => aplicar({ categoria: valor ?? '' })}
                    todos="Todas las categorías"
                    etiqueta="Categoría"
                    opciones={categorias.map((categoria) => ({
                        value: String(categoria.id),
                        label: categoria.nombre,
                    }))}
                />
                {ubicaciones.length > 0 && (
                    <FiltroSelect
                        valor={filtros.ubicacion || null}
                        onCambio={(valor) =>
                            aplicar({ ubicacion: valor ?? '' })
                        }
                        todos="Todas las ubicaciones"
                        etiqueta="Ubicación"
                        opciones={ubicaciones.map((ubicacion) => ({
                            value: String(ubicacion.id),
                            label: ubicacion.nombre,
                        }))}
                    />
                )}
                <FiltroSelect
                    valor={filtros.estado || null}
                    onCambio={(valor) => aplicar({ estado: valor ?? '' })}
                    todos="Todo el stock"
                    etiqueta="Estado"
                    opciones={ESTADOS}
                />
                <div className="flex gap-2 sm:ml-auto">
                    <Button variant="outline" asChild>
                        <a
                            href={
                                almacen.exportar().url +
                                (query ? `?${query}` : '')
                            }
                        >
                            <Download className="size-4" />
                            Stock en Excel
                        </a>
                    </Button>
                    {veCostos && <KardexGeneralDialog />}
                    {puede('almacen.catalogo') && (
                        <Button
                            variant="outline"
                            size="icon"
                            asChild
                            aria-label="Configurar el almacén"
                            title="Categorías, ubicaciones y mínimos"
                        >
                            <Link href={configuracionAlmacen().url}>
                                <Settings className="size-4" />
                            </Link>
                        </Button>
                    )}
                    {puede('almacen.catalogo') && (
                        <Button variant="outline" asChild>
                            <Link href={articulos.create()}>
                                <Plus className="size-4" />
                                Artículo
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<Package className="size-7" />}
                    titulo="No hay artículos con esos filtros"
                    descripcion="Cambia la búsqueda o el filtro."
                />
            ) : (
                <>
                    <ul className="flex flex-col gap-2 md:hidden">
                        {paginador.data.map((articulo) => (
                            <li key={articulo.id}>
                                <Link
                                    href={articulos.show(articulo.id)}
                                    className="flex items-center gap-3 rounded-xl border bg-card p-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {articulo.nombre}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {articulo.codigo}
                                            {articulo.ubicacion &&
                                                ` · ${articulo.ubicacion}`}
                                        </p>
                                    </div>
                                    <Stock articulo={articulo} />
                                </Link>
                            </li>
                        ))}
                    </ul>

                    <div className="overflow-hidden rounded-xl border max-md:hidden">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Código</TableHead>
                                    <TableHead>Artículo</TableHead>
                                    <TableHead>Categoría</TableHead>
                                    <TableHead>Ubicación</TableHead>
                                    <TableHead className="text-right">
                                        Stock
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Mínimo
                                    </TableHead>
                                    {veCostos && (
                                        <>
                                            <TableHead className="text-right">
                                                Costo prom.
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Valor
                                            </TableHead>
                                        </>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((articulo) => (
                                    <TableRow
                                        key={articulo.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                articulos.show(articulo.id),
                                            )
                                        }
                                    >
                                        <TableCell className="font-mono text-xs">
                                            {articulo.codigo}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={articulos.show(
                                                    articulo.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {articulo.nombre}
                                            </Link>
                                            {(articulo.numero_parte ||
                                                articulo.marca) && (
                                                <p className="text-xs text-muted-foreground">
                                                    {[
                                                        articulo.marca,
                                                        articulo.numero_parte,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {articulo.categoria ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {articulo.ubicacion ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Stock articulo={articulo} />
                                        </TableCell>
                                        <TableCell className="text-right text-muted-foreground tabular-nums">
                                            {formatearCantidad(
                                                articulo.stock_minimo,
                                            )}
                                        </TableCell>
                                        {veCostos && (
                                            <>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatearUnitario(
                                                        articulo.costo_promedio,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatearSoles(
                                                        articulo.valor,
                                                    )}
                                                </TableCell>
                                            </>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <Paginacion paginador={paginador} />
                </>
            )}

            {puede('almacen.despachar') && (
                <AccionPrincipalMovil
                    href={salidas.create()}
                    icono={<ArrowUpFromLine />}
                >
                    Vale de salida
                </AccionPrincipalMovil>
            )}
        </div>
    );
}

/** El stock con su color: rojo sin stock, ámbar en el mínimo. */
function Stock({ articulo }: { articulo: ArticuloStock }) {
    return (
        <span
            className={cn(
                'font-semibold whitespace-nowrap tabular-nums',
                articulo.stock <= 0
                    ? 'text-red-700 dark:text-red-400'
                    : articulo.bajo_minimo
                      ? 'text-amber-700 dark:text-amber-400'
                      : undefined,
            )}
        >
            {formatearCantidad(articulo.stock, articulo.unidad)}
        </span>
    );
}

/** El kardex de todo el almacén en un período, para contabilidad. */
function KardexGeneralDialog() {
    const hoy = new Date();
    const [desde, setDesde] = useState(
        new Date(hoy.getFullYear(), hoy.getMonth(), 1)
            .toISOString()
            .slice(0, 10),
    );
    const [hasta, setHasta] = useState(hoy.toISOString().slice(0, 10));

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <FileSpreadsheet className="size-4" />
                    Kardex
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Kardex valorizado</DialogTitle>
                    <DialogDescription>
                        Todos los artículos con movimientos, en el orden del
                        formato 13.1 de SUNAT.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid grid-cols-2 gap-4 py-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="kardex-desde">Desde</Label>
                        <Input
                            id="kardex-desde"
                            type="date"
                            value={desde}
                            onChange={(e) => setDesde(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="kardex-hasta">Hasta</Label>
                        <Input
                            id="kardex-hasta"
                            type="date"
                            value={hasta}
                            onChange={(e) => setHasta(e.target.value)}
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button asChild>
                        <a
                            href={
                                articulos.exportarKardexGeneral({
                                    query: { desde, hasta },
                                }).url
                            }
                        >
                            <Download className="size-4" />
                            Descargar
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
