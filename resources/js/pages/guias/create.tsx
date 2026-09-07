import { Head, setLayoutProps } from '@inertiajs/react';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { GuiaForm } from '@/components/guias/guia-form';
import type { EnumOption } from '@/types/fleet';

type Props = {
    serie: string;
    emisorConfigurado: boolean;
    clientes: (EnumOption & { ruc: string; razon_social: string })[];
    tractos: (EnumOption & { tuc: string | null })[];
    carretas: (EnumOption & { tuc: string | null })[];
    conductores: (EnumOption & {
        documento: string;
        licencia: string | null;
    })[];
    puntosFrecuentes: (EnumOption & { ubigeo: string; direccion: string })[];
    tiposCarga: EnumOption[];
    motivosTraslado: EnumOption[];
};

export default function GuiaCreate(props: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Viajes', href: viajes.index().url },
            { title: 'Emitir guía', href: '#' },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <Head title="Emitir guía de transportista" />

            <div>
                <h1 className="text-xl font-semibold tracking-tight">
                    Emitir guía de transportista
                </h1>
                <p className="text-sm text-muted-foreground">
                    Se genera el XML, se firma con el certificado digital y se
                    envía a SUNAT. El viaje queda registrado con el número que
                    devuelva la serie.
                </p>
            </div>

            <GuiaForm {...props} />
        </div>
    );
}
