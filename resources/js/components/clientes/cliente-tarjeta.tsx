import { Link } from '@inertiajs/react';
import { MessageCircle, Star } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/ClienteController';
import { enlaceWhatsapp } from '@/lib/contacto';
import { cn } from '@/lib/utils';
import type { ClienteListItem } from '@/types/fleet';
import { Avatar } from './cliente-avatar';
import { BarraViajes } from './cliente-barra-viajes';

export function ClienteTarjeta({
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
