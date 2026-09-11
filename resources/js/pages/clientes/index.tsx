import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import clientes, {
    create,
} from '@/actions/App/Http/Controllers/ClienteController';
import { ClienteTarjeta } from '@/components/clientes/cliente-tarjeta';
import { TablaClientes } from '@/components/clientes/tabla-clientes';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { useFiltros } from '@/hooks/use-filtros';
import { usePermisos } from '@/hooks/use-permisos';
import type { ClienteListItem, Paginator } from '@/types/fleet';

type Props = {
    clientes: Paginator<ClienteListItem>;
    filtros: { buscar: string };
    /** Viajes del cliente que más mueve: la escala de las barras. */
    maxViajes: number;
};

/** Las dos primeras iniciales del alias, para el cuadrito de color. */

export default function ClientesIndex({
    clientes: paginador,
    filtros,
    maxViajes,
}: Props) {
    const { url } = usePage();
    const { puedeEditar } = usePermisos();
    const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';

    const { buscar, setBuscar } = useFiltros(filtros, clientes.index().url);

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Clientes" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Clientes
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'cliente en el padrón'
                            : 'clientes en el padrón'}
                    </p>
                </div>

                {puedeEditar && (
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo cliente
                        </Link>
                    </Button>
                )}
            </div>

            <Input
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
                placeholder="Buscar por nombre, RUC o contacto..."
                className="h-11 max-w-sm md:h-9"
            />

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<Building2 className="size-7" />}
                    titulo="No se encontraron clientes"
                    descripcion={
                        <>
                            Ajusta la búsqueda
                            {puedeEditar && ' o registra un cliente nuevo'}.
                        </>
                    }
                />
            ) : (
                <>
                    <div className="flex flex-col gap-2 lg:hidden">
                        {paginador.data.map((cliente) => (
                            <ClienteTarjeta
                                key={cliente.id}
                                cliente={cliente}
                                maxViajes={maxViajes}
                            />
                        ))}
                    </div>

                    <TablaClientes
                        paginador={paginador}
                        maxViajes={maxViajes}
                        puedeEditar={puedeEditar}
                        query={query}
                    />

                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}

ClientesIndex.layout = {
    breadcrumbs: [{ title: 'Clientes', href: clientes.index().url }],
};
