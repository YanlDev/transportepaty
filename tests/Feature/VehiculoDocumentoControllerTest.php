<?php

use App\Enums\TipoDocumento;
use App\Models\Vehiculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Storage::fake('public');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosDocumento(array $overrides = []): array
{
    return array_merge([
        'tipo' => TipoDocumento::Soat->value,
        'numero' => 'SOAT-001',
        'fecha_emision' => null,
        'fecha_vencimiento' => now()->addYear()->toDateString(),
        'observaciones' => null,
        'archivo' => archivoPdf(),
    ], $overrides);
}

/**
 * Un PDF mínimo pero real: la colección de media valida el mime por contenido,
 * así que un fake lleno de ceros sería rechazado.
 */
function archivoPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'documento.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
    );
}

it('stores a document for the vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.documentos.store', $vehiculo), datosDocumento())
        ->assertSessionHasNoErrors();

    expect($vehiculo->documentos()->count())->toBe(1)
        ->and($vehiculo->documentos()->sole()->getFirstMedia('archivo'))->not->toBeNull();
});

it('renews a document by replacing the existing one instead of failing', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.documentos.store', $vehiculo), datosDocumento())
        ->assertSessionHasNoErrors();

    // Subir el SOAT renovado no debe chocar con el índice único: actualiza.
    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.documentos.store', $vehiculo), datosDocumento([
            'numero' => 'SOAT-002',
            'fecha_vencimiento' => now()->addYears(2)->toDateString(),
        ]))
        ->assertSessionHasNoErrors();

    $documento = $vehiculo->documentos()->sole();

    expect($documento->numero)->toBe('SOAT-002')
        ->and($documento->fecha_vencimiento?->toDateString())->toBe(now()->addYears(2)->toDateString())
        ->and($documento->getMedia('archivo'))->toHaveCount(1);
});

it('rejects a soat for a carreta', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.documentos.store', $carreta), datosDocumento())
        ->assertSessionHasErrors('tipo');

    expect($carreta->documentos()->count())->toBe(0);
});

it('accepts the same document type on a tracto', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('vehiculos.documentos.store', $tracto), datosDocumento())
        ->assertSessionHasNoErrors();
});
