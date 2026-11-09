<?php

namespace App\Jobs;

use App\Services\Archivos\CompresorPdf;
use App\Services\Archivos\ResultadoCompresion;
use App\Services\FileUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Recomprime con Ghostscript un PDF recién subido. Corre aparte de la
 * subida, que ya terminó bien: pase lo que pase acá —no hay Ghostscript, el
 * PDF no mejora, el archivo ya se borró— el documento sigue disponible tal
 * como llegó, y el trabajo termina sin error dejando el motivo en el log.
 */
class CompressPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Por encima del timeout de Ghostscript y por debajo del `retry_after` de la cola. */
    public int $timeout = 80;

    /** Si el documento se borró antes de que le toque, no hay nada que hacer. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Media $media)
    {
        $this->onQueue((string) config('uploads.pdf.cola'));
    }

    public function handle(CompresorPdf $compresor, FileUploadService $subidas): void
    {
        $media = $this->media;

        if (! $subidas->debeComprimirse($media)) {
            return;
        }

        $resultado = $compresor->comprimir($media->getPath());

        if ($resultado->concluyente()) {
            $media->size = $resultado->comprimido() ? $resultado->bytesDespues : $media->size;
            $media->setCustomProperty(FileUploadService::PDF_REVISADO, true);
            $media->save();

            return;
        }

        $contexto = ['media' => $media->getKey(), 'detalle' => $resultado->detalle];

        if ($resultado->estado === ResultadoCompresion::SIN_GHOSTSCRIPT) {
            Log::warning('PDF sin comprimir: Ghostscript no está instalado en el servidor.', $contexto);

            return;
        }

        Log::warning('PDF sin comprimir: Ghostscript no pudo procesarlo. Se conserva el original.', $contexto);
    }
}
