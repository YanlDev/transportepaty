import { index } from '@/actions/App/Http/Controllers/ViajeController';
import { useFiltros } from '@/hooks/use-filtros';

export type FiltrosViaje = {
    buscar: string | null;
    cliente: string | null;
    destino_ciudad: string | null;
    tipo_carga: string | null;
};

export function useViajeFiltros(filtros: FiltrosViaje) {
    return useFiltros(filtros, index().url, {
        conservar: ['tiposCarga', 'clientes', 'ciudadesDestino'],
    });
}
