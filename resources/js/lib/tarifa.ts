import type { LineaTarifa, ResultadoTarifa } from '@/types/fleet';

/**
 * La misma cuenta que `App\Services\CalculadoraCotizacion`, repetida acá para
 * que la hoja responda mientras se tipea. Lo que se emite pasa igual por el
 * servidor, que la vuelve a hacer: si una cambia, la otra también.
 *
 * Cada paso se redondea antes de alimentar al siguiente, como allá, para que
 * los dos lleguen al mismo centavo. El retorno vacío se suma a la ida con las
 * mismas tasas.
 *
 * Con un precio unitario fijado, lo que se cobra es cantidad × ese precio y la
 * tarifa del tarifario queda como referencia (`tarifa_calculada`).
 */
export function calcularTarifa(
    lineas: LineaTarifa[],
    {
        km: kmIda,
        dias: diasIda,
        kmRetorno = 0,
        diasRetorno = 0,
        margenPct,
        cantidad: cantidadPedida = 1,
        precioUnitario: precioFijado = 0,
    }: {
        km: number;
        dias: number;
        kmRetorno?: number;
        diasRetorno?: number;
        margenPct: number;
        cantidad?: number;
        /** Cero o ausente: se reparte la tarifa calculada entre la cantidad. */
        precioUnitario?: number;
    },
    igvPct: number,
): ResultadoTarifa {
    const km = kmIda + kmRetorno;
    const dias = diasIda + diasRetorno;
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
    const tarifaCalculada =
        margenPct < 1
            ? redondear(costoOperativo / (1 - margenPct))
            : costoOperativo;
    const cantidad = cantidadPedida > 0 ? cantidadPedida : 1;
    const precioUnitario =
        precioFijado > 0
            ? redondear(precioFijado)
            : redondear(tarifaCalculada / cantidad);
    const subtotal = redondear(cantidad * precioUnitario);
    const igv = redondear(subtotal * igvPct);

    return {
        desglose: { componentes },
        margen_pct: margenPct,
        total_fijo: totalFijo,
        total_variable: totalVariable,
        costo_operativo: costoOperativo,
        margen: redondear(subtotal - costoOperativo),
        tarifa_calculada: tarifaCalculada,
        cantidad,
        precio_unitario: precioUnitario,
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
