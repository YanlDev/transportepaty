import { Head, setLayoutProps } from '@inertiajs/react';
import vehiculos, {
    create,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { VehiculoForm } from '@/components/vehiculos/vehiculo-form';
import type { EnumOption } from '@/types/fleet';

type Props = {
    tipoInicial: string;
    tipos: EnumOption[];
    cajas: EnumOption[];
    estados: EnumOption[];
};

export default function VehiculoCreate({ tipoInicial, ...props }: Props) {
    const esCarreta = tipoInicial === 'carreta';

    setLayoutProps({
        breadcrumbs: [
            {
                title: esCarreta ? 'Carretas' : 'Tractos',
                href: (esCarreta ? vehiculos.carretas() : vehiculos.tractos())
                    .url,
            },
            { title: 'Nuevo', href: create({ query: { tipo: tipoInicial } }).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={esCarreta ? 'Nueva carreta' : 'Nuevo tracto'} />

            <div>
                <p className="text-sm text-muted-foreground">
                    Completa los datos para registrar{' '}
                    {esCarreta ? 'una carreta' : 'un tracto'} en la flota.
                </p>
            </div>

            <VehiculoForm mode="create" tipoInicial={tipoInicial} {...props} />
        </div>
    );
}
