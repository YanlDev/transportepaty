import { Link } from '@inertiajs/react';
import { MessageCircle, Pencil, Star } from 'lucide-react';
import { edit, show } from '@/actions/App/Http/Controllers/ClienteController';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { enlaceWhatsapp } from '@/lib/contacto';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ClienteListItem, Paginator } from '@/types/fleet';
import { Avatar } from './cliente-avatar';
import { BarraViajes } from './cliente-barra-viajes';

type Props = {
    paginador: Paginator<ClienteListItem>;
    /** Viajes del que más mueve: la escala común de todas las barras. */
    maxViajes: number;
    puedeEditar: boolean;
    /** El query actual, para volver al mismo filtro después de editar. */
    query: string;
};

/** El padrón de clientes en escritorio; en móvil se cae a `ClienteTarjeta`. */
export function TablaClientes({
    paginador,
    maxViajes,
    puedeEditar,
    query,
}: Props) {
    return (
        <div className="hidden overflow-x-auto rounded-xl border border-border bg-card lg:block">
            <Table>
                <TableHeader>
                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Cliente</TableHead>
                        <TableHead className="w-32">RUC</TableHead>
                        <TableHead className="w-52">Contacto</TableHead>
                        <TableHead className="w-32">Último viaje</TableHead>
                        <TableHead className="w-44">Viajes</TableHead>
                        {puedeEditar && <TableHead className="w-14" />}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {paginador.data.map((cliente) => {
                        const whatsapp = enlaceWhatsapp(cliente.telefono);

                        return (
                            <TableRow
                                key={cliente.id}
                                className={cn(
                                    'border-b transition-colors last:border-0 hover:bg-muted/40',
                                    !cliente.activo && 'opacity-55',
                                )}
                            >
                                <TableCell className="max-w-0">
                                    <div className="flex items-center gap-3">
                                        <Avatar
                                            alias={cliente.alias}
                                            cliente={cliente.razon_social}
                                        />

                                        <div className="min-w-0">
                                            <Link
                                                href={show(cliente.id)}
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
                                                title={cliente.razon_social}
                                            >
                                                {cliente.razon_social}
                                            </p>
                                        </div>
                                    </div>
                                </TableCell>

                                <TableCell className="font-mono text-xs text-muted-foreground">
                                    {cliente.ruc}
                                </TableCell>

                                <TableCell className="max-w-0">
                                    {cliente.contacto || cliente.telefono ? (
                                        <>
                                            <p className="truncate">
                                                {cliente.contacto ?? '—'}
                                            </p>
                                            {cliente.telefono && (
                                                <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <span className="tabular-nums">
                                                        {cliente.telefono}
                                                    </span>
                                                    {whatsapp && (
                                                        <a
                                                            href={whatsapp}
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
                                </TableCell>

                                <TableCell className="text-xs text-muted-foreground tabular-nums">
                                    {cliente.ultimo_viaje
                                        ? formatearFecha(cliente.ultimo_viaje)
                                        : '—'}
                                </TableCell>

                                <TableCell>
                                    <BarraViajes
                                        viajes={cliente.viajes_count}
                                        maximo={maxViajes}
                                        cliente={cliente.razon_social}
                                    />
                                </TableCell>

                                {puedeEditar && (
                                    <TableCell className="text-right">
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
                                    </TableCell>
                                )}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}
