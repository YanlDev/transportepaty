<?php

namespace App\Console\Commands;

use App\Services\Archivos\CompresorPdf;
use App\Services\Archivos\NormalizadorDeImagen;
use App\Services\Archivos\ReglasDeArchivo;
use App\Services\Archivos\ResultadoCompresion;
use App\Services\FileUploadService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pasa por la misma compresión los archivos que se subieron antes de que
 * existiera: los PDF por Ghostscript y las fotos por la normalización.
 *
 * Avanza por lotes y anota en cada registro que ya lo revisó, así que se
 * puede cortar y volver a lanzar: retoma donde quedó y no repite. Con
 * `--dry-run` hace todo el trabajo de medir pero no cambia ningún archivo
 * ni registro; solo dice cuánto se ahorraría.
 */
#[Signature('uploads:compress-existing
    {--dry-run : Solo informa cuánto espacio se ahorraría, sin cambiar nada}
    {--lote=50 : Cuántos archivos se cargan por tanda}
    {--limite= : Parar después de revisar esta cantidad de archivos}
    {--solo= : Limitar a «pdf» o a «imagenes»}
    {--timeout=300 : Segundos que se le dan a Ghostscript por PDF}')]
#[Description('Comprime los PDF y las fotos que ya estaban subidos')]
class ComprimirArchivosExistentes extends Command
{
    private int $revisados = 0;

    private int $comprimidos = 0;

    private int $sinMejora = 0;

    private int $fallidos = 0;

    private int $bytesAntes = 0;

    private int $bytesAhorrados = 0;

    public function handle(CompresorPdf $compresor, NormalizadorDeImagen $normalizador, FileUploadService $subidas): int
    {
        $simulacro = (bool) $this->option('dry-run');
        $solo = $this->option('solo');
        $limite = $this->option('limite') === null ? null : (int) $this->option('limite');

        if (! in_array($solo, [null, 'pdf', 'imagenes'], true)) {
            $this->error('--solo admite «pdf» o «imagenes».');

            return self::FAILURE;
        }

        if ($solo !== 'imagenes' && ! $compresor->hayGhostscript()) {
            $this->warn('Ghostscript no está instalado: los PDF se saltan. (sudo apt install ghostscript)');
            $solo = 'imagenes';
        }

        $mimes = match ($solo) {
            'pdf' => ['application/pdf'],
            'imagenes' => ReglasDeArchivo::mimesDeImagen(),
            default => ['application/pdf', ...ReglasDeArchivo::mimesDeImagen()],
        };

        $this->info($simulacro ? 'Simulacro: no se cambia ningún archivo.' : 'Comprimiendo archivos existentes…');

        Media::query()
            ->whereIn('mime_type', $mimes)
            ->whereNull('custom_properties->'.FileUploadService::PDF_REVISADO)
            ->whereNull('custom_properties->'.FileUploadService::IMAGEN_NORMALIZADA)
            ->with('model')
            ->chunkById(max(1, (int) $this->option('lote')), function (Collection $lote) use ($compresor, $normalizador, $subidas, $simulacro, $limite): bool {
                foreach ($lote as $media) {
                    if ($limite !== null && $this->revisados >= $limite) {
                        return false;
                    }

                    $media->mime_type === 'application/pdf'
                        ? $this->comprimirPdf($media, $compresor, $subidas, $simulacro)
                        : $this->normalizarImagen($media, $normalizador, $simulacro);
                }

                $this->line("  … {$this->revisados} revisados, ".Number::fileSize($this->bytesAhorrados, 1).' ahorrados');

                return true;
            });

        $this->newLine();
        $this->table(['Revisados', 'Comprimidos', 'Sin mejora', 'Con error', 'Pesaban', $simulacro ? 'Se ahorraría' : 'Ahorrado'], [[
            $this->revisados,
            $this->comprimidos,
            $this->sinMejora,
            $this->fallidos,
            Number::fileSize($this->bytesAntes, 1),
            Number::fileSize($this->bytesAhorrados, 1),
        ]]);

        return self::SUCCESS;
    }

    private function comprimirPdf(Media $media, CompresorPdf $compresor, FileUploadService $subidas, bool $simulacro): void
    {
        if (! $subidas->debeComprimirse($media)) {
            return;
        }

        $resultado = $compresor->comprimir($media->getPath(), reemplazar: ! $simulacro, timeout: (int) $this->option('timeout'));
        $this->anotar($media, $resultado);

        if ($simulacro || ! $resultado->concluyente()) {
            return;
        }

        $media->size = $resultado->comprimido() ? $resultado->bytesDespues : $media->size;
        $media->setCustomProperty(FileUploadService::PDF_REVISADO, true);
        $media->save();
    }

    /**
     * La foto normalizada reemplaza a la original solo si pesa menos. Como
     * siempre sale JPEG, un PNG o un HEIC cambia de nombre y de tipo: el
     * registro se actualiza junto con el archivo, y la URL sale del registro.
     */
    private function normalizarImagen(Media $media, NormalizadorDeImagen $normalizador, bool $simulacro): void
    {
        $ruta = $media->getPath();
        $antes = is_file($ruta) ? (int) filesize($ruta) : 0;

        if ($antes === 0) {
            $this->anotar($media, new ResultadoCompresion(ResultadoCompresion::FALLO, 0, 0, 'El archivo no existe.'));

            return;
        }

        try {
            $temporal = $normalizador->normalizar($ruta, $media->collection_name);
        } catch (RuntimeException $error) {
            $this->anotar($media, new ResultadoCompresion(ResultadoCompresion::FALLO, $antes, $antes, $error->getMessage()));

            return;
        }

        $despues = (int) filesize($temporal);
        $mejora = $despues < $antes;
        $this->anotar($media, new ResultadoCompresion($mejora ? ResultadoCompresion::COMPRIMIDO : ResultadoCompresion::SIN_MEJORA, $antes, $despues));

        if ($simulacro) {
            @unlink($temporal);

            return;
        }

        if ($mejora) {
            $nuevoNombre = pathinfo($media->file_name, PATHINFO_FILENAME).'.jpg';
            $destino = dirname($ruta).'/'.$nuevoNombre;
            $parcial = $destino.'.tmp';

            // El temporal del sistema puede estar en otro disco: se copia al
            // lado del original y recién ahí se mueve encima, de un golpe.
            copy($temporal, $parcial);
            chmod($parcial, fileperms($ruta) & 0777);
            rename($parcial, $destino);

            if ($destino !== $ruta) {
                unlink($ruta);
            }

            $media->file_name = $nuevoNombre;
            $media->mime_type = 'image/jpeg';
            $media->size = $despues;
        }

        @unlink($temporal);

        $media->setCustomProperty(FileUploadService::IMAGEN_NORMALIZADA, true);
        $media->save();
    }

    private function anotar(Media $media, ResultadoCompresion $resultado): void
    {
        $this->revisados++;
        $this->bytesAntes += $resultado->bytesAntes;

        match (true) {
            $resultado->comprimido() => $this->comprimidos++,
            $resultado->estado === ResultadoCompresion::SIN_MEJORA => $this->sinMejora++,
            default => $this->fallidos++,
        };

        $this->bytesAhorrados += $resultado->bytesAhorrados();

        if ($this->output->isVerbose() || ! $resultado->concluyente()) {
            $detalle = $resultado->concluyente()
                ? Number::fileSize($resultado->bytesAntes, 1).' → '.Number::fileSize($resultado->bytesDespues, 1)
                : (string) $resultado->detalle;

            $this->line("  #{$media->getKey()} {$media->file_name}: {$resultado->estado} ({$detalle})");
        }
    }
}
