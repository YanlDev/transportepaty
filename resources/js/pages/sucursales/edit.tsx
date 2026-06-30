import { Head, setLayoutProps } from '@inertiajs/react';
import sucursales, {
    edit,
} from '@/actions/App/Http/Controllers/SucursalController';
import { SucursalForm } from '@/components/sucursales/sucursal-form';
import type { Sucursal } from '@/types/fleet';

type Props = {
    sucursal: Sucursal;
};

export default function SucursalEdit({ sucursal }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Sucursales', href: sucursales.index().url },
            { title: sucursal.nombre, href: edit(sucursal.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Editar ${sucursal.nombre}`} />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Editar sucursal
                </h1>
                <p className="text-sm text-muted-foreground">
                    {sucursal.nombre} · {sucursal.codigo}
                </p>
            </div>

            <SucursalForm mode="edit" sucursal={sucursal} />
        </div>
    );
}
