import { MessageCircle } from 'lucide-react';
import { StatusBadge } from '@/components/ui/status-badge';
import { enlaceWhatsapp } from '@/lib/contacto';
import type { Cliente } from '@/types/fleet';

/** Los datos de registro del cliente: lo que se copia para armar papeles. */
export function ClienteFicha({ cliente }: { cliente: Cliente }) {
    const whatsapp = enlaceWhatsapp(cliente.telefono);

    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="text-sm font-semibold">
                    Información del cliente
                </h2>
            </div>

            <dl className="divide-y">
                <Dato label="Razón social">{cliente.razon_social}</Dato>
                <Dato label="RUC">
                    <span className="font-mono">{cliente.ruc}</span>
                </Dato>
                <Dato label="Nombre comercial">
                    {cliente.nombre_comercial ?? '—'}
                </Dato>
                <Dato label="Dirección">{cliente.direccion ?? '—'}</Dato>
                <Dato label="Contacto">{cliente.contacto ?? '—'}</Dato>
                <Dato label="Teléfono">
                    {cliente.telefono ? (
                        <span className="flex items-center justify-end gap-2">
                            <a
                                href={`tel:${cliente.telefono}`}
                                className="tabular-nums hover:underline"
                            >
                                {cliente.telefono}
                            </a>
                            {whatsapp && (
                                <a
                                    href={whatsapp}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="grid size-7 shrink-0 place-items-center rounded-md text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400"
                                    aria-label={`Escribir por WhatsApp a ${cliente.alias}`}
                                    title="Escribir por WhatsApp"
                                >
                                    <MessageCircle className="size-4" />
                                </a>
                            )}
                        </span>
                    ) : (
                        '—'
                    )}
                </Dato>
                <Dato label="Correo">{cliente.email ?? '—'}</Dato>
                <Dato label="Estado">
                    <StatusBadge
                        label={cliente.activo ? 'Activo' : 'Inactivo'}
                        tone={cliente.activo ? 'success' : 'neutral'}
                    />
                </Dato>
                {/* Las observaciones van a lo ancho: son texto libre y no
                    entran en la columna angosta de la derecha. */}
                {cliente.notas && (
                    <div className="p-3">
                        <dt className="text-xs text-muted-foreground">
                            Observaciones
                        </dt>
                        <dd className="mt-1 text-sm whitespace-pre-line">
                            {cliente.notas}
                        </dd>
                    </div>
                )}
            </dl>
        </section>
    );
}

function Dato({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-3 p-3 text-sm">
            <dt className="shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0 truncate text-right">{children}</dd>
        </div>
    );
}
