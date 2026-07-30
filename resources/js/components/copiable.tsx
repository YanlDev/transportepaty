import { Check, Copy } from 'lucide-react';
import { useClipboard } from '@/hooks/use-clipboard';

type Props = {
    /** El texto que se copia. Si es null se dibuja un guion sin botón. */
    valor: string | null;
    /** Qué se copió, para el aviso accesible: «Copiar placa». */
    etiqueta: string;
    /** Contenido visible; por defecto, el propio valor. */
    children?: React.ReactNode;
    className?: string;
};

/**
 * Muestra un valor con un botón para copiarlo al portapapeles. El botón solo
 * aparece al pasar el mouse por la fila para no ensuciar la tabla, pero siempre
 * es alcanzable por teclado.
 *
 * Detiene la propagación del clic porque las filas de los listados navegan al
 * detalle: copiar no debe además cambiar de página.
 */
export function Copiable({ valor, etiqueta, children, className }: Props) {
    const [copiado, copiar] = useClipboard();

    if (valor === null || valor === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    const yaCopiado = copiado === valor;
    const Icono = yaCopiado ? Check : Copy;

    return (
        <span className={`inline-flex items-center gap-1.5 ${className ?? ''}`}>
            {children ?? valor}
            <button
                type="button"
                onClick={(evento) => {
                    evento.stopPropagation();
                    evento.preventDefault();
                    void copiar(valor);
                }}
                title={`${etiqueta}: ${valor}`}
                aria-label={
                    yaCopiado ? `${etiqueta} copiado` : `Copiar ${etiqueta}`
                }
                className={`grid size-8 shrink-0 place-items-center text-muted-foreground transition-opacity hover:text-foreground focus-visible:opacity-100 sm:size-auto sm:p-0.5 sm:opacity-0 sm:group-hover/fila:opacity-100 ${
                    yaCopiado ? 'text-emerald-600 sm:opacity-100' : ''
                }`}
            >
                <Icono className="size-3.5" />
            </button>
        </span>
    );
}
