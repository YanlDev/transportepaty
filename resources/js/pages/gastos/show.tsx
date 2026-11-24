import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { Check, HandCoins, Paperclip } from 'lucide-react';
import gastos, {
    anular,
    aprobar,
    rechazar,
} from '@/actions/App/Http/Controllers/ValeGastoController';
import { AnularDialog } from '@/components/almacen/anular-dialog';
import { Dato } from '@/components/almacen/dato';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    formatearFechaHora,
    formatearPlaca,
    formatearSoles,
} from '@/lib/format';
import { TONO_VALE_GASTO } from '@/types/gastos';
import type { ValeGastoDetalle } from '@/types/gastos';

type Props = {
    vale: ValeGastoDetalle;
    puede: { aprobar: boolean; anular: boolean };
    /** Lo pidió quien lo está viendo: no puede aprobarlo. */
    esPropio: boolean;
};

/**
 * Un vale de gasto: quién pide, de qué área, cuánto y para qué, y lo que
 * gerencia decidió.
 */
export default function GastoShow({ vale, puede, esPropio }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Vales de gasto', href: gastos.index().url },
            { title: vale.numero, href: gastos.show(vale.id).url },
        ],
    });

    const aprobacion = useForm({});
    const erroresAprobacion = aprobacion.errors as Record<
        string,
        string | undefined
    >;

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 md:p-6">
            <Head title={vale.numero} />

            <EncabezadoPantalla
                titulo={`Vale de gasto ${vale.numero}`}
                descripcion={`${vale.area} · ${vale.categoria}`}
                icono={HandCoins}
                acciones={
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            label={vale.estado_label}
                            tone={TONO_VALE_GASTO[vale.estado]}
                        />
                        {puede.anular && (
                            <AnularDialog
                                url={anular(vale.id).url}
                                titulo={`Anular ${vale.numero}`}
                                descripcion="El vale queda a la vista con el motivo, pero ya no sirve para gastar."
                            />
                        )}
                    </div>
                }
            />

            {puede.aprobar && (
                <section className="flex flex-col gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-5">
                    <h2 className="text-sm font-semibold">
                        Espera tu aprobación
                    </h2>
                    {esPropio ? (
                        <p className="text-sm text-muted-foreground">
                            Este vale lo pediste tú: tiene que aprobarlo otra
                            persona.
                        </p>
                    ) : (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {vale.area} pide{' '}
                                <strong className="text-foreground">
                                    {formatearSoles(vale.monto)}
                                </strong>{' '}
                                para pagarle a {vale.beneficiario}. Al
                                aprobarlo, quien lo pidió recibe el aviso de que
                                ya puede gastar.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    disabled={aprobacion.processing}
                                    onClick={() =>
                                        aprobacion.post(aprobar(vale.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    {aprobacion.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Check className="size-4" />
                                    )}
                                    Aprobar el vale
                                </Button>
                                <AnularDialog
                                    url={rechazar(vale.id).url}
                                    titulo={`Rechazar ${vale.numero}`}
                                    descripcion="Quien lo pidió verá el motivo."
                                    etiqueta="Rechazar"
                                    ayuda="Por qué se rechaza"
                                />
                            </div>
                            <InputError
                                message={erroresAprobacion.aprobacion}
                            />
                        </>
                    )}
                </section>
            )}

            {vale.estado === 'rechazado' && (
                <p className="rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm">
                    <strong>Rechazado</strong>
                    {vale.rechazado_por && ` por ${vale.rechazado_por}`}
                    {vale.rechazado_at &&
                        ` el ${formatearFechaHora(vale.rechazado_at)}`}
                    : {vale.motivo_rechazo}
                </p>
            )}

            {vale.estado === 'anulado' && (
                <p className="rounded-xl border bg-muted/40 p-4 text-sm">
                    <strong>Anulado</strong>
                    {vale.anulado_por && ` por ${vale.anulado_por}`}
                    {vale.anulado_at &&
                        ` el ${formatearFechaHora(vale.anulado_at)}`}
                    : {vale.motivo_anulacion}
                </p>
            )}

            <section className="rounded-xl border bg-card p-5">
                <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <Dato titulo="Monto">
                        <span className="text-base font-semibold tabular-nums">
                            {formatearSoles(vale.monto)}
                        </span>
                    </Dato>
                    <Dato titulo="Se le paga a">{vale.beneficiario}</Dato>
                    <Dato titulo="Para">
                        {vale.placa
                            ? `Unidad ${formatearPlaca(vale.placa)}`
                            : 'Gasto general del área'}
                    </Dato>
                    <Dato titulo="Área">{vale.area}</Dato>
                    <Dato titulo="Categoría">{vale.categoria}</Dato>
                    <Dato titulo="Pidió">
                        {vale.solicitado_por ?? '—'}
                        {vale.solicitado && (
                            <span className="block text-xs text-muted-foreground">
                                {formatearFechaHora(vale.solicitado)}
                            </span>
                        )}
                    </Dato>
                    <Dato titulo="Aprobó">
                        {vale.aprobado_por ?? '—'}
                        {vale.aprobado_at && (
                            <span className="block text-xs text-muted-foreground">
                                {formatearFechaHora(vale.aprobado_at)}
                            </span>
                        )}
                    </Dato>
                    <div className="sm:col-span-2 lg:col-span-3">
                        <Dato titulo="Qué se paga">{vale.concepto}</Dato>
                    </div>
                    <div className="sm:col-span-2 lg:col-span-3">
                        <Dato titulo="Para qué">{vale.motivo}</Dato>
                    </div>
                    {vale.sustento.length > 0 && (
                        <div className="sm:col-span-2 lg:col-span-3">
                            <Dato titulo="Sustento">
                                <ul className="flex flex-col gap-1">
                                    {vale.sustento.map((archivo) => (
                                        <li key={archivo.id}>
                                            <a
                                                href={archivo.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1.5 text-primary underline-offset-4 hover:underline"
                                            >
                                                <Paperclip className="size-3.5" />
                                                {archivo.nombre}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </Dato>
                        </div>
                    )}
                </dl>
            </section>
        </div>
    );
}
