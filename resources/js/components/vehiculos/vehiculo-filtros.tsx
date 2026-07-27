import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
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

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <div className="relative flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={buscar}
                    onChange={(event) => setBuscar(event.target.value)}
                    placeholder="Buscar por placa, marca o modelo..."
                    className="pl-10"
                    aria-label="Buscar vehículos"
                />
            </div>

            <Select
                value={filtros.tipo ?? TODOS}
                onValueChange={(value) =>
                    aplicar({ tipo: value === TODOS ? null : value })
                }
            >
                <SelectTrigger className="sm:w-44">
                    <SelectValue placeholder="Tipo" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={TODOS}>Todos los tipos</SelectItem>
                    {tipos.map((tipo) => (
                        <SelectItem key={tipo.value} value={tipo.value}>
                            {tipo.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filtros.caja ?? TODOS}
                onValueChange={(value) =>
                    aplicar({ caja: value === TODOS ? null : value })
                }
            >
                <SelectTrigger className="sm:w-52">
                    <SelectValue placeholder="Caja" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={TODOS}>Todas las cajas</SelectItem>
                    {cajas.map((caja) => (
                        <SelectItem key={caja.value} value={caja.value}>
                            {caja.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filtros.estado ?? TODOS}
                onValueChange={(value) =>
                    aplicar({ estado: value === TODOS ? null : value })
                }
            >
                <SelectTrigger className="sm:w-48">
                    <SelectValue placeholder="Estado" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={TODOS}>Todos los estados</SelectItem>
                    {estados.map((estado) => (
                        <SelectItem key={estado.value} value={estado.value}>
                            {estado.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
