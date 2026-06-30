import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Fuel, Zap } from 'lucide-react';
import { useMemo, useState } from 'react';
import { index as vehiculos } from '@/actions/App/Http/Controllers/VehiculoController';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import { ChartConsumoMensual } from '@/components/combustible/chart-consumo-mensual';
import { ChartCosto } from '@/components/combustible/chart-costo';
import { ChartRendimiento } from '@/components/combustible/chart-rendimiento';
import { EliminarCargaDialog } from '@/components/combustible/eliminar-carga-dialog';
import { ProcesarCargaDialog } from '@/components/combustible/procesar-carga-dialog';
import { RegistrarCargaDialog } from '@/components/combustible/registrar-carga-dialog';
import { ResumenStats } from '@/components/combustible/resumen-stats';
import { TablaCargas } from '@/components/combustible/tabla-cargas';
import { EmptyState } from '@/components/empty-state';
import type { CargaCombustible, ResumenCombustible } from '@/types/fleet';

type VehiculoProp = {
    id: number;
    placa: string;
    marca: string;
    modelo: string;
    kilometraje: number;
    es_electrico: boolean;
};

type Props = {
    vehiculo: VehiculoProp;
    cargas: CargaCombustible[];
    resumen: ResumenCombustible;
    odometroSugerido: number;
    puede: { registrar: boolean; gestionar: boolean };
};

export default function Combustible({
    vehiculo,
    cargas,
    resumen,
    odometroSugerido,
    puede,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Vehículos', href: vehiculos().url },
            { title: vehiculo.placa, href: show(vehiculo.id).url },
            { title: 'Combustible', href: '' },
        ],
    });

    const [procesando, setProcesando] = useState<CargaCombustible | null>(null);
    const [eliminando, setEliminando] = useState<CargaCombustible | null>(null);

    const cronologico = useMemo(() => [...cargas].reverse(), [cargas]);

    const rendimientoData = useMemo(
        () =>
            cronologico
                .filter(
                    (c) => c.procesada && !c.anomalia && c.rendimiento !== null,
                )
                .map((c) => ({
                    fecha: etiquetaDia(c.fecha_carga),
                    rendimiento: c.rendimiento as number,
                })),
        [cronologico],
    );

    const { consumoData, costoData } = useMemo(
        () => agregarPorMes(cargas),
        [cargas],
    );

    return (
        <div className="flex h-full flex-col gap-6 p-4 md:p-6">
            <Head title={`Combustible · ${vehiculo.placa}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Link
                        href={show(vehiculo.id)}
                        className="mb-1 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        {vehiculo.marca} {vehiculo.modelo} · {vehiculo.placa}
                    </Link>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Combustible
                    </h1>
                </div>

                {puede.registrar && (
                    <RegistrarCargaDialog
                        vehiculoId={vehiculo.id}
                        directo={puede.gestionar}
                        odometroSugerido={odometroSugerido}
                    />
                )}
            </div>

            {vehiculo.es_electrico && (
                <div className="flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50/60 px-4 py-3 text-sm text-sky-800">
                    <Zap className="size-4 shrink-0" />
                    Este vehículo es eléctrico: se registran las cargas, pero el
                    rendimiento en km/galón no aplica.
                </div>
            )}

            <ResumenStats
                resumen={resumen}
                esElectrico={vehiculo.es_electrico}
            />

            {!vehiculo.es_electrico && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="lg:col-span-2">
                        <ChartRendimiento data={rendimientoData} />
                    </div>
                    <ChartConsumoMensual data={consumoData} />
                    <ChartCosto data={costoData} />
                </div>
            )}

            <div className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Historial de cargas
                </h2>

                {cargas.length === 0 ? (
                    <EmptyState
                        icon={<Fuel className="size-6" />}
                        text="Aún no hay cargas registradas para este vehículo."
                    />
                ) : (
                    <TablaCargas
                        cargas={cargas}
                        puedeGestionar={puede.gestionar}
                        onProcesar={setProcesando}
                        onEliminar={setEliminando}
                    />
                )}
            </div>

            {procesando && (
                <ProcesarCargaDialog
                    vehiculoId={vehiculo.id}
                    carga={procesando}
                    odometroSugerido={odometroSugerido}
                    onClose={() => setProcesando(null)}
                />
            )}

            {eliminando && (
                <EliminarCargaDialog
                    vehiculoId={vehiculo.id}
                    carga={eliminando}
                    onClose={() => setEliminando(null)}
                />
            )}
        </div>
    );
}

/** Short `dd/mm` label for the efficiency line. */
function etiquetaDia(iso: string): string {
    return new Date(iso).toLocaleDateString('es-PE', {
        day: '2-digit',
        month: '2-digit',
    });
}

/**
 * Buckets loads into the last 12 months: gallons per month and a running
 * cumulative cost. Only processed loads contribute.
 *
 * @return {consumoData: {mes:string,galones:number}[], costoData: {mes:string,acumulado:number}[]}
 */
function agregarPorMes(cargas: CargaCombustible[]) {
    const ahora = new Date();
    const buckets: {
        key: string;
        mes: string;
        galones: number;
        costo: number;
    }[] = [];

    for (let i = 11; i >= 0; i--) {
        const d = new Date(ahora.getFullYear(), ahora.getMonth() - i, 1);
        buckets.push({
            key: `${d.getFullYear()}-${d.getMonth()}`,
            mes: d.toLocaleDateString('es-PE', { month: 'short' }),
            galones: 0,
            costo: 0,
        });
    }

    const indice = new Map(buckets.map((b, i) => [b.key, i]));

    for (const carga of cargas) {
        if (!carga.procesada) {
            continue;
        }

        const d = new Date(carga.fecha_carga);
        const i = indice.get(`${d.getFullYear()}-${d.getMonth()}`);

        if (i === undefined) {
            continue;
        }

        buckets[i].galones += carga.galones ?? 0;
        buckets[i].costo += carga.costo_total ?? 0;
    }

    let acumulado = 0;

    const consumoData = buckets.map((b) => ({
        mes: b.mes,
        galones: Math.round(b.galones * 100) / 100,
    }));

    const costoData = buckets.map((b) => {
        acumulado += b.costo;

        return { mes: b.mes, acumulado: Math.round(acumulado * 100) / 100 };
    });

    return { consumoData, costoData };
}
