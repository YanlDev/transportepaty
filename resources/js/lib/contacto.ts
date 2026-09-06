/** Código de país del Perú, que es de donde son todos los números cargados. */
const CODIGO_PERU = '51';

/**
 * El enlace para escribirle por WhatsApp. Los teléfonos se cargan como se
 * dictan («999 888 777», «+51 999888777»), así que se limpia todo lo que no
 * sea dígito y se antepone el código de país cuando falta.
 *
 * Devuelve null si lo guardado no llega a ser un número marcable: es
 * preferible no mostrar el botón a mandar a alguien a un chat vacío.
 */
export function enlaceWhatsapp(telefono: string | null): string | null {
    if (!telefono) {
        return null;
    }

    const digitos = telefono.replace(/\D/g, '');

    // Un celular peruano son 9 dígitos y empieza en 9; con el código de país
    // delante son 11.
    const numero = digitos.length === 9 ? `${CODIGO_PERU}${digitos}` : digitos;

    if (numero.length < 11) {
        return null;
    }

    return `https://wa.me/${numero}`;
}
