import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/ViajeController';

export type FiltrosViaje = {
    buscar: string | null;
};

/**
 * Igual que `useVehiculoFiltros` pero con un solo campo: acá no hay
 * selectores, así que no hace falta el mismo mecanismo de `aplicar` con
 * cambios parciales.
 */
export function useViajeFiltros(filtros: FiltrosViaje) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const buscarActual = filtros.buscar ?? '';

    useEffect(() => {
        if (buscar === buscarActual) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(index().url, buscar ? { buscar } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);

        return () => clearTimeout(timeout);
    }, [buscar, buscarActual]);

    return { buscar, setBuscar };
}
