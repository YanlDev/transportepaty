import imageCompression from 'browser-image-compression';
// La librería corre en un web worker, y ese worker la vuelve a cargar por
// URL. Por defecto la pide a un CDN; acá se le da la copia que sirve la
// propia aplicación, para no depender de nadie de afuera.
import urlLibreria from 'browser-image-compression/dist/browser-image-compression.js?url';

/**
 * Tamaño y calidad con los que sale una foto. Son los mismos números de
 * `config/uploads.php`: si cambias unos, cambia los otros.
 *
 * - `foto`: el estado de una pieza, un tablero, un voucher.
 * - `comprobante`: un papel que hay que poder leer; pide más detalle.
 */
export const PERFILES_DE_IMAGEN = {
    foto: { ladoMaximo: 1920, calidad: 0.8 },
    comprobante: { ladoMaximo: 2200, calidad: 0.85 },
} as const;

export type PerfilDeImagen = keyof typeof PERFILES_DE_IMAGEN;

/** Cuánto pesaba cada archivo antes de comprimirlo, para mostrarlo. */
const pesosOriginales = new WeakMap<File, number>();

/** El peso del archivo tal como salió de la cámara o de la carpeta. */
export function pesoOriginal(archivo: File): number {
    return pesosOriginales.get(archivo) ?? archivo.size;
}

/**
 * Achica una foto antes de subirla. La cámara de un celular saca fotos de 4
 * a 8 MB; con 1920 px de lado mayor queda en unos cientos de KB: sube rápido
 * con datos móviles y no llena el servidor. El trabajo corre en un web
 * worker, así que la pantalla no se congela mientras tanto.
 *
 * Un PDF —o cualquier cosa que no sea imagen— pasa sin tocarse. Si la
 * compresión falla (un formato que el navegador no sabe leer, como el HEIC
 * fuera de Safari) o el resultado pesa más, se devuelve el original y que lo
 * resuelva el servidor.
 */
export async function comprimirImagen(
    archivo: File,
    perfil: PerfilDeImagen = 'foto',
): Promise<File> {
    if (!archivo.type.startsWith('image/')) {
        return archivo;
    }

    const { ladoMaximo, calidad } = PERFILES_DE_IMAGEN[perfil];

    try {
        const comprimido = await imageCompression(archivo, {
            maxWidthOrHeight: ladoMaximo,
            initialQuality: calidad,
            fileType: 'image/jpeg',
            useWebWorker: true,
            libURL: new URL(urlLibreria, window.location.origin).href,
        });

        if (comprimido.size >= archivo.size) {
            return archivo;
        }

        const nombre = archivo.name.replace(/\.[^.]+$/, '') + '.jpg';
        const listo = new File([comprimido], nombre, { type: 'image/jpeg' });

        pesosOriginales.set(listo, archivo.size);

        return listo;
    } catch {
        return archivo;
    }
}

/** «310 KB», «4.2 MB». */
export function formatearPeso(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.ceil(bytes / 1024))} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
