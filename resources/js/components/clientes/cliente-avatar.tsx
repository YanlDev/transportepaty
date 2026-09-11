import { clienteColor } from '@/lib/cliente-color';
import { cn } from '@/lib/utils';

/**
 * El cuadrito con las iniciales, en el color que ya tiene ese cliente en el
 * resto de la app (los chips de viajes y las barras del tablero salen del
 * mismo `clienteColor`), así se lo reconoce sin leer el nombre.
 */
export function Avatar({ alias, cliente }: { alias: string; cliente: string }) {
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

function iniciales(alias: string): string {
    return alias
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((palabra) => palabra.charAt(0))
        .join('')
        .toUpperCase();
}
