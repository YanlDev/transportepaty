import { Head, setLayoutProps } from '@inertiajs/react';
import clientes, {
    create,
} from '@/actions/App/Http/Controllers/ClienteController';
import { ClienteForm } from '@/components/clientes/cliente-form';

export default function ClienteCreate() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: clientes.index().url },
            { title: 'Nuevo cliente', href: create().url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nuevo cliente" />

            <ClienteForm mode="create" />
        </div>
    );
}
