import { toast } from 'sonner';

/**
 * Muestra el primer error de validación de un guardado en línea.
 *
 * La edición celda por celda no tiene dónde poner un mensaje bajo el campo
 * —la celda vuelve a su valor guardado apenas pierde el foco—, así que el
 * rechazo se avisa por toast. Sin esto, escribir un número de factura repetido
 * simplemente no haría nada y parecería que se guardó.
 */
export function avisarError(errores: Record<string, string>): void {
    const primero = Object.values(errores)[0];

    toast.error(primero ?? 'No se pudo guardar el cambio.');
}
