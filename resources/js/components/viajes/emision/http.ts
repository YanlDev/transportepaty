/**
 * GET a un endpoint JSON de la app. Los errores traen `mensaje` (SUNAT caído,
 * GR inexistente) o, si es validación, el primero de `errors`.
 */
export async function consultar<T>(url: string): Promise<T> {
    const respuesta = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    const cuerpo = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        const validacion = cuerpo.errors
            ? (Object.values(cuerpo.errors)[0] as string[] | undefined)?.[0]
            : undefined;

        throw new Error(
            cuerpo.mensaje ??
                validacion ??
                `No se pudo consultar (error ${respuesta.status}).`,
        );
    }

    return cuerpo as T;
}

/** El token CSRF que Laravel deja en la cookie XSRF-TOKEN. */
export function tokenXsrf(): string {
    const cookie = document.cookie
        .split('; ')
        .find((par) => par.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export function hoy(): string {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());

    return fecha.toISOString().slice(0, 10);
}
