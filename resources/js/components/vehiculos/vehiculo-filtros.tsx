import { FiltroSelect } from '@/components/filtro-select';
import { FiltrosBarra } from '@/components/filtros-barra';
import { useVehiculoFiltros } from '@/hooks/use-vehiculo-filtros';
import type { FiltrosVehiculo } from '@/hooks/use-vehiculo-filtros';
import type { EnumOption } from '@/types/fleet';

const OPCIONES_BF: EnumOption[] = [
    { value: 'si', label: 'Puede llevar BF' },
    { value: 'no', label: 'No puede llevar BF' },
];

type Props = {
    filtros: FiltrosVehiculo;
    url: string;
    estados: EnumOption[];
    marcas: EnumOption[];
    /** Vacío en Carretas: no tienen caja, así que ese filtro no aplica. */
    cajas: EnumOption[];
};

export function VehiculoFiltros({
    filtros,
    url,
    estados,
    marcas,
    cajas,
}: Props) {
    const { buscar, setBuscar, aplicar } = useVehiculoFiltros(filtros, url);
    const mostrarCaja = cajas.length > 0;

    const activos = [
        filtros.marca,
        mostrarCaja ? filtros.caja : null,
        filtros.estado,
        filtros.bf,
    ].filter(Boolean).length;

    return (
        <FiltrosBarra
            buscar={buscar}
            onBuscar={setBuscar}
            placeholder="Buscar por placa, marca o modelo..."
            etiquetaBusqueda="Buscar vehículos"
            activos={activos}
            onLimpiar={() =>
                aplicar({ marca: null, caja: null, estado: null, bf: null })
            }
        >
            <FiltroSelect
                valor={filtros.marca}
                onCambio={(marca) => aplicar({ marca })}
                todos="Todas las marcas"
                etiqueta="Marca"
                opciones={marcas}
            />
            {mostrarCaja && (
                <FiltroSelect
                    valor={filtros.caja}
                    onCambio={(caja) => aplicar({ caja })}
                    todos="Todas las cajas"
                    etiqueta="Caja"
                    opciones={cajas}
                />
            )}
            <FiltroSelect
                valor={filtros.bf}
                onCambio={(bf) => aplicar({ bf })}
                todos="Bienes fiscalizados"
                etiqueta="Bienes fiscalizados"
                opciones={OPCIONES_BF}
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
