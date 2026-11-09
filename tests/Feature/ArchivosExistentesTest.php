<?php

use App\Enums\TipoDocumento;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * Un archivo subido «a la antigua»: directo por MediaLibrary, en `{id}/` y
 * con el nombre que traía.
 */
function archivoAntiguo(UploadedFile $archivo): Media
{
    /** @var VehiculoDocumento $documento */
    $documento = Vehiculo::factory()->create()->documentos()->create(['tipo' => TipoDocumento::Soat]);

    return $documento->addMedia($archivo)->toMediaCollection('archivo');
}

function pdfAntiguo(int $bytes = 50_000): Media
{
    return archivoAntiguo(UploadedFile::fake()->createWithContent('Tarjeta ABC-123.pdf', "%PDF-1.4\n".str_repeat('0', $bytes)."\n%%EOF\n"));
}

function ghostscriptQueReduceA(int $bytes): void
{
    Process::fake(function (PendingProcess $proceso) use ($bytes) {
        foreach ((array) $proceso->command as $argumento) {
            if (str_starts_with($argumento, '-sOutputFile=')) {
                file_put_contents(substr($argumento, strlen('-sOutputFile=')), "%PDF-1.4\n".str_repeat('0', $bytes)."\n%%EOF\n");
            }
        }

        return Process::result();
    });
}

it('only reports the savings on a dry run', function (): void {
    $media = pdfAntiguo(50_000);
    $original = file_get_contents($media->getPath());
    ghostscriptQueReduceA(10_000);

    artisan('uploads:compress-existing', ['--dry-run' => true])->assertSuccessful();

    expect(file_get_contents($media->getPath()))->toBe($original)
        ->and($media->fresh()->hasCustomProperty(FileUploadService::PDF_REVISADO))->toBeFalse()
        ->and(glob(dirname($media->getPath()).'/*'))->toHaveCount(1);
});

it('compresses the pdfs that were already uploaded, once', function (): void {
    $media = pdfAntiguo(50_000);
    ghostscriptQueReduceA(10_000);

    artisan('uploads:compress-existing')->assertSuccessful();

    $comprimido = filesize($media->getPath());

    expect($comprimido)->toBeLessThan(11_000)
        ->and($media->fresh()->size)->toBe($comprimido)
        ->and($media->fresh()->getPath())->toBe($media->getPath());

    // La segunda pasada no lo vuelve a tocar: ya quedó anotado.
    ghostscriptQueReduceA(100);
    artisan('uploads:compress-existing')->assertSuccessful();

    expect(filesize($media->getPath()))->toBe($comprimido);
});

it('normalizes the photos that were already uploaded', function (): void {
    $media = archivoAntiguo(UploadedFile::fake()->image('Foto SOAT.png', 3000, 2000));
    $rutaVieja = $media->getPath();

    artisan('uploads:compress-existing', ['--solo' => 'imagenes'])->assertSuccessful();

    $media->refresh();

    expect($media->file_name)->toBe('Foto-SOAT.jpg')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and(getimagesize($media->getPath())[0])->toBe(1920)
        ->and($media->size)->toBe(filesize($media->getPath()))
        ->and(file_exists($rutaVieja))->toBeFalse();
});

it('moves the old files to the module and month folders', function (): void {
    $media = pdfAntiguo();
    $contenido = file_get_contents($media->getPath());

    expect($media->getPathRelativeToRoot())->toBe("{$media->id}/Tarjeta-ABC-123.pdf");

    artisan('uploads:reorganize-existing', ['--dry-run' => true])->assertSuccessful();
    Storage::disk('public')->assertExists("{$media->id}/Tarjeta-ABC-123.pdf");

    artisan('uploads:reorganize-existing')->assertSuccessful();

    $media->refresh();
    $esperada = 'vehiculos/'.$media->created_at->format('Y/m')."/{$media->uuid}/{$media->uuid}.pdf";

    expect($media->getPathRelativeToRoot())->toBe($esperada)
        ->and($media->name)->toBe('Tarjeta ABC-123')
        ->and(file_get_contents($media->getPath()))->toBe($contenido);

    Storage::disk('public')->assertMissing("{$media->id}");
});
