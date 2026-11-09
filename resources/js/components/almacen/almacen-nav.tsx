import { Link } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    ClipboardList,
    Package,
    Truck,
} from 'lucide-react';
import almacen from '@/actions/App/Http/Controllers/Almacen/AlmacenController';
import ingresos from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import inventarios from '@/actions/App/Http/Controllers/Almacen/InventarioAlmacenController';
import proveedores from '@/actions/App/Http/Controllers/Almacen/ProveedorController';
import salidas from '@/actions/App/Http/Controllers/Almacen/SalidaAlmacenController';
import { cn } from '@/lib/utils';

export type SeccionAlmacen =
    | 'stock'
    | 'ingresos'
    | 'salidas'
    | 'inventarios'
    | 'proveedores';

const SECCIONES: {
    clave: SeccionAlmacen;
    titulo: string;
    href: string;
    icono: typeof Package;
}[] = [
    { clave: 'stock', titulo: 'Stock', href: almacen.index().url, icono: Package },
    {
        clave: 'ingresos',
        titulo: 'Ingresos',
        href: ingresos.index().url,
        icono: ArrowDownToLine,
    },
    {
        clave: 'salidas',
        titulo: 'Vales de salida',
        href: salidas.index().url,
        icono: ArrowUpFromLine,
    },
    {
        clave: 'inventarios',
        titulo: 'Conteos',
        href: inventarios.index().url,
        icono: ClipboardList,
    },
    {
        clave: 'proveedores',
        titulo: 'Proveedores',
        href: proveedores.index().url,
        icono: Truck,
    },
];

/**
 * Las pestañas del almacén, iguales en todas sus pantallas: se pasa del stock
 * a los vales sin volver al menú. En el celular se deslizan de lado.
 */
export function AlmacenNav({ actual }: { actual: SeccionAlmacen }) {
    return (
        <nav className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <ul className="flex w-max gap-1 rounded-xl border bg-muted/40 p-1">
                {SECCIONES.map(({ clave, titulo, href, icono: Icono }) => (
                    <li key={clave}>
                        <Link
                            href={href}
                            prefetch
                            className={cn(
                                'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm whitespace-nowrap transition-colors',
                                actual === clave
                                    ? 'bg-background font-medium text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icono className="size-4" />
                            {titulo}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

/** Las migas de pan de cada pantalla del almacén. */
export function migasAlmacen(
    ...extra: { title: string; href: string }[]
): { title: string; href: string }[] {
    return [{ title: 'Almacén', href: almacen.index().url }, ...extra];
}
