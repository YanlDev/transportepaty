<?php

use App\Enums\TipoDocumento;
use App\Jobs\CompressPdfJob;
use App\Models\Vehiculo;
use App\Services\Archivos\CompresorPdf;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * Un PDF «escaneado»: válido de cabecera y cola, con relleno para que pese.
 */
function pdfConPeso(int $bytes): string
{
    return "%PDF-1.4\n".str_repeat('0', $bytes)."\n%%EOF\n";
}

function subirPdfPesado(int $bytes = 50_000): Media
{
    Queue::fake();

    $documento = Vehiculo::factory()->create()->documentos()->create(['tipo' => TipoDocumento::Soat]);

    return app(FileUploadService::class)->guardar(
        $documento,
        UploadedFile::fake()->createWithContent('tarjeta.pdf', pdfConPeso($bytes)),
        'archivo',
    );
}

/**
 * Un Ghostscript de mentira: en vez de comprimir, escribe en el archivo de
 * salida lo que la prueba le diga.
 */
function ghostscriptQueEscribe(string $salida): void
{
    Process::fake(function (PendingProcess $proceso) use ($salida) {
        foreach ((array) $proceso->command as $argumento) {
            if (str_starts_with($argumento, '-sOutputFile=')) {
                file_put_contents(substr($argumento, strlen('-sOutputFile=')), $salida);
            }
        }

        return Process::result();
    });
}

it('replaces the pdf when the result is at least 10% smaller', function (): void {
    $media = subirPdfPesado(50_000);
    $comprimido = pdfConPeso(20_000);
    ghostscriptQueEscribe($comprimido);

    (new CompressPdfJob($media))->handle(app(CompresorPdf::class), app(FileUploadService::class));

    expect(file_get_contents($media->getPath()))->toBe($comprimido)
        ->and($media->fresh()->size)->toBe(strlen($comprimido))
        ->and($media->fresh()->getCustomProperty(FileUploadService::PDF_REVISADO))->toBeTrue()
        ->and(glob(dirname($media->getPath()).'/*'))->toHaveCount(1);
});

it('keeps the original when there is no real improvement', function (): void {
    $media = subirPdfPesado(50_000);
    $original = file_get_contents($media->getPath());
    // 4 % menos: por debajo del 10 % que se exige.
    ghostscriptQueEscribe(pdfConPeso(48_000));

    (new CompressPdfJob($media))->handle(app(CompresorPdf::class), app(FileUploadService::class));

    expect(file_get_contents($media->getPath()))->toBe($original)
        ->and($media->fresh()->size)->toBe(strlen($original))
        ->and(glob(dirname($media->getPath()).'/*'))->toHaveCount(1);
});

it('keeps the original when ghostscript does not produce a valid pdf', function (): void {
    $media = subirPdfPesado(50_000);
    $original = file_get_contents($media->getPath());
    ghostscriptQueEscribe(str_repeat('basura', 100));

    (new CompressPdfJob($media))->handle(app(CompresorPdf::class), app(FileUploadService::class));

    expect(file_get_contents($media->getPath()))->toBe($original)
        ->and($media->fresh()->hasCustomProperty(FileUploadService::PDF_REVISADO))->toBeFalse()
        ->and(glob(dirname($media->getPath()).'/*'))->toHaveCount(1);
});

it('keeps the original when ghostscript fails', function (): void {
    $media = subirPdfPesado(50_000);
    $original = file_get_contents($media->getPath());
    Process::fake(['*' => Process::result(errorOutput: 'Error: /syntaxerror', exitCode: 1)]);

    (new CompressPdfJob($media))->handle(app(CompresorPdf::class), app(FileUploadService::class));

    expect(file_get_contents($media->getPath()))->toBe($original);
});

it('really shrinks a scanned pdf with ghostscript', function (): void {
    $compresor = app(CompresorPdf::class);

    if (! $compresor->hayGhostscript() || ! extension_loaded('imagick')) {
        $this->markTestSkipped('Hace falta Ghostscript e Imagick para esta prueba.');
    }

    // Una «página escaneada»: ruido a 300 ppp, guardada como PDF.
    $ruta = tempnam(sys_get_temp_dir(), 'escaneo-').'.pdf';
    $pagina = new Imagick;
    $pagina->newPseudoImage(2480, 3508, 'plasma:fractal');
    $pagina->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
    $pagina->setImageResolution(300, 300);
    $pagina->setImageFormat('pdf');
    $pagina->setImageCompressionQuality(95);
    $pagina->writeImage($ruta);
    $antes = filesize($ruta);

    $resultado = $compresor->comprimir($ruta);

    expect($resultado->comprimido())->toBeTrue()
        ->and(filesize($ruta))->toBeLessThan((int) ($antes * 0.9))
        ->and(file_get_contents($ruta, length: 5))->toBe('%PDF-');

    unlink($ruta);
});
