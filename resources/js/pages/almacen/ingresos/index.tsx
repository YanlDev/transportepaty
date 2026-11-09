import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowDownToLine, Plus } from 'lucide-react';
import ingresos from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { AlmacenNav, migasAlmacen } from '@/components/almacen/almacen-nav';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { FiltroSelect } from '@/components/filtro-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { formatearMonto } from '@/lib/almacen';
import { formatearFecha, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { IngresoCabecera, ProveedorElegible } from '@/types/almacen';
import type { Paginator } from '@/types/fleet';

type Props = {
    ingresos: Paginator<IngresoCabecera>;
    filtros: { buscar: string; proveedor: string; tipo: string };
    proveedores: ProveedorElegible[];
};

/** Todo lo que entró al almacén: compras con su comprobante y devoluciones. */
export default function IngresosIndex({
    ingresos: paginador,
    filtros,
    proveedores,
}: Props) {
    const { puede } = usePermisos();
    const veCostos = puede('almacen.ver_costos');
    const { buscar, setBuscar, aplicar } = useFiltros(
        filtros,
        ingresos.index().url,
        { conservar: ['proveedores'] },
    );

    setLayoutProps({
        breadcrumbs: migasAlmacen({
            title: 'Ingresos',
            href: ingresos.index().url,
        }),
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Ingresos al almacén" />

            <EncabezadoPantalla
                titulo="Ingresos"
                descripcion="Las compras con su comprobante y lo que volvió de los vales."
                icono={ArrowDownToLine}
                acciones={
                    puede('almacen.ingresar') && (
                        <Button asChild className="max-md:hidden">
                            <Link href={ingresos.create()}>
                                <Plus className="size-4" />
                                Registrar compra
                            </Link>
                        </Button>
                    )
                }
            />

            <AlmacenNav actual="ingresos" />

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Input
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    placeholder="Buscar por N°, comprobante o proveedor..."
                    className="h-11 sm:max-w-sm md:h-9"
                />
                <FiltroSelect
                    valor={filtros.proveedor || null}
                    onCambio={(valor) => aplicar({ proveedor: valor ?? '' })}
                    todos="Todos los proveedores"
                    etiqueta="Proveedor"
                    opciones={proveedores.map((p) => ({
                        value: String(p.id),
                        label: p.nombre,
                    }))}
                />
                <FiltroSelect
                    valor={filtros.tipo || null}
                    onCambio={(valor) => aplicar({ tipo: valor ?? '' })}
                    todos="Compras y devoluciones"
                    etiqueta="Tipo"
                    opciones={[
                        { value: 'compra', label: 'Compras' },
                        { value: 'devolucion', label: 'Devoluciones' },
                    ]}
                />
            </div>

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<ArrowDownToLine className="size-7" />}
                    titulo="No hay ingresos"
                    descripcion="Cada compra que llega se registra con su comprobante."
                />
            ) : (
                <>
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>N°</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Comprobante</TableHead>
                                    <TableHead>Proveedor / vale</TableHead>
                                    <TableHead className="text-right">
                                        Líneas
                                    </TableHead>
                                    {veCostos && (
                                        <>
                                            <TableHead className="text-right">
                                                Total comprobante
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Al kardex
                                            </TableHead>
                                        </>
                                    )}
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {paginador.data.map((ingreso) => (
                                    <TableRow
                                        key={ingreso.id}
                                        className={cn(
                                            'cursor-pointer',
                                            ingreso.anulado && 'opacity-60',
                                        )}
                                        onClick={() =>
                                            router.visit(
                                                ingresos.show(ingreso.id),
                                            )
                                        }
                                    >
                                        <TableCell className="font-mono text-xs">
                                            <Link
                                                href={ingresos.show(ingreso.id)}
                                                className="hover:underline"
                                            >
                                                {ingreso.numero}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {formatearFecha(ingreso.fecha)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {ingreso.tipo === 'devolucion'
                                                ? 'Devolución'
                                                : `${ingreso.comprobante_tipo ?? ''} ${ingreso.serie_numero ?? ''}`}
                                        </TableCell>
                                        <TableCell>
                                            {ingreso.proveedor ??
                                                ingreso.salida ??
                                                '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {ingreso.lineas}
                                        </TableCell>
                                        {veCostos && (
                                            <>
                                                <TableCell className="text-right tabular-nums">
                                                    {ingreso.tipo === 'compra'
                                                        ? formatearMonto(
                                                              ingreso.total,
                                                              ingreso.moneda,
                                                          )
                                                        : '—'}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatearSoles(
                                                        ingreso.costo_total,
                                                    )}
                                                </TableCell>
                                            </>
                                        )}
                                        <TableCell>
                                            {ingreso.anulado && (
                                                <EstadoDocumento tono="anulado">
                                                    Anulado
                                                </EstadoDocumento>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    <Paginacion paginador={paginador} />
                </>
            )}

            {puede('almacen.ingresar') && (
                <AccionPrincipalMovil href={ingresos.create()} icono={<Plus />}>
                    Registrar compra
                </AccionPrincipalMovil>
            )}
        </div>
    );
}
