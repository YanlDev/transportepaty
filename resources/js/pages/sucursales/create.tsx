import { Head } from '@inertiajs/react';
import sucursales, {
    create,
} from '@/actions/App/Http/Controllers/SucursalController';
import { SucursalForm } from '@/components/sucursales/sucursal-form';

export default function SucursalCreate() {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nueva sucursal" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Nueva sucursal
                </h1>
                <p className="text-sm text-muted-foreground">
                    Registra una sede para asignarle vehículos y conductores.
                </p>
            </div>

            <SucursalForm mode="create" />
        </div>
    );
}

SucursalCreate.layout = {
    breadcrumbs: [
        { title: 'Sucursales', href: sucursales.index().url },
        { title: 'Nueva', href: create().url },
    ],
};
