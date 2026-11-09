<?php

namespace App\Services\Archivos;

use Imagick;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Deja una foto como se guarda en el sistema: JPEG, derecha (la orientación
 * EXIF aplicada a los píxeles), sin metadatos —el GPS del celular no tiene
 * por qué quedar en el servidor— y con el lado mayor recortado al máximo de
 * `config/uploads.php`.
 */
class NormalizadorDeImagen
{
    /**
     * ¿Este servidor sabe leer el HEIC de un iPhone?
     */
    public function leeHeic(): bool
    {
        return extension_loaded('imagick') && Imagick::queryFormats('HEIC') !== [];
    }

    /**
     * Escribe la versión normalizada en un archivo temporal y devuelve su
     * ruta. Quien la pide se encarga de moverla o borrarla.
     *
     * @param  string|null  $coleccion  Para aplicar su perfil, si tiene uno
     *                                  en `uploads.imagenes.perfiles`.
     *
     * @throws RuntimeException si el archivo no se puede leer como imagen.
     */
    public function normalizar(string $ruta, ?string $coleccion = null): string
    {
        $perfil = (array) config("uploads.imagenes.perfiles.{$coleccion}", []);
        $lado = (int) ($perfil['lado_maximo'] ?? config('uploads.imagenes.lado_maximo'));
        $calidad = (int) ($perfil['calidad'] ?? config('uploads.imagenes.calidad'));
        $destino = tempnam(sys_get_temp_dir(), 'foto-');

        if ($destino === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de la foto.');
        }

        try {
            $this->manager()
                ->read($ruta)
                ->scaleDown($lado, $lado)
                ->toJpeg($calidad)
                ->save($destino);
        } catch (Throwable $error) {
            @unlink($destino);

            throw new RuntimeException('No se pudo leer la imagen.', previous: $error);
        }

        return $destino;
    }

    /**
     * Imagick si está (lee HEIC y gasta menos memoria de PHP); si no, GD.
     * En ambos la orientación EXIF se aplica al leer y el JPEG sale sin
     * metadatos.
     */
    private function manager(): ImageManager
    {
        return extension_loaded('imagick')
            ? ImageManager::imagick(strip: true)
            : ImageManager::gd();
    }
}
