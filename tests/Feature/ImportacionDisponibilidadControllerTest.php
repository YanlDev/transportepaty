<?php

use App\Models\EstadoUnidad;
use App\Models\Importacion;
use App\Models\Vehiculo;
use Database\Seeders\UbicacionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->seed(UbicacionSeeder::class);
});

/**
 * Copia el archivo real que sube el cliente por WhatsApp, tal como llegaría en
 * el formulario de subida.
 */
function excelReal(?string $nombre = null): UploadedFile
{
    $ruta = base_path('tests/Fixtures/disponibilidad-real.xlsx');
    $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    return new UploadedFile(
        $ruta,
        $nombre ?? 'Disponibilidad de unidades 29-07-2026 - Transportes Paty.xlsx',
        $mime,
        null,
        true,
    );
}

/**
 * Siembra algunas de las unidades que de verdad aparecen en el archivo fijo,
 * para que al menos unas filas se resuelvan y se pueda probar el camino
 * completo de confirmación.
 */
function sembrarUnidadesDelArchivo(): void
{
    Vehiculo::factory()->create(['placa' => 'BJF934']);
    Vehiculo::factory()->carreta()->create(['placa' => 'VEF-983']);
}

it('exige sesión para importar', function (): void {
    $this->get(route('importaciones.index'))->assertRedirect(route('login'));
});

it('no deja a un visor subir un archivo', function (): void {
    actingAs(actorConRol('visor'))
        ->get(route('importaciones.create'))
        ->assertForbidden();
});

it('sube el archivo real y saca la fecha del nombre', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))
        ->post(route('importaciones.store'), ['archivo' => excelReal()])
        ->assertRedirect();

    $importacion = Importacion::query()->firstOrFail();

    expect($importacion->fecha->toDateString())->toBe('2026-07-29')
        ->and($importacion->filas_totales)->toBeGreaterThanOrEqual(60)
        ->and($importacion->estaConfirmada())->toBeFalse();
});

it('respeta la fecha indicada en vez de la del nombre del archivo', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))
        ->post(route('importaciones.store'), [
            'archivo' => excelReal(),
            'fecha' => '2026-08-01',
        ]);

    expect(Importacion::query()->firstOrFail()->fecha->toDateString())->toBe('2026-08-01');
});

it('cae en el día de hoy cuando el nombre no trae fecha', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))
        ->post(route('importaciones.store'), [
            'archivo' => excelReal('disponibilidad.xlsx'),
        ]);

    expect(Importacion::query()->firstOrFail()->fecha->toDateString())
        ->toBe(now()->toDateString());
});

it('rechaza un archivo que no es un Excel', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))
        ->post(route('importaciones.store'), [
            'archivo' => UploadedFile::fake()->create('cosa.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('archivo');
});

it('muestra la previsualización con las filas leídas', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))->post(route('importaciones.store'), ['archivo' => excelReal()]);
    $importacion = Importacion::query()->firstOrFail();

    actingAs(actorConRol('admin'))
        ->get(route('importaciones.show', $importacion))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('importaciones/show')
            ->where('importacion.filas_totales', $importacion->filas_totales)
            ->has('filas', $importacion->filas_totales)
        );
});

it('deja alternar si una fila se incluye antes de confirmar', function (): void {
    Storage::fake('public');
    sembrarUnidadesDelArchivo();

    actingAs(actorConRol('admin'))->post(route('importaciones.store'), ['archivo' => excelReal()]);
    $importacion = Importacion::query()->with('filas')->firstOrFail();
    $fila = $importacion->filas->firstWhere('incluir', true);

    actingAs(actorConRol('admin'))
        ->patch(route('importaciones.filas.update', [$importacion, $fila]), ['incluir' => false])
        ->assertRedirect();

    expect($fila->fresh()->incluir)->toBeFalse();
});

it('confirma la importación y aplica las filas a la disponibilidad del día', function (): void {
    Storage::fake('public');
    sembrarUnidadesDelArchivo();

    actingAs(actorConRol('admin'))->post(route('importaciones.store'), ['archivo' => excelReal()]);
    $importacion = Importacion::query()->firstOrFail();

    actingAs(actorConRol('admin'))
        ->post(route('importaciones.confirmar', $importacion))
        ->assertRedirect(route('disponibilidad.index', ['fecha' => '2026-07-29']));

    expect($importacion->fresh()->estaConfirmada())->toBeTrue()
        ->and($importacion->filas_resueltas)->toBeGreaterThan(0)
        ->and(EstadoUnidad::query()->count())->toBe($importacion->filas_resueltas)
        ->and(EstadoUnidad::query()->whereHas('tracto', fn ($q) => $q->where('placa', 'BJF934'))->exists())
        ->toBeTrue();
});

it('no deja confirmar dos veces la misma importación', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))->post(route('importaciones.store'), ['archivo' => excelReal()]);
    $importacion = Importacion::query()->firstOrFail();

    actingAs(actorConRol('admin'))->post(route('importaciones.confirmar', $importacion));

    actingAs(actorConRol('admin'))
        ->post(route('importaciones.confirmar', $importacion))
        ->assertForbidden();
});

it('no deja eliminar una importación ya confirmada', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))->post(route('importaciones.store'), ['archivo' => excelReal()]);
    $importacion = Importacion::query()->firstOrFail();
    actingAs(actorConRol('admin'))->post(route('importaciones.confirmar', $importacion));

    actingAs(actorConRol('admin'))
        ->delete(route('importaciones.destroy', $importacion))
        ->assertForbidden();
});

it('deja descartar una importación que todavía no se confirmó', function (): void {
    Storage::fake('public');

    actingAs(actorConRol('admin'))->post(route('importaciones.store'), ['archivo' => excelReal()]);
    $importacion = Importacion::query()->firstOrFail();

    actingAs(actorConRol('admin'))
        ->delete(route('importaciones.destroy', $importacion))
        ->assertRedirect(route('importaciones.index'));

    expect(Importacion::query()->count())->toBe(0);
});
