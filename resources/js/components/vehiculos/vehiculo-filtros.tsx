import { FiltrosBarra } from '@/components/filtros-barra';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useVehiculoFiltros } from '@/hooks/use-vehiculo-filtros';
import type { FiltrosVehiculo } from '@/hooks/use-vehiculo-filtros';
import type { EnumOption } from '@/types/fleet';

const TODOS = 'todos';

type Props = {
    filtros: FiltrosVehiculo;
    tipos: EnumOption[];
    estados: EnumOption[];
    cajas: EnumOption[];
};

export function VehiculoFiltros({ filtros, tipos, estados, cajas }: Props) {
    const { buscar, setBuscar, aplicar } = useVehiculoFiltros(filtros);

    const activos = [filtros.tipo, filtros.caja, filtros.estado].filter(
        Boolean,
    ).length;

    return (
        <FiltrosBarra
            buscar={buscar}
            onBuscar={setBuscar}
            placeholder="Buscar por placa, marca o modelo..."
            etiquetaBusqueda="Buscar vehículos"
            activos={activos}
            onLimpiar={() => aplicar({ tipo: null, caja: null, estado: null })}
        >
            <FiltroSelect
                valor={filtros.tipo}
                onCambio={(tipo) => aplicar({ tipo })}
                todos="Todos los tipos"
                etiqueta="Tipo"
                opciones={tipos}
            />
            <FiltroSelect
                valor={filtros.caja}
                onCambio={(caja) => aplicar({ caja })}
                todos="Todas las cajas"
                etiqueta="Caja"
                opciones={cajas}
            />
            <FiltroSelect
                valor={filtros.estado}
                onCambio={(estado) => aplicar({ estado })}
                todos="Todos los estados"
                etiqueta="Estado"
                opciones={estados}
            />
        </FiltrosBarra>
    );
}

/**
 * Ocupa todo el ancho dentro del panel móvil y se ajusta al contenido en
 * escritorio, donde comparte fila con la búsqueda.
 */
function FiltroSelect({
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
