<?php

namespace App\Services;

use App\Jobs\CompressPdfJob;
use App\Services\Archivos\GeneradorDeRutas;
use App\Services\Archivos\NormalizadorDeImagen;
use App\Services\Archivos\ReglasDeArchivo;
use BackedEnum;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * La única puerta por la que entra un archivo subido. Los controladores no
 * guardan archivos por su cuenta: se lo piden a este servicio.
 *
 * - Mira el tipo real del archivo (su contenido, no su extensión) y su peso.
 * - Una foto se normaliza: JPEG, derecha, sin metadatos, a lo sumo 1920 px.
 * - Un PDF se guarda como llegó y se manda a comprimir en la cola.
 * - El nombre en disco es un UUID, nunca el que traía; el original queda
 *   solo como dato (`media.name`). La carpeta sale de `config/uploads.php`.
 *
 * El almacén de fondo sigue siendo MediaLibrary: las relaciones, las
 * colecciones y las URLs de siempre no cambian.
 */
class FileUploadService
{
    public const PDF_REVISADO = 'pdf_revisado';

    public const IMAGEN_NORMALIZADA = 'imagen_normalizada';

    public function __construct(private readonly NormalizadorDeImagen $normalizador) {}

    /**
     * @param  string  $campo  A qué campo del formulario se le cuelga el error
     *                         si el archivo no se acepta.
     *
     * @throws ValidationException
     */
    public function guardar(HasMedia $modelo, UploadedFile $archivo, string $coleccion, string $campo = 'archivo'): Media
    {
        $mime = $this->tipoReal($archivo);
        $esImagen = in_array($mime, ReglasDeArchivo::mimesDeImagen(), true);

        if (! $esImagen && $mime !== 'application/pdf') {
            $this->rechazar($campo, 'El archivo debe ser una imagen (JPG, PNG, WebP o HEIC) o un PDF.');
        }

        $maximoKb = (int) config($esImagen ? 'uploads.imagenes.max_kb' : 'uploads.pdf.max_kb');

        if ($archivo->getSize() > $maximoKb * 1024) {
            $this->rechazar($campo, 'El archivo pesa más de '.round($maximoKb / 1024).' MB.');
        }

        $media = $esImagen
            ? $this->guardarImagen($modelo, $archivo, $coleccion, $campo, $mime)
            : $this->guardarPdf($modelo, $archivo, $coleccion);

        if (! $esImagen && $this->debeComprimirse($media)) {
            CompressPdfJob::dispatch($media)->afterCommit();
        }

        return $media;
    }

    /**
     * ¿A este PDF le toca pasar por Ghostscript? No si la compresión está
     * apagada ni si su modelo o su tipo de documento están en las
     * exclusiones de `config/uploads.php`.
     */
    public function debeComprimirse(Media $media): bool
    {
        if ($media->mime_type !== 'application/pdf' || ! config('uploads.pdf.comprimir')) {
            return false;
        }

        $clase = Media::getActualClassNameForMorph($media->model_type);

        if (in_array($clase, (array) config('uploads.exclusiones.modelos'), true)) {
            return false;
        }

        $excluidos = (array) config('uploads.exclusiones.tipos_documento');

        if ($excluidos === []) {
            return true;
        }

        $tipo = $media->model?->getAttribute('tipo');
        $tipo = $tipo instanceof BackedEnum ? $tipo->value : $tipo;

        return ! in_array($tipo, $excluidos, true);
    }

    private function guardarImagen(HasMedia $modelo, UploadedFile $archivo, string $coleccion, string $campo, string $mime): Media
    {
        if (in_array($mime, ['image/heic', 'image/heif'], true) && ! $this->normalizador->leeHeic()) {
            $this->rechazar($campo, 'Este servidor no puede leer fotos HEIC. Súbela como JPG (en el iPhone: Ajustes › Cámara › Formatos › «Más compatible»).');
        }

        try {
            $normalizada = $this->normalizador->normalizar((string) $archivo->getRealPath(), $coleccion);
        } catch (RuntimeException) {
            $this->rechazar($campo, 'No se pudo leer la imagen: el archivo está dañado o no es una foto.');
        }

        $uuid = (string) Str::uuid();

        // MediaLibrary mueve el temporal al disco: no queda nada que limpiar.
        return $modelo->addMedia($normalizada)
            ->usingName($this->nombreOriginal($archivo))
            ->usingFileName("{$uuid}.jpg")
            ->withCustomProperties([
                GeneradorDeRutas::PROPIEDAD => GeneradorDeRutas::carpetaNueva($modelo::class, $uuid),
                self::IMAGEN_NORMALIZADA => true,
            ])
            ->toMediaCollection($coleccion);
    }

    private function guardarPdf(HasMedia $modelo, UploadedFile $archivo, string $coleccion): Media
    {
        $uuid = (string) Str::uuid();

        // El origen se conserva: puede ser un archivo que el sistema todavía
        // necesita (la GR recién emitida), no solo el temporal de la subida.
        return $modelo->addMedia($archivo)
            ->preservingOriginal()
            ->usingName($this->nombreOriginal($archivo))
            ->usingFileName("{$uuid}.pdf")
            ->withCustomProperties([
                GeneradorDeRutas::PROPIEDAD => GeneradorDeRutas::carpetaNueva($modelo::class, $uuid),
            ])
            ->toMediaCollection($coleccion);
    }

    /**
     * El tipo según el contenido del archivo. No se le pregunta al
     * `UploadedFile`: lo que declara el navegador (o la extensión) no vale.
     */
    private function tipoReal(UploadedFile $archivo): string
    {
        $ruta = $archivo->getRealPath();

        if ($ruta === false || ! is_file($ruta)) {
            return '';
        }

        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
    }

    private function nombreOriginal(UploadedFile $archivo): string
    {
        $nombre = trim(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME));

        return $nombre === '' ? 'archivo' : Str::limit($nombre, 200, '');
    }

    /**
     * @throws ValidationException
     */
    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
