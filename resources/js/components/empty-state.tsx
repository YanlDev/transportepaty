import { cn } from '@/lib/utils';

type Props = {
    /** El ícono ya dimensionado, normalmente `size-7`. */
    icono: React.ReactNode;
    titulo: React.ReactNode;
    /** Qué hacer para que deje de estar vacío. */
    descripcion?: React.ReactNode;
    /** Botón opcional, solo cuando el usuario tiene permiso de crear. */
    accion?: React.ReactNode;
    /** `false` cuando el bloque no debe estirarse a lo alto de la página. */
    expandir?: boolean;
    className?: string;
};

/**
 * El bloque que ocupa el lugar de una lista vacía. Distingue dos motivos que
 * se ven igual pero no lo son: no hay nada cargado todavía, o los filtros no
 * dejaron pasar nada. Por eso `descripcion` es de quien llama, que es el
 * único que sabe cuál de los dos es.
 */
export function EmptyState({
    icono,
    titulo,
    descripcion,
    accion,
    expandir = true,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center',
                expandir && 'flex-1',
                className,
            )}
        >
            <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                {icono}
            </div>
            <p className="font-medium">{titulo}</p>
            {descripcion && (
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    {descripcion}
                </p>
            )}
            {accion && <div className="mt-6">{accion}</div>}
        </div>
    );
}
