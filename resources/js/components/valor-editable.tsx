import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/FacturaController';
import { avisarError } from '@/lib/aviso-error';
import { cn } from '@/lib/utils';

type Tipo = 'texto' | 'numero' | 'fecha';

type Props = {
    facturaId: number;
    /** La columna de `facturas` que guarda esta celda. */
    campo: string;
    /** El valor guardado; null cuando la celda está vacía. */
    valor: string | number | null;
    /** Cómo se ve el valor guardado. Por defecto, el valor crudo. */
    children?: React.ReactNode;
    tipo?: Tipo;
    editable: boolean;
    placeholder?: string;
    className?: string;
    /** Ancho mínimo del input, para que la celda no salte al entrar en edición. */
    ancho?: string;
};

/**
 * Una celda de la cobranza que se edita en el sitio, como en la hoja de
 * cálculo de la que viene: un clic la abre, Enter o salir del campo la guarda,
 * Escape la deja como estaba.
 *
 * Manda solo su propio campo (`PATCH` parcial) y no la fila entera: dos celdas
 * de la misma factura pueden editarse una tras otra sin que la segunda pise lo
 * que guardó la primera.
 */
export function CeldaEditable({
    facturaId,
    campo,
    valor,
    children,
    tipo = 'texto',
    editable,
    placeholder = '—',
    className,
    ancho = 'min-w-24',
}: Props) {
    const [editando, setEditando] = useState(false);
    const [borrador, setBorrador] = useState('');
    const [guardando, setGuardando] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editando) {
            input.current?.focus();
            input.current?.select();
        }
    }, [editando]);

    const mostrado =
        children ?? (valor === null || valor === '' ? null : valor);

    if (!editable) {
        return (
            <span className={className}>
                {mostrado ?? <Vacio placeholder={placeholder} />}
            </span>
        );
    }

    const abrir = () => {
        setBorrador(valor === null ? '' : String(valor));
        setEditando(true);
    };

    const guardar = () => {
        setEditando(false);

        const limpio = borrador.trim();
        const nuevo = limpio === '' ? null : limpio;

        // Sin cambios no se manda nada: entrar y salir de una celda no debería
        // costar una visita al servidor ni marcar la factura como tocada.
        if (nuevo === (valor === null ? null : String(valor))) {
            return;
        }

        setGuardando(true);

        router.patch(
            update(facturaId).url,
            { [campo]: nuevo },
            {
                preserveScroll: true,
                preserveState: true,
                onError: avisarError,
                onFinish: () => setGuardando(false),
            },
        );
    };

    if (editando) {
        return (
            <input
                ref={input}
                type={
                    tipo === 'fecha'
                        ? 'date'
                        : tipo === 'numero'
                          ? 'number'
                          : 'text'
                }
                step={tipo === 'numero' ? '0.01' : undefined}
                min={tipo === 'numero' ? '0' : undefined}
                value={borrador}
                onChange={(evento) => setBorrador(evento.target.value)}
                onBlur={guardar}
                onKeyDown={(evento) => {
                    if (evento.key === 'Enter') {
                        evento.currentTarget.blur();
                    }

                    if (evento.key === 'Escape') {
                        setEditando(false);
                    }
                }}
                className={cn(
                    'w-full rounded-sm border border-primary bg-background px-1 py-0.5 text-sm outline-none',
                    ancho,
                    className,
                )}
            />
        );
    }

    return (
        <button
            type="button"
            onClick={abrir}
            aria-label={`Editar ${campo}`}
            className={cn(
                'w-full rounded-sm px-1 py-0.5 text-left hover:bg-accent',
                guardando && 'animate-pulse opacity-60',
                className,
            )}
        >
            {mostrado ?? <Vacio placeholder={placeholder} />}
        </button>
    );
}

function Vacio({ placeholder }: { placeholder: string }) {
    return <span className="text-muted-foreground/40">{placeholder}</span>;
}
