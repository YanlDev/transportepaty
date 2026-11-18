import type { GalonesTramo } from '@/types/abastecimiento';

/**
 * La fila de un tramo en la tabla de galones: cargado con una carga que
 * tiene su propia fila (Lima → Mina · Particular), esa; si no, la que vale
 * para cualquier carga. Igual que `TablaDeTramos::fila()` en el servidor.
 */
export function filaDeLaTabla(
    tabla: GalonesTramo[],
    origen: string,
    destino: string,
    carga: string | null = null,
): GalonesTramo | undefined {
    const delTramo = tabla.filter(
        (fila) => fila.zona_origen === origen && fila.zona_destino === destino,
    );

    return (
        (carga !== null && carga !== 'vacio'
            ? delTramo.find((fila) => fila.tipo_carga === carga)
            : undefined) ?? delTramo.find((fila) => !fila.tipo_carga)
    );
}
