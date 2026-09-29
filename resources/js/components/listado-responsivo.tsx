import { Fragment } from 'react';
import type { Key, ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableHeader } from '@/components/ui/table';

type Props<T> = {
    items: T[];
    clave: (item: T) => Key;
    /** La tarjeta del celular: el dato por el que se entra, arriba y grande. */
    tarjeta: (item: T) => ReactNode;
    /** Lo que se muestra cuando no hay nada (un `EmptyState`). */
    vacio: ReactNode;
    /** Mientras llegan los datos: esqueletos con la forma de las tarjetas. */
    cargando?: boolean;
} & (
    | {
          /** Fila de encabezado (`<TableRow>` con los `<TableHead>`). */
          encabezado: ReactNode;
          fila: (item: T) => ReactNode;
          tabla?: never;
      }
    | {
          /** Una tabla ya armada, para las que tienen su propio componente. */
          tabla: ReactNode;
          encabezado?: never;
          fila?: never;
      }
);

/**
 * Un listado que es tabla desde `md` y tarjetas debajo. Existe para que todos
 * cambien en el mismo punto: cuando cada pantalla elegía el suyo (`sm`, `md`
 * o `lg`), la misma tablet veía tarjetas en una y una tabla apretada en otra.
 *
 * El scroll horizontal queda permitido solo adentro de la tabla, nunca en la
 * página. Los dos bloques se montan siempre y los esconde CSS: sin
 * `useIsMobile`, no hay salto al hidratar ni al girar la tablet.
 */
export function ListadoResponsivo<T>({
    items,
    clave,
    tarjeta,
    vacio,
    cargando = false,
    ...vista
}: Props<T>) {
    if (cargando) {
        return (
            <div className="flex flex-col gap-2" aria-busy="true">
                {Array.from({ length: 4 }, (_, indice) => (
                    <Skeleton key={indice} className="h-20 w-full rounded-xl" />
                ))}
            </div>
        );
    }

    if (items.length === 0) {
        return vacio;
    }

    return (
        <>
            <ul className="flex flex-col gap-2 md:hidden">
                {items.map((item) => (
                    <li key={clave(item)}>{tarjeta(item)}</li>
                ))}
            </ul>

            <div className="hidden min-w-0 md:block">
                {'tabla' in vista && vista.tabla !== undefined ? (
                    vista.tabla
                ) : (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>{vista.encabezado}</TableHeader>
                            <TableBody>
                                {items.map((item) => (
                                    <Fragment key={clave(item)}>
                                        {vista.fila?.(item)}
                                    </Fragment>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}
