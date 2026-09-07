import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Download, Pencil } from 'lucide-react';
import cotizaciones, {
    edit,
    pdf,
    show,
} from '@/actions/App/Http/Controllers/CotizacionController';
import { DesglosePanel } from '@/components/cotizaciones/desglose-panel';
import { Button } from '@/components/ui/button';
import { formatearFecha } from '@/lib/format';
import type { Cotizacion } from '@/types/fleet';

type Props = {
    cotizacion: Cotizacion;
};

function soles(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export default function CotizacionShow({ cotizacion }: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Cotizaciones', href: cotizaciones.index().url },
            { title: cotizacion.numero, href: show(cotizacion.id).url },
        ],
    });

    // El IGV que se le aplicó, no el vigente: si mañana cambia la tasa, una
    // proforma vieja tiene que seguir mostrando la que se le facturó.
    const igvPct =
        cotizacion.subtotal > 0 ? cotizacion.igv / cotizacion.subtotal : 0;

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title={`Cotización ${cotizacion.numero}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="font-mono text-xl font-semibold tracking-tight">
                        {cotizacion.numero}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {cotizacion.cliente_nombre} · {cotizacion.estado_label}{' '}
                        · válida hasta {formatearFecha(cotizacion.valido_hasta)}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {/* Descarga directa: la proforma la genera el servidor,
                        no es una visita de Inertia. */}
                    <Button asChild variant="outline">
                        <a href={pdf(cotizacion.id).url}>
                            <Download className="size-4" />
                            Descargar proforma
                        </a>
                    </Button>
                    {puedeGestionar && (
                        <Button asChild>
                            <Link href={edit(cotizacion.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <section className="rounded-xl border border-border bg-card p-5">
                <h2 className="mb-4 text-sm font-semibold">Ruta</h2>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <Dato etiqueta="Origen" valor={cotizacion.origen} />
                    <Dato etiqueta="Destino" valor={cotizacion.destino} />
                    <Dato
                        etiqueta="Material"
                        valor={cotizacion.material ?? '—'}
                    />
                    <Dato etiqueta="Distancia" valor={`${cotizacion.km} km`} />
                    <Dato
                        etiqueta="Días de ruta"
                        valor={`${cotizacion.dias}`}
                    />
                    <Dato
                        etiqueta="Costo por kilómetro"
                        valor={`S/. ${soles(cotizacion.costo_por_km)}`}
                    />
                </dl>
            </section>

            <DesglosePanel
                desglose={{
                    ...cotizacion,
                    costo_por_km: cotizacion.costo_por_km,
                }}
                km={cotizacion.km}
                dias={cotizacion.dias}
                igvPct={igvPct}
            />

            {cotizacion.notas && (
                <section className="rounded-xl border border-border bg-card p-5">
                    <h2 className="mb-2 text-sm font-semibold">Notas</h2>
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {cotizacion.notas}
                    </p>
                </section>
            )}
        </div>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd>{valor}</dd>
        </div>
    );
}
