import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export type FiltrosVehiculo = {
    buscar: string | null;
    estado: string | null;
    marca: string | null;
    caja: string | null;
};

/**
 * Manages the tracto/carreta list filters: keeps the search text local
 * (debounced) while marca/caja/status changes apply immediately. `url` is the
 * index route of whichever section is active (tractos or carretas), porque
 * ambas comparten este mismo hook.
 */
export function useVehiculoFiltros(filtros: FiltrosVehiculo, url: string) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const buscarActual = filtros.buscar ?? '';

    const aplicar = (cambios: Partial<FiltrosVehiculo>) => {
        const merged: FiltrosVehiculo = {
            buscar,
            estado: filtros.estado,
            marca: filtros.marca,
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

        if (merged.marca) {
            params.marca = merged.marca;
        }

        if (merged.caja) {
            params.caja = merged.caja;
        }

        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Debounce the free-text search; marca/caja/status call `aplicar` directly.
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
