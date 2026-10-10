// Credit: https://usehooks-ts.com/
import { useEffect, useRef, useState } from 'react';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
export type UseClipboardReturn = [CopiedValue, CopyFn];

/**
 * Copia usando `execCommand`, que es la única vía disponible fuera de un
 * contexto seguro. La app se sirve por HTTP en la red interna, así que
 * `navigator.clipboard` no existe y sin esto no se podría copiar nada.
 *
 * El textarea va fuera de pantalla y se quita enseguida; se preserva el foco
 * anterior para no interrumpir lo que el usuario estuviera escribiendo.
 */
function copiarConExecCommand(text: string): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    const anterior = document.activeElement as HTMLElement | null;
    const area = document.createElement('textarea');

    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.top = '-9999px';
    area.style.opacity = '0';

    document.body.appendChild(area);
    area.select();

    let copiado = false;

    try {
        copiado = document.execCommand('copy');
    } catch {
        copiado = false;
    }

    document.body.removeChild(area);
    anterior?.focus?.();

    return copiado;
}

/**
 * Cuánto dura la confirmación de «copiado». Sin un límite el ✓ quedaba para
 * siempre, y en una tabla terminaba marcando cada número que se copió alguna
 * vez, como si fuera un estado de la fila.
 */
const DURACION_COPIADO_MS = 2000;

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);
    const temporizador = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(
        () => () => {
            if (temporizador.current !== null) {
                clearTimeout(temporizador.current);
            }
        },
        [],
    );

    const marcarCopiado = (text: string): void => {
        setCopiedText(text);

        if (temporizador.current !== null) {
            clearTimeout(temporizador.current);
        }

        temporizador.current = setTimeout(
            () => setCopiedText(null),
            DURACION_COPIADO_MS,
        );
    };

    const copy: CopyFn = async (text) => {
        if (navigator?.clipboard) {
            try {
                await navigator.clipboard.writeText(text);
                marcarCopiado(text);

                return true;
            } catch {
                // Puede fallar por permisos aunque la API exista; se intenta
                // igual con el método antiguo antes de darse por vencido.
            }
        }

        if (copiarConExecCommand(text)) {
            marcarCopiado(text);

            return true;
        }

        console.warn('No se pudo copiar al portapapeles');
        setCopiedText(null);

        return false;
    };

    return [copiedText, copy];
}
