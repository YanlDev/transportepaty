import { useEffect, useMemo, useState } from 'react';
import { comprimirImagen, formatearPeso } from '@/lib/comprimir-imagen';
import type { PerfilDeImagen } from '@/lib/comprimir-imagen';

export type TipoAceptado = 'imagen' | 'pdf' | 'imagen-pdf';

/** El tope del servidor (`config/uploads.php`), en KB. */
export const MAXIMO_KB = 10240;

function esAceptado(archivo: File, acepta: TipoAceptado): boolean {
    const esImagen = archivo.type.startsWith('image/');
    const esPdf = archivo.type === 'application/pdf';

    return (esImagen && acepta !== 'pdf') || (esPdf && acepta !== 'imagen');
}

/**
 * Lo que hace falta entre que alguien elige un archivo y el formulario lo
 * manda: descartar lo que no corresponde, comprimir las fotos (los PDF pasan
 * tal cual), avisar si algo pesa de más y armar las vistas previas.
 *
 * `preparando` sirve para bloquear el botón de enviar mientras se comprime.
 */
export function useSubidaArchivos({
    archivos,
    acepta,
    perfil = 'foto',
    maximoKb = MAXIMO_KB,
}: {
    archivos: File[];
    acepta: TipoAceptado;
    perfil?: PerfilDeImagen;
    maximoKb?: number;
}) {
    const [preparando, setPreparando] = useState(false);
    const [aviso, setAviso] = useState<string | null>(null);

    const vistas = useMemo(
        () =>
            archivos.map((archivo) =>
                archivo.type.startsWith('image/')
                    ? URL.createObjectURL(archivo)
                    : null,
            ),
        [archivos],
    );

    useEffect(
        () => () =>
            vistas.forEach((url) => {
                if (url) {
                    URL.revokeObjectURL(url);
                }
            }),
        [vistas],
    );

    /** Devuelve los archivos listos para sumarse al formulario. */
    const preparar = async (elegidos: File[]): Promise<File[]> => {
        const validos = elegidos.filter((archivo) =>
            esAceptado(archivo, acepta),
        );
        const avisos: string[] = [];

        if (validos.length < elegidos.length) {
            avisos.push(
                acepta === 'pdf'
                    ? 'Solo se pueden subir archivos PDF.'
                    : acepta === 'imagen'
                      ? 'Solo se pueden subir fotos.'
                      : 'Solo se pueden subir fotos o archivos PDF.',
            );
        }

        setPreparando(true);
        const comprimidos = await Promise.all(
            validos.map((archivo) => comprimirImagen(archivo, perfil)),
        );
        setPreparando(false);

        const listos = comprimidos.filter(
            (archivo) => archivo.size <= maximoKb * 1024,
        );

        if (listos.length < comprimidos.length) {
            const pesado = comprimidos.find(
                (archivo) => archivo.size > maximoKb * 1024,
            );

            avisos.push(
                `«${pesado?.name}» pesa ${formatearPeso(pesado?.size ?? 0)} y el máximo es ${Math.round(maximoKb / 1024)} MB. Si es un PDF escaneado, guárdalo en menor calidad.`,
            );
        }

        setAviso(avisos.length > 0 ? avisos.join(' ') : null);

        return listos;
    };

    return { preparando, aviso, vistas, preparar };
}
