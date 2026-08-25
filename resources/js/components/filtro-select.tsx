import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EnumOption } from '@/types/fleet';

const TODOS = 'todos';

/**
 * Un selector de filtro con opción "todos" — usado por `VehiculoFiltros` y
 * `viajes/index`, junto a `FiltrosBarra`. Ocupa todo el ancho dentro del
 * panel móvil y se ajusta al contenido en escritorio, donde comparte fila
 * con la búsqueda.
 */
export function FiltroSelect({
    valor,
    onCambio,
    todos,
    etiqueta,
    opciones,
}: {
    valor: string | null;
    onCambio: (valor: string | null) => void;
    todos: string;
    etiqueta: string;
    opciones: EnumOption[];
}) {
    return (
        <Select
            value={valor ?? TODOS}
            onValueChange={(nuevo) => onCambio(nuevo === TODOS ? null : nuevo)}
        >
            <SelectTrigger
                aria-label={etiqueta}
                className="h-9 w-full sm:w-auto sm:min-w-36"
            >
                <SelectValue placeholder={etiqueta} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={TODOS}>{todos}</SelectItem>
                {opciones.map((opcion) => (
                    <SelectItem key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
