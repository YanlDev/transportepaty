import { lazy, Suspense, useSyncExternalStore } from 'react';
import { Spinner } from '@/components/ui/spinner';
import type { MarcadorVehiculo, PuntoRecorrido } from '@/types/fleet';

// Leaflet touches `window` at import time, so it must never run during SSR.
// Loading it lazily + only on the client keeps it strictly client-side, and
// the chunk resolves once so toggling modes never remounts the map.
const MapaLienzoLeaflet = lazy(
    () => import('@/components/mapa/mapa-lienzo-leaflet'),
);

const emptySubscribe = () => () => {};

/** False during SSR, true once running in the browser (no hydration mismatch). */
function useEsCliente(): boolean {
    return useSyncExternalStore(
        emptySubscribe,
        () => true,
        () => false,
    );
}

type Props = {
    modo: 'vivo' | 'recorrido';
    marcadores: MarcadorVehiculo[];
    puntos: PuntoRecorrido[];
    pos: number;
    className?: string;
};

export function MapaLienzo(props: Props) {
    const esCliente = useEsCliente();

    const cargando = (
        <div className="flex h-full w-full items-center justify-center bg-muted text-muted-foreground">
            <Spinner />
        </div>
    );

    if (!esCliente) {
        return cargando;
    }

    return (
        <Suspense fallback={cargando}>
            <MapaLienzoLeaflet {...props} />
        </Suspense>
    );
}
