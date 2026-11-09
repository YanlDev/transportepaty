<?php

use App\Enums\TipoDocumento;
use App\Jobs\CompressPdfJob;
use App\Models\LecturaKilometraje;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\Viaje;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Storage::fake('public');
});

function documentoSubidoDePrueba(): VehiculoDocumento
{
    return Vehiculo::factory()->create()->documentos()->create(['tipo' => TipoDocumento::Soat]);
}

function pdfSubidoDePrueba(string $nombre = 'SOAT escaneado.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $nombre,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
    );
}

it('resizes a large photo and stores it as a jpeg', function (): void {
    $lectura = LecturaKilometraje::factory()->create();
    $foto = UploadedFile::fake()->image('Tablero del camión.png', 4000, 3000);

    $media = app(FileUploadService::class)->guardar($lectura, $foto, 'foto', 'foto');

    [$ancho, $alto] = getimagesize($media->getPath());

    expect($ancho)->toBe(1920)
        ->and($alto)->toBe(1440)
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBe(filesize($media->getPath()));
});

it('does not enlarge a photo that is already small', function (): void {
    $lectura = LecturaKilometraje::factory()->create();

    $media = app(FileUploadService::class)->guardar($lectura, UploadedFile::fake()->image('chica.jpg', 800, 600), 'foto');

    expect(getimagesize($media->getPath())[0])->toBe(800);
});

it('names the file with a uuid inside the module and month folder', function (): void {
    $lectura = LecturaKilometraje::factory()->create();

    $media = app(FileUploadService::class)->guardar($lectura, UploadedFile::fake()->image('Tablero del camión.jpg', 640, 480), 'foto');

    expect($media->getPathRelativeToRoot())->toMatch('#^kilometraje/'.now()->format('Y/m').'/([0-9a-f-]{36})/\1\.jpg$#')
        ->and($media->name)->toBe('Tablero del camión')
        ->and($media->file_name)->not->toContain('Tablero');

    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
});

it('removes only its own folder when the file is deleted', function (): void {
    $servicio = app(FileUploadService::class);
    $una = $servicio->guardar(LecturaKilometraje::factory()->create(), UploadedFile::fake()->image('a.jpg'), 'foto');
    $otra = $servicio->guardar(LecturaKilometraje::factory()->create(), UploadedFile::fake()->image('b.jpg'), 'foto');

    $una->delete();

    Storage::disk('public')->assertMissing($una->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($otra->getPathRelativeToRoot());
});

it('rejects a file whose real type is not allowed', function (): void {
    $lectura = LecturaKilometraje::factory()->create();
    $disfrazado = UploadedFile::fake()->createWithContent('foto.jpg', "#!/bin/sh\necho hola\n");

    expect(fn () => app(FileUploadService::class)->guardar($lectura, $disfrazado, 'foto', 'foto'))
        ->toThrow(ValidationException::class);

    expect($lectura->getMedia('foto'))->toBeEmpty();
});

it('rejects a broken image with a validation error', function (): void {
    $lectura = LecturaKilometraje::factory()->create();
    // Cabecera de JPEG válida y nada más: pasa por imagen, pero no se puede leer.
    $rota = UploadedFile::fake()->createWithContent('rota.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00".str_repeat('x', 200));

    expect(fn () => app(FileUploadService::class)->guardar($lectura, $rota, 'foto', 'foto'))
        ->toThrow(ValidationException::class);
});

it('rejects a disallowed type through the form too', function (): void {
    foreach (['admin'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(actorConRol('admin'))
        ->post(route('vehiculos.documentos.store', $vehiculo), [
            'tipo' => TipoDocumento::Soat->value,
            'archivo' => UploadedFile::fake()->createWithContent('soat.pdf', 'esto no es un pdf'),
        ])
        ->assertSessionHasErrors('archivo');
});

it('queues the compression of an uploaded pdf', function (): void {
    Queue::fake();

    $media = app(FileUploadService::class)->guardar(documentoSubidoDePrueba(), pdfSubidoDePrueba(), 'archivo');

    expect($media->getPathRelativeToRoot())->toMatch('#^vehiculos/\d{4}/\d{2}/[0-9a-f-]{36}/[0-9a-f-]{36}\.pdf$#')
        ->and($media->name)->toBe('SOAT escaneado');

    Queue::assertPushed(CompressPdfJob::class, fn (CompressPdfJob $job): bool => $job->media->is($media));
});

it('does not compress the pdfs of excluded models', function (): void {
    Queue::fake();

    app(FileUploadService::class)->guardar(Viaje::factory()->create(), pdfSubidoDePrueba('gr.pdf'), 'archivo');

    Queue::assertNotPushed(CompressPdfJob::class);
});

it('does not compress an excluded document type', function (): void {
    Queue::fake();
    config(['uploads.exclusiones.tipos_documento' => [TipoDocumento::Soat->value]]);

    app(FileUploadService::class)->guardar(documentoSubidoDePrueba(), pdfSubidoDePrueba(), 'archivo');

    Queue::assertNotPushed(CompressPdfJob::class);
});

it('stores the pdf even when ghostscript is not installed', function (): void {
    config(['uploads.pdf.ghostscript.binario' => '/no/existe/gs']);
    Log::spy();
    $contenido = pdfSubidoDePrueba()->getContent();

    // La cola de las pruebas es síncrona: el job corre dentro de la subida.
    $media = app(FileUploadService::class)->guardar(documentoSubidoDePrueba(), pdfSubidoDePrueba(), 'archivo');

    expect(file_get_contents($media->getPath()))->toBe($contenido)
        ->and($media->fresh()->hasCustomProperty(FileUploadService::PDF_REVISADO))->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $mensaje): bool => str_contains($mensaje, 'Ghostscript no está instalado'))->once();
});
