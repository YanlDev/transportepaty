import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, ClipboardPlus, Search, Users } from 'lucide-react';
import { useState } from 'react';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/OrdenTrabajoController';
import { EstadoOrden } from '@/components/ordenes/estado-orden';
import { Placa } from '@/components/placa';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption, Paginator } from '@/types/fleet';

type Orden = {
    id: number;
    numero: string;
    vehiculo_id: number;
    placa: string;
    tipo_unidad: string;
    tipo: string;
    servicio: string | null;
    estado: string;
    estado_label: string;
    urgente: boolean;
    fecha: string;
    trabajo: string;
    mecanicos: string[];
    solicitante: string | null;
    fotos: number;
};

type Props = {
    ordenes: Paginator<Orden>;
    filtros: { estado: string; mecanico: number | null; q: string };
    estados: EnumOption[];
    mecanicos: { id: number; nombre: string }[];
    por_atender: { por_revisar: number; solicitadas: number };
    puede: { editar: boolean };
};

/**
 * Todas las órdenes de trabajo. Abre con lo que espera a taller: lo que los
 * mecánicos terminaron (por revisar) y lo que operaciones pidió (solicitudes).
 */
export default function Ordenes({
    ordenes,
    filtros,
    estados,
    mecanicos,
    por_atender,
    puede,
}: Props) {
    const [busqueda, setBusqueda] = useState(filtros.q);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Órdenes de trabajo', href: '#' },
        ],
    });

    const filtrar = (cambios: Partial<Props['filtros']>) => {
        const nuevos = { ...filtros, q: busqueda, ...cambios };

        router.get(
            index().url,
            {
                estado:
                    nuevos.estado === 'abiertas' ? undefined : nuevos.estado,
                mecanico: nuevos.mecanico ?? undefined,
                q: nuevos.q || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Órdenes de trabajo" />

            <div className="flex flex-wrap items-center justify-between gap-2">
                <h1 className="text-xl font-semibold">Órdenes de trabajo</h1>
                {puede.editar && (
                    <Button asChild>
                        <Link href={create()}>
                            <ClipboardPlus className="size-4" />
                            Nueva orden
                        </Link>
                    </Button>
                )}
            </div>

            {(por_atender.por_revisar > 0 || por_atender.solicitadas > 0) && (
                <div className="grid gap-2 sm:grid-cols-2">
                    {por_atender.por_revisar > 0 && (
                        <Atajo
                            cantidad={por_atender.por_revisar}
                            titulo="Por revisar"
                            detalle="El mecánico terminó: revisa las fotos y finaliza u observa."
                            activo={filtros.estado === 'por_revisar'}
                            onClick={() => filtrar({ estado: 'por_revisar' })}
                        />
                    )}
                    {por_atender.solicitadas > 0 && (
                        <Atajo
                            cantidad={por_atender.solicitadas}
                            titulo="Solicitudes"
                            detalle="Pedidos de operaciones: acéptalos y asigna mecánico, o recházalos."
                            activo={filtros.estado === 'solicitada'}
                            onClick={() => filtrar({ estado: 'solicitada' })}
                        />
                    )}
                </div>
            )}

            <div className="flex flex-col gap-2">
                <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0">
                    {estados.map((estado) => (
                        <button
                            key={estado.value}
                            type="button"
                            onClick={() => filtrar({ estado: estado.value })}
                            aria-pressed={filtros.estado === estado.value}
                            className={cn(
                                'min-h-10 shrink-0 rounded-full border px-4 text-sm font-medium transition-colors',
                                filtros.estado === estado.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-card hover:bg-accent/50',
                            )}
                        >
                            {estado.label}
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap gap-2">
                    <form
                        className="relative min-w-48 flex-1 sm:max-w-xs"
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            filtrar({});
                        }}
                    >
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={busqueda}
                            onChange={(evento) =>
                                setBusqueda(evento.target.value)
                            }
                            placeholder="Placa"
                            className="h-10 pl-8"
                            aria-label="Buscar por placa"
                        />
                    </form>
                    {mecanicos.length > 0 && (
                        <select
                            value={filtros.mecanico ?? ''}
                            onChange={(evento) =>
                                filtrar({
                                    mecanico:
                                        evento.target.value === ''
                                            ? null
                                            : Number(evento.target.value),
                                })
                            }
                            aria-label="Mecánico"
                            className="h-10 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Todos los mecánicos</option>
                            {mecanicos.map((mecanico) => (
                                <option key={mecanico.id} value={mecanico.id}>
                                    {mecanico.nombre}
                                </option>
                            ))}
                        </select>
                    )}
                </div>
            </div>

            {ordenes.data.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No hay órdenes con ese filtro.
                </p>
            ) : (
                <ul className="grid gap-2 lg:grid-cols-2">
                    {ordenes.data.map((orden) => (
                        <li key={orden.id}>
                            <Fila orden={orden} editable={puede.editar} />
                        </li>
                    ))}
                </ul>
            )}

            <Paginacion paginador={ordenes} />
        </div>
    );
}

function Fila({ orden, editable }: { orden: Orden; editable: boolean }) {
    const anulada = orden.estado === 'anulada';
    const contenido = (
        <>
            <Placa placa={orden.placa} />
            <span className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium">
                    {orden.numero} · {orden.tipo}
                    {orden.servicio && ` ${orden.servicio}`}
                    <EstadoOrden
                        estado={orden.estado}
                        etiqueta={orden.estado_label}
                    />
                    {orden.urgente && !anulada && (
                        <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">
                            Urgente
                        </span>
                    )}
                </span>
                <span className="line-clamp-2 text-muted-foreground">
                    {orden.trabajo}
                </span>
                <span className="flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                    <span>
                        {orden.tipo_unidad} · {formatearFecha(orden.fecha)}
                    </span>
                    <span className="flex items-center gap-1">
                        <Users className="size-3.5" />
                        {orden.mecanicos.length > 0
                            ? orden.mecanicos.join(', ')
                            : 'Sin mecánico'}
                    </span>
                    {orden.solicitante && (
                        <span>Pidió: {orden.solicitante}</span>
                    )}
                    <span className="tabular-nums">
                        {orden.fotos === 1 ? '1 foto' : `${orden.fotos} fotos`}
                    </span>
                </span>
            </span>
        </>
    );

    const clase =
        'flex min-h-20 items-center gap-3 rounded-xl border bg-card p-3';

    // Una anulada ya no se edita: se ve en la ficha de su unidad.
    if (!editable || anulada) {
        return (
            <Link
                href={mantenimiento.show(orden.vehiculo_id)}
                className={cn(clase, anulada && 'opacity-60')}
            >
                {contenido}
            </Link>
        );
    }

    return (
        <Link
            href={edit(orden.id)}
            className={cn(clase, 'transition-colors hover:bg-accent/50')}
        >
            {contenido}
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
        </Link>
    );
}

function Atajo({
    cantidad,
    titulo,
    detalle,
    activo,
    onClick,
}: {
    cantidad: number;
    titulo: string;
    detalle: string;
    activo: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex items-center gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-left transition-colors hover:bg-amber-500/20',
                activo && 'ring-2 ring-amber-500',
            )}
        >
            <span className="text-3xl font-semibold tabular-nums">
                {cantidad}
            </span>
            <span className="flex flex-col text-sm">
                <span className="font-semibold">{titulo}</span>
                <span className="text-xs text-muted-foreground">{detalle}</span>
            </span>
        </button>
    );
}
