import { FiltroBuscable } from '@/components/filtro-buscable';
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
 * A partir de esta cantidad de opciones el desplegable deja de servir: la
 * lista tapa media pantalla y hay que recorrerla a ojo. Ahí se pasa solo al
 * buscador. El número no tiene nada de especial —es donde «Estado» o «Carga»,
 * que son listas cortas y cerradas, siguen cómodos como desplegable, y
 * «Cliente» o «Destino», que crecen con los datos, ya no.
 */
const MAXIMO_DESPLEGABLE = 12;

/**
 * Un selector de filtro con opción "todos" — usado por `VehiculoFiltros`,
 * `viajes/index` y la cobranza, junto a `FiltrosBarra`. Ocupa todo el ancho
 * dentro del panel móvil y se ajusta al contenido en escritorio, donde
 * comparte fila con la búsqueda.
 *
 * Elige solo cómo mostrarse según cuántas opciones reciba, así un filtro que
 * crece con los datos no hay que ir a cambiarlo a mano cuando se pone largo.
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
    if (opciones.length > MAXIMO_DESPLEGABLE) {
        return (
            <FiltroBuscable
                valor={valor}
                onCambio={onCambio}
                todos={todos}
                etiqueta={etiqueta}
                opciones={opciones}
            />
        );
    }

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
