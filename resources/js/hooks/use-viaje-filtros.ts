import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/ViajeController';

export type FiltrosViaje = {
    buscar: string | null;
    cliente: string | null;
    destino_ciudad: string | null;
    tipo_carga: string | null;
};

/**
 * Igual que `useVehiculoFiltros`: la búsqueda de texto queda local y con
 * debounce, mientras que cliente/destino/tipo de carga aplican de una con
 * `aplicar`, sin esperar a que el usuario deje de tipear (son selectores,
 * no texto libre).
 */
export function useViajeFiltros(filtros: FiltrosViaje) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const buscarActual = filtros.buscar ?? '';

    const aplicar = (cambios: Partial<FiltrosViaje>) => {
        const merged: FiltrosViaje = {
            buscar,
            cliente: filtros.cliente,
            destino_ciudad: filtros.destino_ciudad,
            tipo_carga: filtros.tipo_carga,
            ...cambios,
        };

        const params: Record<string, string> = {};

        if (merged.buscar) {
            params.buscar = merged.buscar;
        }

        if (merged.cliente) {
            params.cliente = merged.cliente;
        }

        if (merged.destino_ciudad) {
            params.destino_ciudad = merged.destino_ciudad;
        }

        if (merged.tipo_carga) {
            params.tipo_carga = merged.tipo_carga;
        }

        router.get(index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (buscar === buscarActual) {
            return;
        }

        const timeout = setTimeout(() => aplicar({ buscar }), 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [buscar, buscarActual]);

    return { buscar, setBuscar, aplicar };
}
