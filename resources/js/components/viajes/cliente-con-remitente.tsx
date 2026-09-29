import { ClienteChip } from '@/components/viajes/cliente-chip';

/**
 * El cliente —a quien se cobra— y, debajo, el remitente de la GR cuando es
 * otro: Crisar arriba y «Ajeper» abajo. El backend manda `remitente` solo si
 * difiere del cliente, así que en la mayoría de las filas no agrega nada.
 */
export function ClienteConRemitente({
    cliente,
    remitente,
}: {
    cliente: string;
    remitente: string | null;
}) {
    return (
        <span className="flex min-w-0 flex-col items-start gap-0.5">
            <ClienteChip cliente={cliente} />
            {remitente && (
                <span
                    className="max-w-full truncate text-xs text-muted-foreground"
                    title={`Remitente de la GR: ${remitente}`}
                >
                    rem. {remitente}
                </span>
            )}
        </span>
    );
}
