<?php

use App\Enums\TipoDocumentoConductor;
use App\Models\Conductor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Storage::fake('public');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDocumentoConductor(array $overrides = []): array
{
    return array_merge([
        'tipo' => TipoDocumentoConductor::LicenciaConducir->value,
        'numero' => 'Q-12345678',
        'fecha_emision' => null,
        'fecha_vencimiento' => now()->addYear()->toDateString(),
        'observaciones' => null,
        'archivo' => pdfDeConductor(),
    ], $overrides);
}

/**
 * Un PDF mínimo pero real: la colección de media valida el mime por contenido.
 */
function pdfDeConductor(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'licencia.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
    );
}

it('guarda el documento del conductor con su archivo', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor())
        ->assertSessionHasNoErrors();

    expect($conductor->documentos()->count())->toBe(1)
        ->and($conductor->documentos()->sole()->getFirstMedia('archivo'))->not->toBeNull();
});

/**
 * Solo puede haber un documento de cada tipo: renovar la licencia reemplaza la
 * anterior en vez de dejar dos filas compitiendo.
 */
it('renueva el documento existente en vez de duplicarlo', function (): void {
    $conductor = Conductor::factory()->create();
    $actor = actorConRol('admin');

    actingAs($actor)
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor([
            'numero' => 'Q-11111111',
        ]))
        ->assertSessionHasNoErrors();

    actingAs($actor)
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor([
            'numero' => 'Q-22222222',
        ]))
        ->assertSessionHasNoErrors();

    expect($conductor->documentos()->count())->toBe(1)
        ->and($conductor->documentos()->sole()->numero)->toBe('Q-22222222');
});

it('exige el archivo: un documento sin escanear no sirve de nada', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor([
            'archivo' => null,
        ]))
        ->assertSessionHasErrors('archivo');

    expect($conductor->documentos()->count())->toBe(0);
});

it('rechaza un vencimiento anterior a la emisión', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor([
            'fecha_emision' => '2026-09-10',
            'fecha_vencimiento' => '2026-09-09',
        ]))
        ->assertSessionHasErrors('fecha_vencimiento');
});

it('deja fuera de cargar documentos a quien no administra', function (): void {
    $conductor = Conductor::factory()->create();

    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor())
            ->assertForbidden();
    }

    expect($conductor->documentos()->count())->toBe(0);
});

it('elimina el documento del conductor', function (): void {
    $conductor = Conductor::factory()->create();
    $actor = actorConRol('admin');

    actingAs($actor)
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor())
        ->assertSessionHasNoErrors();

    $documento = $conductor->documentos()->sole();

    actingAs($actor)
        ->delete(route('conductores.documentos.destroy', [$conductor, $documento]))
        ->assertSessionHasNoErrors();

    expect($conductor->documentos()->count())->toBe(0);
});

it('deja fuera de eliminar documentos a quien no administra', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor())
        ->assertSessionHasNoErrors();

    $documento = $conductor->documentos()->sole();

    foreach (['visor', 'conductor', 'contador'] as $rol) {
        actingAs(actorConRol($rol))
            ->delete(route('conductores.documentos.destroy', [$conductor, $documento]))
            ->assertForbidden();
    }

    expect($conductor->documentos()->count())->toBe(1);
});

/**
 * La corrección rápida del vencimiento: el archivo escaneado está bien, lo que
 * se cargó mal —o se renovó— es la fecha.
 */
it('corrige el vencimiento sin tocar el archivo ni el resto del documento', function (): void {
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('conductores.documentos.store', $conductor), datosDocumentoConductor())
        ->assertSessionHasNoErrors();

    $documento = $conductor->documentos()->sole();
    $mediaOriginal = $documento->getFirstMedia('archivo');

    actingAs(actorConRol('admin'))
        ->patch(route('conductores.documentos.vencimiento', [$conductor, $documento]), [
            'fecha_vencimiento' => '2030-01-31',
        ])
        ->assertSessionHasNoErrors();

    $documento->refresh();

    expect($documento->fecha_vencimiento->toDateString())->toBe('2030-01-31')
        ->and($documento->numero)->toBe('Q-12345678')
        ->and($documento->getFirstMedia('archivo')?->id)->toBe($mediaOriginal->id);
});

/**
 * Vaciar la fecha es un valor válido, no un error: el DNI no vence.
 */
it('deja vaciar el vencimiento', function (): void {
    $conductor = Conductor::factory()->create();
    $documento = $conductor->documentos()->create([
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_vencimiento' => '2027-08-02',
    ]);

    actingAs(actorConRol('admin'))
        ->patch(route('conductores.documentos.vencimiento', [$conductor, $documento]), [
            'fecha_vencimiento' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($documento->refresh()->fecha_vencimiento)->toBeNull();
});

it('rechaza un vencimiento anterior a la emisión del documento', function (): void {
    $conductor = Conductor::factory()->create();
    $documento = $conductor->documentos()->create([
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_emision' => '2026-03-10',
        'fecha_vencimiento' => '2031-03-10',
    ]);

    actingAs(actorConRol('admin'))
        ->patch(route('conductores.documentos.vencimiento', [$conductor, $documento]), [
            'fecha_vencimiento' => '2026-03-09',
        ])
        ->assertSessionHasErrors('fecha_vencimiento');

    expect($documento->refresh()->fecha_vencimiento->toDateString())->toBe('2031-03-10');
});

it('no deja al visor corregir el vencimiento', function (): void {
    $conductor = Conductor::factory()->create();
    $documento = $conductor->documentos()->create([
        'tipo' => TipoDocumentoConductor::LicenciaConducir,
        'fecha_vencimiento' => '2027-08-02',
    ]);

    actingAs(actorConRol('visor'))
        ->patch(route('conductores.documentos.vencimiento', [$conductor, $documento]), [
            'fecha_vencimiento' => '2030-01-31',
        ])
        ->assertForbidden();

    expect($documento->refresh()->fecha_vencimiento->toDateString())->toBe('2027-08-02');
});
