import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/VehiculoController';

export type FiltrosVehiculo = {
    buscar: string | null;
    estado: string | null;
    tipo: string | null;
    caja: string | null;
};

/**
 * Manages the vehicle list filters: keeps the search text local (debounced)
 * while type/status changes apply immediately. Navigation always uses the
 * current local search value, never the value echoed back by the server.
 */
export function useVehiculoFiltros(filtros: FiltrosVehiculo) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const buscarActual = filtros.buscar ?? '';

    const aplicar = (cambios: Partial<FiltrosVehiculo>) => {
        const merged: FiltrosVehiculo = {
            buscar,
            estado: filtros.estado,
            tipo: filtros.tipo,
            caja: filtros.caja,
            ...cambios,
        };

        const params: Record<string, string> = {};

        if (merged.buscar) {
            params.buscar = merged.buscar;
        }

        if (merged.estado) {
            params.estado = merged.estado;
        }

        if (merged.tipo) {
            params.tipo = merged.tipo;
        }

        if (merged.caja) {
            params.caja = merged.caja;
        }

        router.get(index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Debounce the free-text search; type/status call `aplicar` directly.
    // Only navigate when the user actually changed the text relative to what
    // the server already has. This prevents pagination (which re-renders this
    // component without touching `buscar`) from resetting back to page 1.
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
