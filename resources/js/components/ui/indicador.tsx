import { cn } from '@/lib/utils';

export type TonoIndicador = 'normal' | 'ambar' | 'rojo';

const tonoValor: Record<TonoIndicador, string> = {
    normal: '',
    ambar: 'text-amber-700 dark:text-amber-500',
    rojo: 'text-red-700 dark:text-red-500',
};

type Props = {
    icono: React.ReactNode;
    label: string;
    valor: React.ReactNode;
    /** La línea de abajo: contexto del número, no otro número. */
    pie?: React.ReactNode;
    /** Clases del chip del ícono, p. ej. `bg-blue-500/10 text-blue-600`. */
    color?: string;
    /** Tiñe el valor cuando el número en sí es una alerta. */
    tono?: TonoIndicador;
    /** `grande` es la escala del tablero; `normal`, la de las fichas. */
    tamano?: 'normal' | 'grande';
    className?: string;
};

/**
 * La tarjeta de un número de cabecera: ícono, etiqueta, valor y una línea de
 * contexto. Es la misma pieza en el tablero y en las fichas de cliente,
 * conductor y vehículo, que solo se diferencian en la escala.
 */
export function Indicador({
    icono,
    label,
    valor,
    pie,
    color = 'bg-muted text-muted-foreground',
    tono = 'normal',
    tamano = 'normal',
    className,
}: Props) {
    const grande = tamano === 'grande';

    return (
        <div
            className={cn(
                'flex items-start gap-3 rounded-xl border border-border bg-card p-4',
                // En el tablero van de a dos por fila en el celular: con el
                // ícono al costado quedaban ~70px de texto y se cortaba todo.
                // Ahí el ícono sube y el texto usa el ancho completo.
                grande && 'flex-col gap-2 p-3.5 sm:flex-row sm:gap-3 sm:p-5',
                className,
            )}
        >
            <span
                className={cn(
                    'grid size-10 shrink-0 place-items-center rounded-lg',
                    grande && 'size-9 sm:size-10',
                    color,
                )}
                aria-hidden
            >
                {icono}
            </span>
            <div className="w-full min-w-0">
                <p className="truncate text-xs text-muted-foreground">
                    {label}
                </p>
                <div
                    className={cn(
                        'truncate font-semibold tabular-nums',
                        grande ? 'text-2xl sm:text-3xl' : 'text-xl',
                        tonoValor[tono],
                    )}
                >
                    {valor}
                </div>
                {pie && (
                    <div className="line-clamp-2 text-xs text-muted-foreground">
                        {pie}
                    </div>
                )}
            </div>
        </div>
    );
}
