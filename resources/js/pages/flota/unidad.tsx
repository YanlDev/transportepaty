import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import flota from '@/actions/App/Http/Controllers/FlotaController';
import { Badge } from '@/components/ui/badge';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { PasoLineaTiempo } from '@/types/fleet';

type Props = {
    tracto: { id: number; placa: string };
    linea: PasoLineaTiempo[];
};

export default function FlotaUnidad({ tracto, linea }: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Historial ${tracto.placa}`} />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {formatearPlaca(tracto.placa)}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {linea.length}{' '}
                    {linea.length === 1 ? 'día registrado' : 'días registrados'}
                    , del más reciente al más antiguo
                </p>
            </div>

            {linea.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center bg-muted text-muted-foreground">
                        <History className="size-7" />
                    </div>
                    <p className="font-medium">
                        Esta unidad todavía no tiene historial
                    </p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Aparecerá aquí en cuanto se registre su estado en algún
                        día.
                    </p>
                </div>
            ) : (
                <ol className="relative flex flex-col gap-0 border-l pl-6">
                    {linea.map((paso) => (
                        <li key={paso.id} className="relative pb-6 last:pb-0">
                            <span
                                aria-hidden
                                className="absolute top-1.5 -left-[1.8125rem] size-3 rounded-full border-2 border-background bg-primary"
                            />

                            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <time className="font-medium">
                                    {formatearFecha(paso.fecha)}
                                </time>
                                {paso.ubicacion && (
                                    <span className="text-sm">
                                        {paso.ubicacion}
                                    </span>
                                )}
                                {paso.fase_label && (
                                    <Badge variant="outline">
                                        {paso.fase_label}
                                    </Badge>
                                )}
                            </div>

                            <div className="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-muted-foreground">
                                {paso.tipo_carga_label && (
                                    <span>{paso.tipo_carga_label}</span>
                                )}
                                {paso.ruta && (
                                    <span>
                                        {paso.ruta.origen ?? '—'}
                                        {' ⇒ '}
                                        {paso.ruta.destino ?? '—'}
                                    </span>
                                )}
                                {paso.conductor && (
                                    <span>{paso.conductor}</span>
                                )}
                                {paso.carreta && (
                                    <span>
                                        Carreta {formatearPlaca(paso.carreta)}
                                    </span>
                                )}
                            </div>

                            {paso.observaciones && (
                                <p className="mt-1 text-sm">
                                    {paso.observaciones}
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

FlotaUnidad.layout = {
    breadcrumbs: [{ title: 'Flota', href: flota.index().url }],
};
