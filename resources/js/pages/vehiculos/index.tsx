import { Head, Link, usePage } from '@inertiajs/react';
import { Car, Plus } from 'lucide-react';
import vehiculos, {
    create,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { Button } from '@/components/ui/button';
import { VehiculoCard } from '@/components/vehiculos/vehiculo-card';
import { VehiculoFiltros } from '@/components/vehiculos/vehiculo-filtros';
import type { FiltrosVehiculo } from '@/hooks/use-vehiculo-filtros';
import type {
    EnumOption,
    Paginator,
    SucursalOption,
    VehiculoListItem,
} from '@/types/fleet';

type Props = {
    vehiculos: Paginator<VehiculoListItem>;
    filtros: FiltrosVehiculo;
    sucursales: SucursalOption[];
    estados: EnumOption[];
};

export default function VehiculosIndex({
    vehiculos: paginador,
    filtros,
    sucursales,
    estados,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Vehículos" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Vehículos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'vehículo registrado'
                            : 'vehículos registrados'}
                    </p>
                </div>

                {puedeGestionar && (
                    <Button
                        asChild
                        className="bg-emerald-800 hover:bg-emerald-900"
                    >
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo vehículo
                        </Link>
                    </Button>
                )}
            </div>

            <VehiculoFiltros
                filtros={filtros}
                sucursales={sucursales}
                estados={estados}
            />

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Car className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron vehículos</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta los filtros de búsqueda
                        {puedeGestionar && ' o registra tu primer vehículo'}.
                    </p>
                    {puedeGestionar && (
                        <Button asChild variant="outline" className="mt-6">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nuevo vehículo
                            </Link>
                        </Button>
                    )}
                </div>
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {paginador.data.map((vehiculo) => (
                            <VehiculoCard
                                key={vehiculo.id}
                                vehiculo={vehiculo}
                            />
                        ))}
                    </div>

                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}

function Paginacion({ paginador }: { paginador: Paginator<VehiculoListItem> }) {
    if (paginador.last_page <= 1) {
        return null;
    }

    return (
        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
            <p className="text-sm text-muted-foreground">
                Mostrando {paginador.from}–{paginador.to} de {paginador.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {paginador.links.map((link, indice) => (
                    <Button
                        key={indice}
                        asChild={!!link.url}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={!link.url}
                        className={
                            link.active
                                ? 'bg-emerald-800 hover:bg-emerald-900'
                                : ''
                        }
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}

VehiculosIndex.layout = {
    breadcrumbs: [{ title: 'Vehículos', href: vehiculos.index().url }],
};
