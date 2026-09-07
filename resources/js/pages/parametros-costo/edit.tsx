import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { useId } from 'react';
import cotizaciones from '@/actions/App/Http/Controllers/CotizacionController';
import parametrosCosto, {
    update,
} from '@/actions/App/Http/Controllers/ParametroCostoController';
import InputError from '@/components/input-error';
import { ComponenteCard } from '@/components/parametros-costo/componente-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatearSoles } from '@/lib/format';
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

type FormData = {
    tamano_flota: string;
    dias_ano: string;
    dias_mantenimiento: string;
    dias_certificaciones: string;
    dias_sincronizacion: string;
    igv_pct: string;
    margen_pct_default: string;
    viatico_dia: string;
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

    const { data, setData, put, transform, processing, errors } =
        useForm<FormData>({
            tamano_flota: flota.tamano_flota.toString(),
            dias_ano: flota.dias_ano.toString(),
            dias_mantenimiento: flota.dias_mantenimiento.toString(),
            dias_certificaciones: flota.dias_certificaciones.toString(),
            dias_sincronizacion: flota.dias_sincronizacion.toString(),
            igv_pct: (flota.igv_pct * 100).toString(),
            margen_pct_default: (flota.margen_pct_default * 100).toString(),
            viatico_dia: flota.viatico_dia.toString(),
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        transform((datos) => ({
            ...datos,
            igv_pct: (Number(datos.igv_pct) / 100).toString(),
            margen_pct_default: (
                Number(datos.margen_pct_default) / 100
            ).toString(),
        }));

        put(update().url, { preserveScroll: true });
    };

    // El divisor de toda la estructura: se muestra mientras se editan los días
    // perdidos porque es el número que mueve todas las tasas fijas a la vez.
    const diasDisponibles =
        Number(data.dias_ano) -
        Number(data.dias_mantenimiento) -
        Number(data.dias_certificaciones) -
        Number(data.dias_sincronizacion);

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
                <Resumen
                    titulo="Costo fijo"
                    unidad="por día"
                    total={totales.fijo_dia}
                    directo={totales.fijo_dia_directo}
                    indirecto={totales.fijo_dia_indirecto}
                />
                <Resumen
                    titulo="Costo variable"
                    unidad="por kilómetro"
                    total={totales.variable_km}
                    directo={totales.variable_km_directo}
                    indirecto={totales.variable_km_indirecto}
                />
            </div>

            <form
                onSubmit={submit}
                className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5"
            >
                <div>
                    <h2 className="text-sm font-semibold text-foreground">
                        Flota
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Un camión no factura los 365 días: lo que queda después
                        de restar los días perdidos es el divisor de todos los
                        costos fijos.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo
                        label="Unidades de la flota"
                        error={errors.tamano_flota}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                min={1}
                                value={data.tamano_flota}
                                onChange={(e) =>
                                    setData('tamano_flota', e.target.value)
                                }
                            />
                        )}
                    </Campo>
                    <Campo label="Días del año" error={errors.dias_ano}>
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                step="0.01"
                                value={data.dias_ano}
                                onChange={(e) =>
                                    setData('dias_ano', e.target.value)
                                }
                            />
                        )}
                    </Campo>
                    <Campo
                        label="Días en mantenimiento"
                        error={errors.dias_mantenimiento}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                step="0.01"
                                value={data.dias_mantenimiento}
                                onChange={(e) =>
                                    setData(
                                        'dias_mantenimiento',
                                        e.target.value,
                                    )
                                }
                            />
                        )}
                    </Campo>
                    <Campo
                        label="Días en certificaciones"
                        error={errors.dias_certificaciones}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                step="0.01"
                                value={data.dias_certificaciones}
                                onChange={(e) =>
                                    setData(
                                        'dias_certificaciones',
                                        e.target.value,
                                    )
                                }
                            />
                        )}
                    </Campo>
                    <Campo
                        label="Días por sincronización"
                        error={errors.dias_sincronizacion}
                        ayuda="Esperas de retorno entre viaje y viaje."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                step="0.01"
                                value={data.dias_sincronizacion}
                                onChange={(e) =>
                                    setData(
                                        'dias_sincronizacion',
                                        e.target.value,
                                    )
                                }
                            />
                        )}
                    </Campo>
                    <div className="grid content-start gap-1.5">
                        <Label>Días disponibles</Label>
                        <p className="flex h-9 items-center font-mono text-sm text-foreground tabular-nums">
                            {Number.isFinite(diasDisponibles)
                                ? diasDisponibles.toFixed(2)
                                : '—'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Días vendibles al año por unidad.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 border-t border-border pt-4 sm:grid-cols-3">
                    <Campo label="IGV (%)" error={errors.igv_pct}>
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                step="0.5"
                                value={data.igv_pct}
                                onChange={(e) =>
                                    setData('igv_pct', e.target.value)
                                }
                            />
                        )}
                    </Campo>
                    <Campo
                        label="Margen sugerido (%)"
                        error={errors.margen_pct_default}
                        ayuda="Se precarga en cada cotización nueva."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                step="0.5"
                                value={data.margen_pct_default}
                                onChange={(e) =>
                                    setData(
                                        'margen_pct_default',
                                        e.target.value,
                                    )
                                }
                            />
                        )}
                    </Campo>
                    <Campo
                        label="Viático por día (S/)"
                        error={errors.viatico_dia}
                        ayuda="Precarga los viáticos de cada cotización según sus días."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                step="0.5"
                                value={data.viatico_dia}
                                onChange={(e) =>
                                    setData('viatico_dia', e.target.value)
                                }
                            />
                        )}
                    </Campo>
                </div>

                <div className="flex justify-end border-t border-border pt-4">
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Guardar flota
                    </Button>
                </div>
            </form>

            <Grupo
                titulo="Costos fijos"
                descripcion="Se pagan por día que la unidad queda tomada, aunque esté parada esperando turno de carga."
                componentes={fijos}
                naturalezas={naturalezas}
            />

            <Grupo
                titulo="Costos variables"
                descripcion="Se pagan por kilómetro rodado."
                componentes={variables}
                naturalezas={naturalezas}
            />
        </div>
    );
}

function Grupo({
    titulo,
    descripcion,
    componentes,
    naturalezas,
}: {
    titulo: string;
    descripcion: string;
    componentes: ComponenteCosto[];
    naturalezas: EnumOption[];
}) {
    if (componentes.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-3">
            <div>
                <h2 className="text-sm font-semibold text-foreground">
                    {titulo}
                </h2>
                <p className="text-xs text-muted-foreground">{descripcion}</p>
            </div>

            {componentes.map((componente) => (
                <ComponenteCard
                    key={componente.id}
                    componente={componente}
                    naturalezas={naturalezas}
                />
            ))}
        </section>
    );
}

function Resumen({
    titulo,
    unidad,
    total,
    directo,
    indirecto,
}: {
    titulo: string;
    unidad: string;
    total: number;
    directo: number;
    indirecto: number;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <p className="text-xs text-muted-foreground">
                {titulo} {unidad}
            </p>
            <p className="font-mono text-2xl font-semibold text-foreground tabular-nums">
                {formatearSoles(total)}
            </p>
            <div className="mt-2 flex gap-4 text-xs text-muted-foreground">
                <span>Directo {formatearSoles(directo)}</span>
                <span>Indirecto {formatearSoles(indirecto)}</span>
            </div>
        </div>
    );
}

function Campo({
    label,
    error,
    ayuda,
    children,
}: {
    label: string;
    error?: string;
    ayuda?: string;
    children: (id: string) => React.ReactNode;
}) {
    const id = useId();

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children(id)}
            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            <InputError message={error} />
        </div>
    );
}
