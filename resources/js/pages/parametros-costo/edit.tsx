import { Head, setLayoutProps } from '@inertiajs/react';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import { FlotaForm } from '@/components/parametros-costo/flota-form';
import { GrupoCostos } from '@/components/parametros-costo/grupo-costos';
import { ResumenCosto } from '@/components/parametros-costo/grupo-costos';
import type {
    ComponenteCosto,
    EnumOption,
    ParametroFlota,
    TotalesCosto,
} from '@/types/fleet';

type Props = {
    flota: ParametroFlota;
    componentes: ComponenteCosto[];
    totales: TotalesCosto;
    naturalezas: EnumOption[];
};

export default function ParametrosCostoEdit({
    flota,
    componentes,
    totales,
    naturalezas,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cotizaciones', href: cotizaciones.index().url },
            { title: 'Estructura de costos', href: parametrosCosto.edit().url },
        ],
    });

    const fijos = componentes.filter((c) => c.tipo === 'fijo_dia');
    const variables = componentes.filter((c) => c.tipo === 'variable_km');

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <Head title="Estructura de costos" />

            <div>
                <h1 className="text-xl font-semibold tracking-tight">
                    Estructura de costos
                </h1>
                <p className="text-sm text-muted-foreground">
                    Lo que cuesta operar una unidad, línea por línea. Cada
                    componente se abre y muestra de dónde sale su tasa. Las
                    cotizaciones ya emitidas guardan la suya y no cambian.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <ResumenCosto
                    titulo="Costo fijo"
                    unidad="por día"
                    total={totales.fijo_dia}
                    directo={totales.fijo_dia_directo}
                    indirecto={totales.fijo_dia_indirecto}
                />
                <ResumenCosto
                    titulo="Costo variable"
                    unidad="por kilómetro"
                    total={totales.variable_km}
                    directo={totales.variable_km_directo}
                    indirecto={totales.variable_km_indirecto}
                />
            </div>

            <FlotaForm flota={flota} />

            <GrupoCostos
                titulo="Costos fijos"
                descripcion="Se pagan por día que la unidad queda tomada, aunque esté parada esperando turno de carga."
                componentes={fijos}
                naturalezas={naturalezas}
            />

            <GrupoCostos
                titulo="Costos variables"
                descripcion="Se pagan por kilómetro rodado."
                componentes={variables}
                naturalezas={naturalezas}
            />
        </div>
    );
}
