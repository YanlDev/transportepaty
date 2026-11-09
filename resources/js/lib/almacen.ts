import type { OpcionBuscable } from '@/components/selector-buscable';
import type { ArticuloElegible } from '@/types/almacen';

const LOCALE = 'es-PE';

/**
 * Una cantidad del almacén: sin decimales de sobra («12», «2.5», «0.125») y
 * con su unidad si se pasa.
 */
export function formatearCantidad(
    cantidad: number | null | undefined,
    unidad?: string | null,
): string {
    if (cantidad === null || cantidad === undefined) {
        return '—';
    }

    const texto = new Intl.NumberFormat(LOCALE, {
        maximumFractionDigits: 3,
    }).format(cantidad);

    return unidad ? `${texto} ${unidad}` : texto;
}

/**
 * Un costo unitario: hasta cuatro decimales, porque el litro de aceite o el
 * perno sale a céntimos de céntimo.
 */
export function formatearUnitario(monto: number | null | undefined): string {
    if (monto === null || monto === undefined) {
        return '—';
    }

    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    }).format(monto);
}

/** Un monto en la moneda del comprobante. */
export function formatearMonto(
    monto: number | null | undefined,
    moneda = 'PEN',
): string {
    if (monto === null || monto === undefined) {
        return '—';
    }

    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency: moneda,
    }).format(monto);
}

/** Los artículos como opciones del selector: se buscan por código, parte o marca. */
export function opcionesArticulo(
    articulos: ArticuloElegible[],
    conStock = false,
): OpcionBuscable[] {
    return articulos.map((articulo) => ({
        valor: articulo.id,
        etiqueta: conStock
            ? `${articulo.nombre} · ${formatearCantidad(articulo.stock, articulo.unidad)}`
            : articulo.nombre,
        detalle: [articulo.codigo, articulo.numero_parte, articulo.marca]
            .filter(Boolean)
            .join(' '),
    }));
}

/** Un error de una línea del formulario: `detalles.2.cantidad`. */
export function errorDeLinea(
    errores: Record<string, string | undefined>,
    indice: number,
    campo: string,
): string | undefined {
    return errores[`detalles.${indice}.${campo}`];
}
