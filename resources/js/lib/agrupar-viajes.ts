/**
 * Colores del borde de grupo, alternados entre grupos consecutivos — no por
 * significado (a diferencia del color de cliente), solo para que dos grupos
 * distintos que caen uno pegado al otro en la tabla (ej. dos placas
 * distintas, mismo día) no se lean como un borde continuo de un solo grupo.
 * Con 2 alcanza: grupos consecutivos nunca repiten color entre sí.
 */
const COLORES_GRUPO = [
    'border-l-primary',
    'border-l-slate-400 dark:border-l-slate-500',
] as const;

export type FilaAgrupada<T> = {
    viaje: T;
    agrupado: boolean;
    colorGrupo: string | null;
};

/**
 * Una GR no es un viaje: el mismo camión puede salir una vez y llevar carga
 * de dos clientes, cada una con su propia GR (ver `Viaje::claveGrupoViaje`
 * en el backend). Acá solo se cuenta cuántas filas comparten esa clave —
 * si hay más de una, se marcan como agrupadas para que la tabla les ponga
 * un borde compartido en vez de tratarlas como viajes independientes.
 *
 * Lo usan tanto `/viajes` como la cobranza: allá el borde dice qué GR salieron
 * juntas, que es exactamente lo que hay que mirar para decidir qué se factura
 * en un solo documento.
 */
export function agruparViajes<T extends { grupo_viaje: string }>(
    datos: T[],
): FilaAgrupada<T>[] {
    const conteos = new Map<string, number>();

    for (const viaje of datos) {
        conteos.set(
            viaje.grupo_viaje,
            (conteos.get(viaje.grupo_viaje) ?? 0) + 1,
        );
    }

    let indiceGrupo = -1;
    let claveAnterior: string | null = null;

    return datos.map((viaje) => {
        const agrupado = (conteos.get(viaje.grupo_viaje) ?? 0) > 1;

        if (viaje.grupo_viaje !== claveAnterior) {
            indiceGrupo++;
            claveAnterior = viaje.grupo_viaje;
        }

        return {
            viaje,
            agrupado,
            colorGrupo: agrupado
                ? COLORES_GRUPO[indiceGrupo % COLORES_GRUPO.length]
                : null,
        };
    });
}
