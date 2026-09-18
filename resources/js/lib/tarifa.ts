import type { LineaTarifa, ResultadoTarifa } from '@/types/fleet';

/**
 * La misma cuenta que `App\Services\CalculadoraCotizacion`, repetida acá para
 * que la hoja responda mientras se tipea. Lo que se emite pasa igual por el
 * servidor, que la vuelve a hacer: si una cambia, la otra también.
 *
 * Cada paso se redondea antes de alimentar al siguiente, como allá, para que
 * los dos lleguen al mismo centavo.
 */
export function calcularTarifa(
    lineas: LineaTarifa[],
    { km, dias, margenPct }: { km: number; dias: number; margenPct: number },
    igvPct: number,
): ResultadoTarifa {
    let totalFijo = 0;
    let totalVariable = 0;

    const componentes = lineas.map((linea) => {
        const esFijo = linea.tipo === 'fijo_dia';
        const importe = redondear(linea.tasa * (esFijo ? dias : km));

        if (esFijo) {
            totalFijo += importe;
        } else {
            totalVariable += importe;
        }

        return { ...linea, importe };
    });

    totalFijo = redondear(totalFijo);
    totalVariable = redondear(totalVariable);
    const costoOperativo = redondear(totalFijo + totalVariable);

    // Margen sobre el precio de venta, como en la hoja: con 12 % el costo es
    // el 88 % de la tarifa.
    const subtotal =
        margenPct < 1
            ? redondear(costoOperativo / (1 - margenPct))
            : costoOperativo;
    const igv = redondear(subtotal * igvPct);

    return {
        desglose: { componentes },
        margen_pct: margenPct,
        total_fijo: totalFijo,
        total_variable: totalVariable,
        costo_operativo: costoOperativo,
        margen: redondear(subtotal - costoOperativo),
        subtotal,
        igv,
        total: redondear(subtotal + igv),
    };
}

/**
 * A dos decimales, con el medio centavo hacia arriba como el `round()` de PHP.
 * El épsilon corrige los productos que en binario quedan apenas por debajo
 * (1.005 se guarda como 1.00499…).
 */
function redondear(valor: number): number {
    return Math.round((valor + Number.EPSILON) * 100) / 100;
}

/** Números con separador de miles y dos decimales, como en la hoja. */
export function formatearMonto(monto: number): string {
    return monto.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
