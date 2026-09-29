import { useSyncExternalStore } from 'react';

/** De `md` a antes de `lg`: la tablet vertical y los celulares grandes en horizontal. */
const CONSULTA = '(min-width: 768px) and (max-width: 1023px)';

const mql =
    typeof window === 'undefined' ? undefined : window.matchMedia(CONSULTA);

function suscribir(callback: (event: MediaQueryListEvent) => void) {
    mql?.addEventListener('change', callback);

    return () => mql?.removeEventListener('change', callback);
}

/** Mismo mecanismo que `useIsMobile`, para la franja de tablet. */
export function useEsTablet(): boolean {
    return useSyncExternalStore(
        suscribir,
        () => mql?.matches ?? false,
        () => false,
    );
}
