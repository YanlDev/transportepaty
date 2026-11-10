import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Tono = 'mal' | 'aviso' | 'bien' | 'neutro';

const colorCuenta: Record<Tono, string> = {
    mal: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    aviso: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    bien: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    neutro: 'bg-muted text-muted-foreground',
};

/**
 * Un grupo de unidades dentro de un listado de taller: las que necesitan
 * algo van abiertas y arriba; las que están bien, o de las que todavía no
 * hay datos, quedan plegadas con su número a la vista.
 *
 * Así un listado de 123 unidades abre mostrando las cinco que hay que
 * atender, no doce pantallas de filas iguales. Un grupo vacío no se dibuja.
 */
export function GrupoDeUnidades({
    titulo,
    cantidad,
    tono = 'neutro',
    ayuda,
    abiertoAlInicio = false,
    forzarAbierto = false,
    children,
}: {
    titulo: string;
    cantidad: number;
    tono?: Tono;
    /** Qué significa el grupo o qué hacer con él, en una línea. */
    ayuda?: string;
    abiertoAlInicio?: boolean;
    /** Mientras se busca una placa todos los grupos se muestran. */
    forzarAbierto?: boolean;
    children: ReactNode;
}) {
    const [abierto, setAbierto] = useState(abiertoAlInicio);
    const visible = abierto || forzarAbierto;

    if (cantidad === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            <button
                type="button"
                onClick={() => setAbierto(!abierto)}
                aria-expanded={visible}
                disabled={forzarAbierto}
                className="flex min-h-11 items-center gap-2 rounded-lg text-left"
            >
                <h2 className="text-sm font-semibold">{titulo}</h2>
                <span
                    className={cn(
                        'rounded-full px-2 py-0.5 text-xs tabular-nums',
                        colorCuenta[tono],
                    )}
                >
                    {cantidad}
                </span>
                {ayuda && (
                    <span className="hidden flex-1 truncate text-xs text-muted-foreground sm:inline">
                        {ayuda}
                    </span>
                )}
                {!forzarAbierto && (
                    <ChevronDown
                        className={cn(
                            'ml-auto size-4 shrink-0 text-muted-foreground transition-transform',
                            visible && 'rotate-180',
                        )}
                    />
                )}
            </button>
            {ayuda && visible && (
                <p className="-mt-2 text-xs text-muted-foreground sm:hidden">
                    {ayuda}
                </p>
            )}
            {visible && children}
        </section>
    );
}
