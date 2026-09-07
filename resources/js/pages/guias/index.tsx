import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, RefreshCw, Send } from 'lucide-react';
import {
    create,
    reintentar,
} from '@/actions/App/Http/Controllers/GuiaController';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import type { StatusTone } from '@/components/ui/status-badge';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { Paginator } from '@/types/fleet';

type GuiaEmitida = {
    id: number;
    numero_gr: string;
    fecha_traslado: string;
    cliente: string;
    destinatario: string;
    partida: string;
    llegada: string;
    placa_tracto: string;
    estado: string;
    estado_label: string;
    ticket: string | null;
    mensaje: string | null;
    reintentable: boolean;
};

type Props = {
    guias: Paginator<GuiaEmitida>;
    /** Sin credenciales del Menú SOL la guía se firma pero no se envía. */
    credencialesConfiguradas: boolean;
};

const tonos: Record<string, StatusTone> = {
    generada: 'warning',
    enviada: 'warning',
    aceptada: 'success',
    rechazada: 'danger',
    anulada: 'neutral',
};

export default function GuiasIndex({
    guias: paginador,
    credencialesConfiguradas,
}: Props) {
    const { auth } = usePage().props;
    const puedeEmitir = auth.roles.includes('admin');

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Guías de transportista" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Guías de transportista
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'guía emitida desde el sistema'
                            : 'guías emitidas desde el sistema'}
                    </p>
                </div>

                {puedeEmitir && (
                    <Button asChild size="lg">
                        <Link href={create()}>
                            <Send className="size-4" />
                            Emitir guía
                        </Link>
                    </Button>
                )}
            </div>

            {!credencialesConfiguradas && (
                <p className="rounded-lg border border-amber-500/40 bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    Faltan las credenciales del Menú SOL. Las guías se generan y
                    se firman con el certificado digital, pero no se envían a
                    SUNAT hasta cargar <code>GRE_CLIENT_ID</code> y{' '}
                    <code>GRE_CLIENT_SECRET</code>.
                </p>
            )}

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <FileText className="size-7" />
                    </div>
                    <p className="font-medium">Todavía no emitiste ninguna</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Acá aparecen las guías emitidas desde el sistema, con su
                        estado ante SUNAT. Las que vienen de PDF importado viven
                        en Viajes.
                    </p>
                    {puedeEmitir && (
                        <Button asChild className="mt-4">
                            <Link href={create()}>
                                <Send className="size-4" />
                                Emitir la primera
                            </Link>
                        </Button>
                    )}
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <th className="p-3 font-medium">Número</th>
                                <th className="p-3 font-medium">Fecha</th>
                                <th className="p-3 font-medium">Cliente</th>
                                <th className="p-3 font-medium">Ruta</th>
                                <th className="p-3 font-medium">Unidad</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="w-28 p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {paginador.data.map((guia) => (
                                <tr
                                    key={guia.id}
                                    className="border-b transition-colors last:border-0 hover:bg-muted/40"
                                >
                                    <td className="p-3 font-mono font-medium tabular-nums">
                                        {guia.numero_gr}
                                    </td>
                                    <td className="p-3 whitespace-nowrap">
                                        {formatearFecha(guia.fecha_traslado)}
                                    </td>
                                    <td className="max-w-0 p-3">
                                        <p className="truncate">
                                            {guia.cliente}
                                        </p>
                                    </td>
                                    <td className="max-w-0 p-3 text-xs text-muted-foreground">
                                        <p className="truncate">
                                            {guia.partida} → {guia.llegada}
                                        </p>
                                    </td>
                                    <td className="p-3 tabular-nums">
                                        {formatearPlaca(guia.placa_tracto)}
                                    </td>
                                    <td className="p-3">
                                        <StatusBadge
                                            label={guia.estado_label}
                                            tone={
                                                tonos[guia.estado] ?? 'neutral'
                                            }
                                        />
                                        {guia.mensaje && (
                                            <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                                {guia.mensaje}
                                            </p>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {puedeEmitir && guia.reintentable && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        reintentar(guia.id).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <RefreshCw className="size-3.5" />
                                                Reintentar
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {paginador.last_page > 1 && (
                <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
                    <p className="text-sm text-muted-foreground">
                        Mostrando {paginador.from}–{paginador.to} de{' '}
                        {paginador.total}
                    </p>
                    <div className="flex flex-wrap gap-1">
                        {paginador.links.map((link, indice) => (
                            <Button
                                key={indice}
                                asChild={!!link.url}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                            >
                                {link.url ? (
                                    <Link
                                        href={link.url}
                                        preserveScroll
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                )}
                            </Button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
