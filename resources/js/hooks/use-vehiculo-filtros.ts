import { useFiltros } from '@/hooks/use-filtros';

export type FiltrosVehiculo = {
    buscar: string | null;
    estado: string | null;
    marca: string | null;
    caja: string | null;
};

/**
 * `url` es el índice de la sección activa: tractos y carretas comparten este
 * hook y solo se diferencian en a dónde navegan.
 */
export function useVehiculoFiltros(filtros: FiltrosVehiculo, url: string) {
    return useFiltros(filtros, url, {
        conservar: ['estados', 'marcas', 'cajas'],
    });
}
