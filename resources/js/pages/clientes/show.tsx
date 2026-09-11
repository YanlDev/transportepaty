import { Head, setLayoutProps } from '@inertiajs/react';
import clientes, {
    show,
} from '@/actions/App/Http/Controllers/ClienteController';
import { ClienteCabecera } from '@/components/clientes/cliente-cabecera';
import { ClienteFicha } from '@/components/clientes/cliente-ficha';
import { ClienteIndicadores } from '@/components/clientes/cliente-indicadores';
import { ClienteViajes } from '@/components/clientes/cliente-viajes';
import { usePermisos } from '@/hooks/use-permisos';
import type {
    Cliente,
    ClienteEstadisticas,
    ClienteViajeItem,
} from '@/types/fleet';

type Props = {
    cliente: Cliente;
    estadisticas: ClienteEstadisticas;
    viajes: ClienteViajeItem[];
};

export default function ClienteShow({
    cliente,
    estadisticas,
    viajes: ultimosViajes,
}: Props) {
    const { puedeEditar } = usePermisos();

    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: clientes.index().url },
            { title: cliente.alias, href: show(cliente.id).url },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1500px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={cliente.alias} />

            <ClienteCabecera cliente={cliente} puedeEditar={puedeEditar} />

            <ClienteIndicadores estadisticas={estadisticas} />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <ClienteViajes
                    viajes={ultimosViajes}
                    cliente={cliente.razon_social}
                />

                <ClienteFicha cliente={cliente} />
            </div>
        </div>
    );
}
