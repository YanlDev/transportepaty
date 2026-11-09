<?php

namespace App\Services\Archivos;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Dónde vive cada archivo dentro del disco.
 *
 * Los nuevos van en `{módulo}/{año}/{mes}/{uuid}/`, y esa carpeta queda
 * anotada en el propio registro (`custom_properties.carpeta`): la ruta no se
 * recalcula nunca, así que cambiar la lista de módulos no pierde archivos.
 * Los que se subieron antes no tienen la anotación y siguen en `{id}/`,
 * donde los dejó MediaLibrary.
 *
 * Cada archivo tiene su carpeta propia —no comparte la del mes— porque al
 * borrar uno MediaLibrary limpia su carpeta entera.
 */
class GeneradorDeRutas implements PathGenerator
{
    public const PROPIEDAD = 'carpeta';

    public function getPath(Media $media): string
    {
        return $this->base($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->base($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->base($media).'/responsive-images/';
    }

    /**
     * La carpeta que le toca a un archivo nuevo de ese modelo.
     *
     * @param  class-string|string  $modelo  Clase o alias del morph map.
     */
    public static function carpetaNueva(string $modelo, string $uuid, ?DateTimeInterface $fecha = null): string
    {
        $clase = Relation::getMorphedModel($modelo) ?? $modelo;
        $modulo = config('uploads.modulos')[$clase] ?? 'otros';
        $fecha ??= now();

        return "{$modulo}/{$fecha->format('Y')}/{$fecha->format('m')}/{$uuid}";
    }

    private function base(Media $media): string
    {
        $carpeta = $media->getCustomProperty(self::PROPIEDAD);

        return is_string($carpeta) && $carpeta !== '' ? $carpeta : (string) $media->getKey();
    }
}
