import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, MessageCircle, Pencil, Plus, Star } from 'lucide-react';
import { useEffect, useState } from 'react';
import clientes, {
    create,
    edit,
    show,
} from '@/actions/App/Http/Controllers/ClienteController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { StatusBadge } from '@/components/ui/status-badge';
import { clienteColor } from '@/lib/cliente-color';
import { enlaceWhatsapp } from '@/lib/contacto';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ClienteListItem, Paginator } from '@/types/fleet';

type Props = {
    clientes: Paginator<ClienteListItem>;
    filtros: { buscar: string };
    /** Viajes del cliente que más mueve: la escala de las barras. */
    maxViajes: number;
};

/** Las dos primeras iniciales del alias, para el cuadrito de color. */
function iniciales(alias: string): string {
    return alias
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((palabra) => palabra.charAt(0))
        .join('')
        .toUpperCase();
}

export default function ClientesIndex({
    clientes: paginador,
    filtros,
    maxViajes,
}: Props) {
    const { props, url } = usePage();
    const { auth } = props;
    const puedeGestionar = auth.roles.includes('admin');
    const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';

    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    useEffect(() => {
        if (buscar === (filtros.buscar ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                clientes.index().url,
                { buscar: buscar || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros.buscar]);

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Clientes" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Clientes
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'cliente en el padrón'
                            : 'clientes en el padrón'}
                    </p>
                </div>

                {puedeGestionar && (
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo cliente
                        </Link>
                    </Button>
                )}
            </div>

            <Input
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
                placeholder="Buscar por nombre, RUC o contacto..."
                className="h-11 max-w-sm md:h-9"
            />

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Building2 className="size-7" />
                    </div>
                    <p className="font-medium">No se encontraron clientes</p>
                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                        Ajusta la búsqueda
                        {puedeGestionar && ' o registra un cliente nuevo'}.
                    </p>
                </div>
            ) : (
                <>
                    <div className="flex flex-col gap-2 lg:hidden">
                        {paginador.data.map((cliente) => (
                            <ClienteTarjeta
                                key={cliente.id}
                                cliente={cliente}
                                maxViajes={maxViajes}
                            />
                        ))}
                    </div>

                    <div className="hidden overflow-hidden rounded-xl border border-border bg-card lg:block">
                        <table className="w-full table-fixed text-sm">
                            <thead>
                                <tr className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                    <th className="p-3 font-medium">Cliente</th>
                                    <th className="w-32 p-3 font-medium">
                                        RUC
                                    </th>
                                    <th className="w-52 p-3 font-medium">
                                        Contacto
                                    </th>
                                    <th className="w-32 p-3 font-medium">
                                        Último viaje
                                    </th>
                                    <th className="w-44 p-3 font-medium">
                                        Viajes
                                    </th>
                                    {puedeGestionar && (
                                        <th className="w-14 p-3" />
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {paginador.data.map((cliente) => {
                                    const whatsapp = enlaceWhatsapp(
                                        cliente.telefono,
                                    );

                                    return (
                                        <tr
                                            key={cliente.id}
                                            className={cn(
                                                'border-b transition-colors last:border-0 hover:bg-muted/40',
                                                !cliente.activo && 'opacity-55',
                                            )}
                                        >
                                            <td className="max-w-0 p-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar
                                                        alias={cliente.alias}
                                                        cliente={
                                                            cliente.razon_social
                                                        }
                                                    />

                                                    <div className="min-w-0">
                                                        <Link
                                                            href={show(
                                                                cliente.id,
                                                            )}
                                                            className="flex items-center gap-1.5 font-medium hover:underline"
                                                        >
                                                            <span className="truncate">
                                                                {cliente.alias}
                                                            </span>
                                                            {cliente.recurrente && (
                                                                <Star
                                                                    className="size-3.5 shrink-0 fill-amber-400 text-amber-500"
                                                                    aria-label="Cliente recurrente"
                                                                />
                                                            )}
                                                            {!cliente.activo && (
                                                                <StatusBadge
                                                                    label="Inactivo"
                                                                    tone="neutral"
                                                                    dot={false}
                                                                />
                                                            )}
                                                        </Link>
                                                        <p
                                                            className="truncate text-xs text-muted-foreground"
                                                            title={
                                                                cliente.razon_social
                                                            }
                                                        >
                                                            {
                                                                cliente.razon_social
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="p-3 font-mono text-xs text-muted-foreground">
                                                {cliente.ruc}
                                            </td>

                                            <td className="max-w-0 p-3">
                                                {cliente.contacto ||
                                                cliente.telefono ? (
                                                    <>
                                                        <p className="truncate">
                                                            {cliente.contacto ??
                                                                '—'}
                                                        </p>
                                                        {cliente.telefono && (
                                                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                                <span className="tabular-nums">
                                                                    {
                                                                        cliente.telefono
                                                                    }
                                                                </span>
                                                                {whatsapp && (
                                                                    <a
                                                                        href={
                                                                            whatsapp
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                                                                        aria-label={`Escribir por WhatsApp a ${cliente.alias}`}
                                                                        title="Escribir por WhatsApp"
                                                                    >
                                                                        <MessageCircle className="size-3.5" />
                                                                    </a>
                                                                )}
                                                            </span>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground/60 italic">
                                                        Sin datos de contacto
                                                    </span>
                                                )}
                                            </td>

                                            <td className="p-3 text-xs text-muted-foreground tabular-nums">
                                                {cliente.ultimo_viaje
                                                    ? formatearFecha(
                                                          cliente.ultimo_viaje,
                                                      )
                                                    : '—'}
                                            </td>

                                            <td className="p-3">
                                                <BarraViajes
                                                    viajes={
                                                        cliente.viajes_count
                                                    }
                                                    maximo={maxViajes}
                                                    cliente={
                                                        cliente.razon_social
                                                    }
                                                />
                                            </td>

                                            {puedeGestionar && (
                                                <td className="p-3 text-right">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-muted-foreground"
                                                    >
                                                        <Link
                                                            href={`${edit(cliente.id).url}${query}`}
                                                            aria-label={`Editar ${cliente.alias}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

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
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
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
                </>
            )}
        </div>
    );
}

/**
 * El cuadrito con las iniciales, en el color que ya tiene ese cliente en el
 * resto de la app (los chips de viajes y las barras del tablero salen del
 * mismo `clienteColor`), así se lo reconoce sin leer el nombre.
 */
function Avatar({ alias, cliente }: { alias: string; cliente: string }) {
    return (
        <span
            className={cn(
                'grid size-9 shrink-0 place-items-center rounded-lg text-[11px] font-bold',
                clienteColor(cliente).pill,
            )}
            aria-hidden
        >
            {iniciales(alias)}
        </span>
    );
}

/**
 * Cuántos viajes, y cuánto es eso comparado con el cliente que más mueve. El
 * número solo no dice nada cuando uno concentra el 60% del movimiento; la
 * barra sí.
 */
function BarraViajes({
    viajes,
    maximo,
    cliente,
}: {
    viajes: number;
    maximo: number;
    cliente: string;
}) {
    const porcentaje = maximo > 0 ? Math.max(2, (viajes / maximo) * 100) : 0;

    return (
        <div className="flex items-center gap-2.5">
            <span className="w-8 shrink-0 text-right font-semibold tabular-nums">
                {viajes}
            </span>
            <span className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                <span
                    className={cn(
                        'block h-full rounded-full',
                        clienteColor(cliente).punto,
                    )}
                    style={{ width: `${porcentaje}%` }}
                />
            </span>
        </div>
    );
}

function ClienteTarjeta({
    cliente,
    maxViajes,
}: {
    cliente: ClienteListItem;
    maxViajes: number;
}) {
    const whatsapp = enlaceWhatsapp(cliente.telefono);

    return (
        <div
            className={cn(
                'flex flex-col gap-2.5 rounded-lg border border-border bg-card p-3',
                !cliente.activo && 'opacity-55',
            )}
        >
            <div className="flex items-start gap-3">
                <Avatar alias={cliente.alias} cliente={cliente.razon_social} />

                <div className="min-w-0 flex-1">
                    <Link
                        href={show(cliente.id)}
                        className="flex items-center gap-1.5 font-medium hover:underline"
                    >
                        <span className="truncate">{cliente.alias}</span>
                        {cliente.recurrente && (
                            <Star
                                className="size-3.5 shrink-0 fill-amber-400 text-amber-500"
                                aria-label="Cliente recurrente"
                            />
                        )}
                    </Link>
                    <p className="truncate text-xs text-muted-foreground">
                        RUC {cliente.ruc}
                    </p>
                </div>

                {whatsapp && (
                    <a
                        href={whatsapp}
                        target="_blank"
                        rel="noreferrer"
                        className="grid size-9 shrink-0 place-items-center rounded-md text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400"
                        aria-label={`Escribir por WhatsApp a ${cliente.alias}`}
                    >
                        <MessageCircle className="size-4" />
                    </a>
                )}
            </div>

            <BarraViajes
                viajes={cliente.viajes_count}
                maximo={maxViajes}
                cliente={cliente.razon_social}
            />
        </div>
    );
}

ClientesIndex.layout = {
    breadcrumbs: [{ title: 'Clientes', href: clientes.index().url }],
};
