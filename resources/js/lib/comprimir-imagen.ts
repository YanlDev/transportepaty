/**
 * Achica una foto antes de subirla. La cámara de un celular saca fotos de 4
 * a 8 MB; para ver un tablero o el estado de una pieza alcanza con 1600 px
 * de lado mayor, que queda en unos 300 KB. Sube rápido con datos móviles y
 * no llena el servidor.
 *
 * Si el navegador no puede leer la imagen (un formato raro), devuelve el
 * archivo tal cual y que lo decida la validación del servidor.
 */
export async function comprimirImagen(
    archivo: File,
    ladoMaximo = 1600,
    calidad = 0.8,
): Promise<File> {
    if (!archivo.type.startsWith('image/')) {
        return archivo;
    }

    try {
        const imagen = await createImageBitmap(archivo);
        const escala = Math.min(
            1,
            ladoMaximo / Math.max(imagen.width, imagen.height),
        );
        const ancho = Math.round(imagen.width * escala);
        const alto = Math.round(imagen.height * escala);

        const lienzo = document.createElement('canvas');
        lienzo.width = ancho;
        lienzo.height = alto;
        lienzo.getContext('2d')?.drawImage(imagen, 0, 0, ancho, alto);
        imagen.close();

        const blob = await new Promise<Blob | null>((resolver) =>
            lienzo.toBlob(resolver, 'image/jpeg', calidad),
        );

        if (blob === null || blob.size >= archivo.size) {
            return archivo;
        }

        const nombre = archivo.name.replace(/\.[^.]+$/, '') + '.jpg';

        return new File([blob], nombre, { type: 'image/jpeg' });
    } catch {
        return archivo;
    }
}
