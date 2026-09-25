import { index } from '@/actions/App/Http/Controllers/ContabilidadController';
import { useFiltros } from '@/hooks/use-filtros';
import type { FiltrosContabilidad } from '@/types/contabilidad';

export function useContabilidadFiltros(filtros: FiltrosContabilidad) {
    return useFiltros(filtros, index().url, {
        conservar: ['estados', 'monedas', 'clientes', 'meses', 'cuentas'],
    });
}
