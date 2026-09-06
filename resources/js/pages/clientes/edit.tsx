import { Head, setLayoutProps } from '@inertiajs/react';
import clientes, {
    edit,
} from '@/actions/App/Http/Controllers/ClienteController';
import { ClienteForm } from '@/components/clientes/cliente-form';
import type { Cliente } from '@/types/fleet';

export default function ClienteEdit({ cliente }: { cliente: Cliente }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: clientes.index().url },
            { title: cliente.alias, href: edit(cliente.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Editar ${cliente.alias}`} />

            <div>
                <p className="text-sm text-muted-foreground">
                    {cliente.razon_social} · RUC {cliente.ruc}
                </p>
            </div>

            <ClienteForm mode="edit" cliente={cliente} />
        </div>
    );
}
