<?php

use App\Enums\PosicionFoto;
use App\Enums\TipoDocumento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\VehiculoFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Storage::fake('public');

    foreach (['admin', 'visor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function admin(): User
{
    return User::factory()->create()->assignRole('admin');
}

it('allows an admin to upload a vehicle photo', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(admin())
        ->post(route('vehiculos.fotos.store', $vehiculo), [
            'posicion' => PosicionFoto::Frente->value,
            'archivo' => UploadedFile::fake()->image('frente.jpg', 800, 600),
        ])
        ->assertRedirect();

    $foto = $vehiculo->fotos()->first();

    expect($foto)->not->toBeNull()
        ->and($foto->posicion)->toBe(PosicionFoto::Frente)
        ->and($foto->getFirstMedia('imagen'))->not->toBeNull();
});

it('forbids a viewer from uploading a photo', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(User::factory()->create()->assignRole('visor'))
        ->post(route('vehiculos.fotos.store', $vehiculo), [
            'posicion' => PosicionFoto::Frente->value,
            'archivo' => UploadedFile::fake()->image('frente.jpg'),
        ])
        ->assertForbidden();

    expect($vehiculo->fotos()->count())->toBe(0);
});

it('requires an image file for a photo', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(admin())
        ->post(route('vehiculos.fotos.store', $vehiculo), [
            'posicion' => PosicionFoto::Frente->value,
        ])
        ->assertSessionHasErrors('archivo');
});

it('lets an admin delete a photo', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $foto = $vehiculo->fotos()->create(['posicion' => PosicionFoto::Frente]);
    $foto->addMedia(UploadedFile::fake()->image('f.jpg'))->toMediaCollection('imagen');

    actingAs(admin())
        ->delete(route('vehiculos.fotos.destroy', [$vehiculo, $foto]))
        ->assertRedirect();

    expect(VehiculoFoto::find($foto->id))->toBeNull();
});

it('allows an admin to upload a document with an expiry date', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(admin())
        ->post(route('vehiculos.documentos.store', $vehiculo), [
            'tipo' => TipoDocumento::Soat->value,
            'numero' => 'SOAT-123',
            'fecha_vencimiento' => '2027-01-31',
            'archivo' => UploadedFile::fake()->image('soat.jpg'),
        ])
        ->assertRedirect();

    $documento = $vehiculo->documentos()->first();

    expect($documento)->not->toBeNull()
        ->and($documento->tipo)->toBe(TipoDocumento::Soat)
        ->and($documento->fecha_vencimiento->format('Y-m-d'))->toBe('2027-01-31')
        ->and($documento->getFirstMedia('archivo'))->not->toBeNull();
});

it('allows a document without an expiry date', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(admin())
        ->post(route('vehiculos.documentos.store', $vehiculo), [
            'tipo' => TipoDocumento::TarjetaPropiedad->value,
            'archivo' => UploadedFile::fake()->image('tarjeta.jpg'),
        ])
        ->assertRedirect();

    expect(VehiculoDocumento::first()->fecha_vencimiento)->toBeNull();
});

it('requires a type and a file for a document', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(admin())
        ->post(route('vehiculos.documentos.store', $vehiculo), [])
        ->assertSessionHasErrors(['tipo', 'archivo']);
});

it('limits documents to 3 on the show page and reports the total', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    foreach (range(1, 5) as $i) {
        $vehiculo->documentos()->create([
            'tipo' => TipoDocumento::Otro,
            'nombre' => "Doc {$i}",
        ]);
    }

    actingAs(admin())
        ->get(route('vehiculos.show', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/show')
            ->has('documentos', 3)
            ->where('documentosTotal', 5)
        );
});

it('lists every document on the documents page', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    foreach (range(1, 5) as $i) {
        $vehiculo->documentos()->create(['tipo' => TipoDocumento::Otro]);
    }

    actingAs(admin())
        ->get(route('vehiculos.documentos.index', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/documentos')
            ->has('documentos', 5)
        );
});

it('prevents deleting a photo through a vehicle it does not belong to', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $otro = Vehiculo::factory()->create();
    $foto = $vehiculo->fotos()->create(['posicion' => PosicionFoto::Frente]);

    actingAs(admin())
        ->delete(route('vehiculos.fotos.destroy', [$otro, $foto]))
        ->assertNotFound();
});
