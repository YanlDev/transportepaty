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
import type { EnumOption, SucursalOption } from '@/types/fleet';

const TODOS = 'todos';

type Props = {
    filtros: FiltrosVehiculo;
    sucursales: SucursalOption[];
    estados: EnumOption[];
};

export function VehiculoFiltros({ filtros, sucursales, estados }: Props) {
    const { buscar, setBuscar, aplicar } = useVehiculoFiltros(filtros);

    return (
        <div className="flex flex-col gap-3 sm:flex-row">
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

            <Select
                value={
                    filtros.sucursal_id ? String(filtros.sucursal_id) : TODOS
                }
                onValueChange={(value) =>
                    aplicar({
                        sucursal_id: value === TODOS ? null : Number(value),
                    })
                }
            >
                <SelectTrigger className="sm:w-52">
                    <SelectValue placeholder="Sucursal" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={TODOS}>Todas las sucursales</SelectItem>
                    {sucursales.map((sucursal) => (
                        <SelectItem
                            key={sucursal.id}
                            value={String(sucursal.id)}
                        >
                            {sucursal.nombre}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
