import { Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { ChevronRight, CircleCheck, TriangleAlert, Users } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/MisOrdenesController';
import { EstadoOrden } from '@/components/ordenes/estado-orden';
import { Placa } from '@/components/placa';
import { formatearFecha } from '@/lib/format';
import type { OrdenDeMecanico } from '@/types/mantenimiento';

type Props = {
    /** Observadas, en proceso y por empezar, en ese orden. */
    por_hacer: OrdenDeMecanico[];
    /** En manos de operaciones: requerimiento por aprobar o trabajo por validar. */
    en_revision: OrdenDeMecanico[];
    /** Finalizadas en los últimos días. */
    terminadas: OrdenDeMecanico[];
};

/**
 * La pantalla del mecánico: las órdenes que le asignaron y nada más.
 * Abre con lo que hay que hacer —primero lo que le devolvieron—, y cada
 * tarjeta es un botón grande que lleva a la orden.
 */
export default function MisOrdenes({
    por_hacer,
    en_revision,
    terminadas,
}: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Mis órdenes', href: '#' }],
    });

    // Operaciones asigna y aprueba mientras el mecánico trabaja: la lista se
    // refresca sola.
    usePoll(30_000);

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Mis órdenes" />

            <div>
                <h1 className="text-xl font-semibold">Mis órdenes</h1>
                <p className="text-sm text-muted-foreground">
                    {por_hacer.length === 0
                        ? 'No tienes trabajos por hacer.'
                        : por_hacer.length === 1
                          ? 'Tienes 1 trabajo por hacer.'
                          : `Tienes ${por_hacer.length} trabajos por hacer.`}
                </p>
            </div>

            <Grupo titulo="Por hacer" ordenes={por_hacer}>
                <p className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    <CircleCheck className="size-8 text-emerald-600" />
                    Estás al día. Cuando te asignen una orden, aparece acá.
                </p>
            </Grupo>

            {en_revision.length > 0 && (
                <Grupo titulo="Esperando a operaciones" ordenes={en_revision} />
            )}

            {terminadas.length > 0 && (
                <Grupo titulo="Finalizadas" ordenes={terminadas} />
            )}
        </div>
    );
}

function Grupo({
    titulo,
    ordenes,
    children,
}: {
    titulo: string;
    ordenes: OrdenDeMecanico[];
    /** Lo que se muestra cuando no hay ninguna. */
    children?: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-2">
            <h2 className="flex items-center gap-2 text-sm font-semibold">
                {titulo}
                <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground tabular-nums">
                    {ordenes.length}
                </span>
            </h2>
            {ordenes.length === 0 ? (
                children
            ) : (
                <ul className="grid gap-3">
                    {ordenes.map((orden) => (
                        <li key={orden.id}>
                            <Tarjeta orden={orden} />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function Tarjeta({ orden }: { orden: OrdenDeMecanico }) {
    return (
        <Link
            href={show(orden.id)}
            className="flex flex-col gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-accent/50 active:bg-accent"
        >
            <div className="flex items-center gap-3">
                <Placa placa={orden.placa} tamano="lg" />
                <div className="flex min-w-0 flex-1 flex-col">
                    <span className="text-sm font-medium">
                        {orden.numero} · {orden.tipo}
                        {orden.servicio && ` ${orden.servicio}`}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {orden.tipo_unidad}
                        {orden.marca && ` ${orden.marca}`} ·{' '}
                        {formatearFecha(orden.fecha)}
                    </span>
                </div>
                <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
            </div>

            <p className="line-clamp-3 text-sm whitespace-pre-line">
                {orden.trabajo}
            </p>

            {orden.observacion_revision && (
                <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2.5 text-sm">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                    <span>
                        <span className="font-medium">
                            Operaciones observó:{' '}
                        </span>
                        {orden.observacion_revision}
                    </span>
                </p>
            )}

            {orden.devolucion_requerimiento && (
                <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2.5 text-sm">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                    <span>
                        <span className="font-medium">
                            Requerimiento devuelto:{' '}
                        </span>
                        {orden.devolucion_requerimiento}
                    </span>
                </p>
            )}

            {orden.estado === 'solicitada' && (
                <p className="text-sm font-medium text-violet-700 dark:text-violet-300">
                    Arma el requerimiento y envíalo a operaciones.
                </p>
            )}

            <div className="flex flex-wrap items-center gap-2 text-xs">
                <EstadoOrden
                    estado={orden.estado}
                    etiqueta={orden.estado_label}
                />
                {orden.urgente && (
                    <span className="rounded-full bg-red-600 px-2 py-0.5 font-semibold text-white">
                        Urgente
                    </span>
                )}
                {orden.tareas_total > 0 && (
                    <span className="text-muted-foreground tabular-nums">
                        {orden.tareas_hechas} de {orden.tareas_total} tareas
                    </span>
                )}
                {orden.companeros.length > 0 && (
                    <span className="flex items-center gap-1 text-muted-foreground">
                        <Users className="size-3.5" />
                        Con {orden.companeros.join(', ')}
                    </span>
                )}
            </div>
        </Link>
    );
}
