import { Head, setLayoutProps } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import proveedores from '@/actions/App/Http/Controllers/Almacen/ProveedorController';
import { AlmacenNav, migasAlmacen } from '@/components/almacen/almacen-nav';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { ProveedorDialog } from '@/components/almacen/proveedor-dialog';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { useFiltros } from '@/hooks/use-filtros';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearSoles } from '@/lib/format';
import type { Proveedor } from '@/types/almacen';
import type { Paginator } from '@/types/fleet';

type Props = {
    proveedores: Paginator<Proveedor>;
    filtros: { buscar: string };
};

/** A quién se le compra, con cuántas compras y cuánto se le compró. */
export default function ProveedoresIndex({
    proveedores: paginador,
    filtros,
}: Props) {
    const { puede } = usePermisos();
    const puedeEditar = puede('almacen.catalogo');
    const { buscar, setBuscar } = useFiltros(filtros, proveedores.index().url);

    setLayoutProps({
        breadcrumbs: migasAlmacen({
            title: 'Proveedores',
            href: proveedores.index().url,
        }),
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Proveedores" />

            <EncabezadoPantalla
                titulo="Proveedores"
                descripcion="A quién se le compran repuestos, lubricantes y servicios."
                icono={Truck}
                acciones={puedeEditar && <ProveedorDialog />}
            />

            <AlmacenNav actual="proveedores" />

            <Input
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
                placeholder="Buscar por razón social, RUC o contacto..."
                className="h-11 max-w-sm md:h-9"
            />

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<Truck className="size-7" />}
                    titulo="Todavía no hay proveedores"
                    descripcion="Regístralos acá o al cargar su primera factura."
                />
            ) : (
                <>
                    <ul className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        {paginador.data.map((proveedor) => (
                            <li
                                key={proveedor.id}
                                className="flex flex-col gap-2 rounded-xl border bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {proveedor.nombre_comercial ??
                                                proveedor.razon_social}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {proveedor.ruc
                                                ? `RUC ${proveedor.ruc}`
                                                : 'Sin RUC'}
                                            {proveedor.nombre_comercial &&
                                                ` · ${proveedor.razon_social}`}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {!proveedor.activo && (
                                            <EstadoDocumento tono="anulado">
                                                Inactivo
                                            </EstadoDocumento>
                                        )}
                                        {puedeEditar && (
                                            <ProveedorDialog
                                                proveedor={proveedor}
                                            />
                                        )}
                                    </div>
                                </div>
                                {(proveedor.contacto || proveedor.telefono) && (
                                    <p className="text-sm">
                                        {[
                                            proveedor.contacto,
                                            proveedor.telefono,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {proveedor.compras}{' '}
                                    {proveedor.compras === 1
                                        ? 'compra'
                                        : 'compras'}
                                    {proveedor.comprado !== null &&
                                        proveedor.compras > 0 &&
                                        ` · ${formatearSoles(proveedor.comprado)} sin IGV`}
                                    {proveedor.ultima_compra &&
                                        ` · última el ${formatearFecha(proveedor.ultima_compra)}`}
                                    {proveedor.condicion_pago &&
                                        ` · ${proveedor.condicion_pago}`}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}
