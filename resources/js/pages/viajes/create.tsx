import { Head } from '@inertiajs/react';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { ViajeForm } from '@/components/viajes/viaje-form';
import type { ViajeOpciones } from '@/types/fleet';

type Props = {
    opciones: ViajeOpciones;
};

export default function ViajesCreate({ opciones }: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Nuevo viaje" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Nuevo viaje
                </h1>
                <p className="text-sm text-muted-foreground">
                    Al guardarlo podrás adjuntar los archivos de sus guías.
                </p>
            </div>

            <ViajeForm opciones={opciones} />
        </div>
    );
}

ViajesCreate.layout = {
    breadcrumbs: [
        { title: 'Viajes', href: viajes.index().url },
        { title: 'Nuevo', href: viajes.create().url },
    ],
};
