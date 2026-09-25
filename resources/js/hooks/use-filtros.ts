import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Todo filtro viaja como texto en el query string, o no viaja. */
export type FiltrosBase = Record<string, string | null> & {
    buscar: string | null;
};

/**
 * Con menos letras que esto la búsqueda no sale: una sola letra calza con casi
 * todo el listado y solo suma una petición mientras se sigue escribiendo.
 * Borrar el texto sí aplica de una, para volver al listado completo.
 */
const MINIMO_LETRAS = 2;

type Opciones = {
    /** Cuánto se espera después de la última tecla antes de buscar. */
    espera?: number;
    /**
     * Props que no dependen de los filtros (los catálogos de los selectores):
     * no se vuelven a pedir en cada búsqueda, se quedan las que ya hay.
     */
    conservar?: string[];
};

/**
 * Los filtros de un listado, contra el servidor. El texto de búsqueda queda en
 * estado local y se manda con debounce; el resto son selectores y aplican de
 * una, sin esperar. Si llega una búsqueda nueva mientras la anterior sigue en
 * camino, Inertia cancela la anterior.
 *
 * El efecto solo navega cuando el texto difiere de lo que el servidor ya
 * tiene: sin esa comparación, paginar vuelve a montar la página con el mismo
 * `buscar` y la haría rebotar a la página 1.
 *
 * Los valores vacíos no se mandan, así la URL queda con lo que el usuario
 * realmente eligió y no con seis parámetros en blanco.
 */
export function useFiltros<T extends FiltrosBase>(
    filtros: T,
    url: string,
    { espera = 450, conservar = [] }: Opciones = {},
) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const buscarActual = filtros.buscar ?? '';

    const aplicar = (cambios: Partial<T>) => {
        const merged = { ...filtros, buscar, ...cambios };
        const params: Record<string, string> = {};

        for (const [clave, valor] of Object.entries(merged)) {
            if (valor) {
                params[clave] = valor;
            }
        }

        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            except: conservar,
        });
    };

    useEffect(() => {
        const texto = buscar.trim();

        if (texto === buscarActual.trim()) {
            return;
        }

        if (texto.length > 0 && texto.length < MINIMO_LETRAS) {
            return;
        }

        const timeout = setTimeout(
            () => aplicar({ buscar } as Partial<T>),
            espera,
        );

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [buscar, buscarActual]);

    return { buscar, setBuscar, aplicar };
}
