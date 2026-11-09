<?php

namespace App\Console\Commands;

use App\Services\Archivos\GeneradorDeRutas;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Lleva los archivos subidos con el esquema viejo (`{id}/{nombre original}`)
 * al nuevo (`{módulo}/{año}/{mes}/{uuid}/{uuid}.ext`), para que todo el
 * disco quede igual.
 *
 * Por cada archivo: mueve su carpeta, renombra el archivo y recién entonces
 * actualiza el registro; si el registro no se puede guardar, devuelve la
 * carpeta a su lugar. El año y el mes son los de la subida original.
 *
 * Ojo: las direcciones públicas cambian. Dentro del sistema no se nota
 * —cada enlace sale del registro—, pero un enlace viejo copiado fuera (un
 * WhatsApp, un correo) deja de abrir.
 */
#[Signature('uploads:reorganize-existing
    {--dry-run : Solo lista qué se movería y a dónde}
    {--lote=100 : Cuántos archivos se cargan por tanda}
    {--limite= : Parar después de esta cantidad de archivos}')]
#[Description('Mueve los archivos ya subidos al esquema de carpetas por módulo y fecha')]
class ReorganizarArchivosExistentes extends Command
{
    public function handle(): int
    {
        $simulacro = (bool) $this->option('dry-run');
        $limite = $this->option('limite') === null ? null : (int) $this->option('limite');
        $movidos = 0;
        $fallidos = 0;

        Media::query()
            ->whereNull('custom_properties->'.GeneradorDeRutas::PROPIEDAD)
            ->chunkById(max(1, (int) $this->option('lote')), function (Collection $lote) use ($simulacro, $limite, &$movidos, &$fallidos): bool {
                foreach ($lote as $media) {
                    if ($limite !== null && $movidos + $fallidos >= $limite) {
                        return false;
                    }

                    $this->mover($media, $simulacro) ? $movidos++ : $fallidos++;
                }

                return true;
            });

        $this->newLine();
        $this->info(($simulacro ? 'Se moverían' : 'Movidos').": {$movidos}. Con problemas: {$fallidos}.");

        return $fallidos === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function mover(Media $media, bool $simulacro): bool
    {
        $origen = dirname($media->getPath());
        $raiz = Str::beforeLast($origen, '/'.$media->getKey());
        $uuid = $media->uuid ?: (string) Str::uuid();
        $carpeta = GeneradorDeRutas::carpetaNueva((string) $media->model_type, $uuid, $media->created_at);
        $destino = $raiz.'/'.$carpeta;
        $nombreViejo = $media->file_name;
        $extension = Str::lower(pathinfo($nombreViejo, PATHINFO_EXTENSION));
        $nombreNuevo = $extension === '' ? $uuid : "{$uuid}.{$extension}";

        if (! is_file($origen.'/'.$nombreViejo)) {
            $this->warn("  #{$media->getKey()}: no está el archivo {$nombreViejo}; se deja como está.");

            return false;
        }

        if ($this->output->isVerbose() || $simulacro) {
            $this->line("  #{$media->getKey()} {$media->getKey()}/{$nombreViejo} → {$carpeta}/{$nombreNuevo}");
        }

        if ($simulacro) {
            return true;
        }

        if (file_exists($destino)) {
            $this->warn("  #{$media->getKey()}: el destino {$carpeta} ya existe; se deja como está.");

            return false;
        }

        File::ensureDirectoryExists(dirname($destino));

        if (! rename($origen, $destino)) {
            $this->warn("  #{$media->getKey()}: no se pudo mover la carpeta.");

            return false;
        }

        rename($destino.'/'.$nombreViejo, $destino.'/'.$nombreNuevo);

        try {
            $media->file_name = $nombreNuevo;
            $media->setCustomProperty(GeneradorDeRutas::PROPIEDAD, $carpeta);
            $media->save();
        } catch (Throwable $error) {
            rename($destino.'/'.$nombreNuevo, $destino.'/'.$nombreViejo);
            rename($destino, $origen);
            $this->warn("  #{$media->getKey()}: no se pudo guardar el registro ({$error->getMessage()}); se devolvió a su lugar.");

            return false;
        }

        return true;
    }
}
