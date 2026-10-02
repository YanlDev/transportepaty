import type { ViajeContable, ViajeSeleccionado } from '@/types/contabilidad';

/** Lo que guarda la selección de un viaje: lo justo para nombrarlo y avisar. */
export function aSeleccion(viaje: ViajeContable): ViajeSeleccionado {
    return {
        id: viaje.id,
        numero_gr: viaje.numero_gr,
        facturado: viaje.facturas.length > 0,
    };
}

/**
 * Los días de la factura por cobrar más vieja del viaje. Con flete y estadía
 * en facturas distintas, la que manda en la pastilla es la que más se atrasó.
 */
export function diasVencidaMayor(viaje: ViajeContable): number | null {
    const dias = viaje.facturas
        .map((factura) => factura.dias_vencida)
        .filter((valor): valor is number => valor !== null);

    return dias.length === 0 ? null : Math.max(...dias);
}

/** Se puede marcar para una factura: ni la que se decidió no cobrar. */
export function esFacturable(viaje: ViajeContable): boolean {
    return viaje.estado !== 'no_facturable';
}
