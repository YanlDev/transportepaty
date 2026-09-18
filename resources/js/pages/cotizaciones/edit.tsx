import { Head, setLayoutProps } from '@inertiajs/react';
import cotizaciones, {
    edit,
} from '@/actions/App/Http/Controllers/CotizacionController';
import { CotizacionForm } from '@/components/cotizaciones/cotizacion-form';
import type {
    Cliente,
    Cotizacion,
    EnumOption,
    PuntoTraslado,
} from '@/types/fleet';

type Props = {
    cotizacion: Cotizacion;
    clientes: Pick<Cliente, 'id' | 'alias' | 'razon_social' | 'ruc'>[];
    puntos: Pick<PuntoTraslado, 'id' | 'nombre' | 'direccion'>[];
    estados: EnumOption[];
    flota: { margen_pct_default: number; igv_pct: number };
};

export default function CotizacionEdit({
    cotizacion,
    clientes,
    puntos,
    estados,
    flota,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cotizaciones', href: cotizaciones.index().url },
            {
                title: cotizacion.numero,
                href: edit(cotizacion.id).url,
            },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Cotización ${cotizacion.numero}`} />

            <CotizacionForm
                mode="edit"
                cotizacion={cotizacion}
                clientes={clientes}
                puntos={puntos}
                estados={estados}
                flota={flota}
            />
        </div>
    );
}
