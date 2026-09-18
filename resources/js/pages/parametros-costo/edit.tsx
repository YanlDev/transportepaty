import { Head, setLayoutProps } from '@inertiajs/react';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import { CotizacionesTabs } from '@/components/cotizaciones/cotizaciones-tabs';
import { FlotaForm } from '@/components/parametros-costo/flota-form';
import {
    GrupoCostos,
    ResumenCosto,
} from '@/components/parametros-costo/grupo-costos';
import type {
    ComponenteCosto,
    ParametroFlota,
    TotalesCosto,
} from '@/types/fleet';

type Props = {
    flota: ParametroFlota;
    componentes: ComponenteCosto[];
    totales: TotalesCosto;
};

export default function ParametrosCostoEdit({
    flota,
    componentes,
    totales,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cotizaciones', href: cotizaciones.index().url },
            { title: 'Tarifario', href: parametrosCosto.edit().url },
        ],
    });

    const fijos = componentes.filter((c) => c.tipo === 'fijo_dia');
    const variables = componentes.filter((c) => c.tipo === 'variable_km');

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
            <Head title="Tarifario" />

            <CotizacionesTabs actual="tarifario" />

            <div>
                <h1 className="text-xl font-semibold tracking-tight">
                    Tarifario
                </h1>
                <p className="text-sm text-muted-foreground">
                    Lo que cuesta operar una unidad, por día y por kilómetro.
                    Estas tasas son las que usa el cotizador; las cotizaciones
                    ya emitidas guardan las suyas.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <ResumenCosto
                    titulo="Costo fijo"
                    unidad="por día"
                    total={totales.fijo_dia}
                />
                <ResumenCosto
                    titulo="Costo variable"
                    unidad="por kilómetro"
                    total={totales.variable_km}
                />
            </div>

            <GrupoCostos
                titulo="Costos fijos (S/ por día)"
                descripcion="Se pagan por día que la unidad queda tomada, aunque esté parada esperando turno de carga."
                tipo="fijo_dia"
                componentes={fijos}
            />

            <GrupoCostos
                titulo="Costos variables (S/ por km)"
                descripcion="Se pagan por kilómetro rodado. Peajes y viáticos van acá como un promedio por km."
                tipo="variable_km"
                componentes={variables}
            />

            <FlotaForm flota={flota} />
        </div>
    );
}
