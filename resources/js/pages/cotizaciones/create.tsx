import { Head, setLayoutProps } from '@inertiajs/react';
import cotizaciones, {
    create,
} from '@/actions/App/Http/Controllers/CotizacionController';
import { CotizacionForm } from '@/components/cotizaciones/cotizacion-form';
import type { BorradorCotizacion } from '@/components/cotizaciones/cotizacion-form';
import type {
    Cliente,
    EnumOption,
    LineaTarifa,
    PuntoTraslado,
} from '@/types/fleet';

type Props = {
    clientes: Pick<Cliente, 'id' | 'alias' | 'razon_social' | 'ruc'>[];
    puntos: Pick<PuntoTraslado, 'id' | 'nombre' | 'direccion'>[];
    estados: EnumOption[];
    lineas: LineaTarifa[];
    /** Llega vacío como lista cuando no hay nada que precargar. */
    borrador: BorradorCotizacion | [];
    flota: { margen_pct_default: number; igv_pct: number };
};

export default function CotizacionCreate({
    clientes,
    puntos,
    estados,
    lineas,
    borrador,
    flota,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cotizaciones', href: cotizaciones.index().url },
            { title: 'Nueva cotización', href: create().url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Nueva cotización" />

            <CotizacionForm
                mode="create"
                clientes={clientes}
                puntos={puntos}
                estados={estados}
                lineas={lineas}
                borrador={Array.isArray(borrador) ? {} : borrador}
                flota={flota}
            />
        </div>
    );
}
