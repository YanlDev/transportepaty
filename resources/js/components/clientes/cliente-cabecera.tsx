import { Link } from '@inertiajs/react';
import { MessageCircle, Pencil, Star } from 'lucide-react';
import { edit } from '@/actions/App/Http/Controllers/ClienteController';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { enlaceWhatsapp } from '@/lib/contacto';
import type { Cliente } from '@/types/fleet';

/** Quién es el cliente y las dos acciones que se hacen desde su ficha. */
export function ClienteCabecera({
    cliente,
    puedeEditar,
}: {
    cliente: Cliente;
    puedeEditar: boolean;
}) {
    const whatsapp = enlaceWhatsapp(cliente.telefono);

    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {cliente.razon_social}
                    </h1>
                    <StatusBadge
                        label={cliente.activo ? 'Activo' : 'Inactivo'}
                        tone={cliente.activo ? 'success' : 'neutral'}
                    />
                    {/* No usa `StatusBadge`: «recurrente» no es un estado sino
                        un atributo, y lleva estrella en vez de punto. */}
                    {cliente.recurrente && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">
                            <Star className="size-3" />
                            Recurrente
                        </span>
                    )}
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                    <span className="font-mono">RUC {cliente.ruc}</span>
                    {cliente.nombre_comercial && (
                        <span> · {cliente.nombre_comercial}</span>
                    )}
                </p>
            </div>

            <div className="flex items-center gap-2">
                {whatsapp && (
                    <Button asChild variant="outline" size="sm">
                        <a
                            href={whatsapp}
                            target="_blank"
                            rel="noreferrer"
                            className="text-emerald-700 dark:text-emerald-400"
                        >
                            <MessageCircle className="size-4" />
                            WhatsApp
                        </a>
                    </Button>
                )}
                {puedeEditar && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={edit(cliente.id)}>
                            <Pencil className="size-4" />
                            Editar
                        </Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
