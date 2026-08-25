import { clienteColor } from '@/lib/cliente-color';
import { cn } from '@/lib/utils';

/**
 * Píldora de color por cliente — el color sale de un hash del nombre
 * (`clienteColor`), no de un catálogo, así que funciona para cualquier
 * cliente sin mantenimiento. Trunca nombres largos; el nombre completo va en
 * el `title` nativo.
 */
export function ClienteChip({
    cliente,
    className,
}: {
    cliente: string;
    className?: string;
}) {
    const { pill, punto } = clienteColor(cliente);

    return (
        <span
            title={cliente}
            className={cn(
                'inline-flex max-w-full items-center gap-1.5 rounded-full py-0.5 pr-2.5 pl-2 text-xs font-semibold',
                pill,
                className,
            )}
        >
            <span className={cn('size-1.5 shrink-0 rounded-full', punto)} />
            <span className="truncate">{cliente}</span>
        </span>
    );
}
