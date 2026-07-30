import { cn } from '@/lib/utils';
import type { OrigenDato } from '@/types/fleet';

type Props = {
    valor: string | null;
    origen?: OrigenDato;
    className?: string;
};

/**
 * Un valor del estado diario, atenuado cuando el sistema lo supuso y en firme
 * cuando alguien lo confirmó. Que se distinga a simple vista es lo que evita
 * tratar una deducción como si fuera un dato comprobado.
 */
export function ValorCelda({ valor, origen, className }: Props) {
    if (valor === null || valor === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    const esSupuesto = origen === 'deducido';

    return (
        <span
            className={cn(
                esSupuesto && 'text-muted-foreground italic',
                origen === 'manual' && 'font-medium',
                className,
            )}
            title={
                esSupuesto
                    ? 'Deducido por el sistema; confírmalo si es correcto'
                    : origen === 'manual'
                      ? 'Confirmado a mano'
                      : undefined
            }
        >
            {valor}
        </span>
    );
}
