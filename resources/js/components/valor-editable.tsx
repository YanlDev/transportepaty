import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { avisarError } from '@/lib/aviso-error';
import { cn } from '@/lib/utils';

type Tipo = 'texto' | 'numero' | 'fecha';

type Props = {
    /** A dónde se manda el `PATCH`. */
    url: string;
    /** El nombre del campo que se manda; también la columna que lo guarda. */
    campo: string;
    /** El valor guardado; null cuando la celda está vacía. */
    valor: string | number | null;
    /** Cómo se ve el valor guardado. Por defecto, el valor crudo. */
    children?: React.ReactNode;
    tipo?: Tipo;
    editable: boolean;
    placeholder?: string;
    className?: string;
    /** Ancho mínimo del input, para que nada salte al entrar en edición. */
    ancho?: string;
    /** Cómo nombrarlo en el lector de pantalla. Por defecto, el campo. */
    etiqueta?: string;
    /**
     * Abre la edición desde afuera. Sirve para ofrecer el mismo campo desde
     * otro lugar —un ítem de menú, por ejemplo— sin duplicar el formulario.
     * Sin esta prop el componente maneja su apertura solo, como siempre.
     */
    editando?: boolean;
    onEditandoChange?: (editando: boolean) => void;
};

/**
 * Un valor que se edita donde se lee: un clic lo abre, Enter o salir del campo
 * lo guarda, Escape lo deja como estaba.
 *
 * Nació como celda de la cobranza —de ahí el gesto de hoja de cálculo— y sirve
 * igual fuera de una tabla, como el vencimiento en la ficha de un documento.
 * Por eso recibe la URL en vez de conocer una ruta: manda solo su propio campo
 * (`PATCH` parcial) y nunca el registro entero, así dos valores del mismo
 * registro se editan uno tras otro sin que el segundo pise al primero.
 */
export function ValorEditable({
    url,
    campo,
    valor,
    children,
    tipo = 'texto',
    editable,
    placeholder = '—',
    className,
    ancho = 'min-w-24',
    etiqueta,
    editando: editandoControlado,
    onEditandoChange,
}: Props) {
    const [editandoInterno, setEditandoInterno] = useState(false);

    // Controlado si le pasan `editando`; si no, se maneja solo. Las dos ramas
    // avisan por `onEditandoChange`, así quien abre desde afuera puede cerrar
    // su propio estado cuando el campo se guarda o se cancela.
    const editando = editandoControlado ?? editandoInterno;

    const setEditando = (valor: boolean) => {
        if (editandoControlado === undefined) {
            setEditandoInterno(valor);
        }

        onEditandoChange?.(valor);
    };
    const [borrador, setBorrador] = useState('');
    const [guardando, setGuardando] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    // El valor guardado, espejado en una ref, para poder sembrar el borrador al
    // abrir sin que `valor` entre en las dependencias del efecto de apertura:
    // si entrara, un cambio de props a mitad de la edición pisaría lo que se
    // está tecleando. Se sincroniza en su propio efecto —nunca durante el
    // render— y va declarado antes para correr primero.
    const valorActual = useRef(valor);

    useEffect(() => {
        valorActual.current = valor;
    }, [valor]);

    // El borrador se siembra acá y no al hacer clic porque la edición también
    // se puede abrir desde afuera (`editando`), y por ese camino nunca pasa
    // por el clic: sin esto el campo abriría vacío.
    useEffect(() => {
        if (!editando) {
            return;
        }

        const guardado = valorActual.current;

        setBorrador(guardado === null ? '' : String(guardado));
        input.current?.focus();
        input.current?.select();
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

    const abrir = () => setEditando(true);

    const guardar = () => {
        setEditando(false);

        const limpio = borrador.trim();
        const nuevo = limpio === '' ? null : limpio;

        // Sin cambios no se manda nada: entrar y salir de un campo no debería
        // costar una visita al servidor ni marcar el registro como tocado.
        if (nuevo === (valor === null ? null : String(valor))) {
            return;
        }

        setGuardando(true);

        router.patch(
            url,
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
            aria-label={`Editar ${etiqueta ?? campo}`}
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
