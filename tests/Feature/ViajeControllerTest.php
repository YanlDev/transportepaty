<?php

use App\Enums\FaseCiclo;
use App\Enums\TipoCarga;
use App\Enums\TipoGuia;
use App\Models\Conductor;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
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

function ubicacion(string $codigo): int
{
    return Ubicacion::query()->where('codigo', $codigo)->value('id');
}

/**
 * La colección de media valida el mime por contenido, así que un fake lleno de
 * ceros sería rechazado: hacen falta archivos mínimos pero reales.
 */
function guiaPdf(string $nombre = 'guia.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $nombre,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
    );
}

function guiaXml(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'guia.xml',
        '<?xml version="1.0" encoding="UTF-8"?><DespatchAdvice><ID>T001-1</ID></DespatchAdvice>',
    );
}

it('exige sesión para ver los viajes', function (): void {
    $this->get(route('viajes.index'))->assertRedirect(route('login'));
});

it('no deja entrar a un conductor', function (): void {
    actingAs(actorConRol('conductor'))->get(route('viajes.index'))->assertForbidden();
});

it('lista los viajes del más reciente al más antiguo', function (): void {
    Viaje::factory()->create(['fecha_salida' => '2026-07-10']);
    Viaje::factory()->create(['fecha_salida' => '2026-07-20']);

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('viajes/index')
            ->has('viajes.data', 2)
            ->where('viajes.data.0.fecha_salida', '2026-07-20')
        );
});

it('encuentra un viaje por el número de cualquiera de sus dos guías', function (string $buscar): void {
    Viaje::factory()->conGuias('T001-4567', 'V001-8899')->create();
    Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['buscar' => $buscar]))
        ->assertInertia(fn (Assert $page) => $page->has('viajes.data', 1));
})->with(['T001-4567', '4567', 'V001-8899', '8899']);

it('encuentra un viaje por la placa del tracto', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'VEP-856']);
    Viaje::factory()->create(['tracto_id' => $tracto->id]);
    Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['buscar' => 'VEP856']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.tracto.placa', 'VEP-856')
        );
});

it('encuentra un viaje por el apellido del conductor', function (): void {
    $conductor = Conductor::factory()->create(['apellidos' => 'Quispe Mamani']);
    Viaje::factory()->create(['conductor_id' => $conductor->id]);
    Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['buscar' => 'quispe']))
        ->assertInertia(fn (Assert $page) => $page->has('viajes.data', 1));
});

it('separa los viajes en curso de los cerrados', function (): void {
    Viaje::factory()->create();
    Viaje::factory()->completado()->create();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['estado' => 'en_curso']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.en_curso', true)
        );

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['estado' => 'completados']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('viajes.data', 1)
            ->where('viajes.data.0.en_curso', false)
        );
});

it('registra un viaje y deduce su fase de la carga', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), [
            'tracto_id' => $tracto->id,
            'tipo_carga' => TipoCarga::Concentrado->value,
            'origen_id' => ubicacion('san_rafael'),
            'destino_id' => ubicacion('pisco'),
            'fecha_salida' => '2026-07-20',
        ])
        ->assertRedirect();

    $viaje = Viaje::query()->firstOrFail();

    expect($viaje->fase)->toBe(FaseCiclo::MinaPisco)
        ->and($viaje->estaEnCurso())->toBeTrue();
});

it('no admite un viaje que empieza y termina en el mismo punto', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), [
            'tracto_id' => $tracto->id,
            'tipo_carga' => TipoCarga::Concentrado->value,
            'origen_id' => ubicacion('pisco'),
            'destino_id' => ubicacion('pisco'),
            'fecha_salida' => '2026-07-20',
        ])
        ->assertSessionHasErrors('destino_id');
});

it('no admite una llegada anterior a la salida', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), [
            'tracto_id' => $tracto->id,
            'tipo_carga' => TipoCarga::Concentrado->value,
            'origen_id' => ubicacion('san_rafael'),
            'destino_id' => ubicacion('pisco'),
            'fecha_salida' => '2026-07-20',
            'fecha_llegada' => '2026-07-18',
        ])
        ->assertSessionHasErrors('fecha_llegada');
});

it('no admite como carreta un vehículo que es tracto', function (): void {
    $tracto = Vehiculo::factory()->create();
    $otroTracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), [
            'tracto_id' => $tracto->id,
            'carreta_id' => $otroTracto->id,
            'tipo_carga' => TipoCarga::Concentrado->value,
            'origen_id' => ubicacion('san_rafael'),
            'destino_id' => ubicacion('pisco'),
            'fecha_salida' => '2026-07-20',
        ])
        ->assertSessionHasErrors('carreta_id');
});

it('conserva con qué carreta se hizo el viaje aunque después se reasigne', function (): void {
    // El motivo de copiar los fierros en el viaje en vez de referenciar la
    // asignación vigente.
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'BWC-987']);
    $viaje = Viaje::factory()->completado()->create(['carreta_id' => $carreta->id]);

    Vehiculo::factory()->carreta()->create(['placa' => 'NUE-VA1']);

    expect($viaje->fresh()->carreta->placa)->toBe('BWC-987');
});

it('deja corregir un viaje y cerrarlo con su fecha de llegada', function (): void {
    $viaje = Viaje::factory()->create(['fecha_salida' => '2026-07-20']);

    actingAs(actorConRol('admin'))
        ->put(route('viajes.update', $viaje), [
            'tracto_id' => $viaje->tracto_id,
            'tipo_carga' => $viaje->tipo_carga->value,
            'origen_id' => $viaje->origen_id,
            'destino_id' => $viaje->destino_id,
            'fecha_salida' => '2026-07-20',
            'fecha_llegada' => '2026-07-23',
            'numero_guia_transportista' => 'V001-9090',
        ])
        ->assertRedirect();

    $viaje->refresh();

    expect($viaje->estaEnCurso())->toBeFalse()
        ->and($viaje->duracionDias())->toBe(3)
        ->and($viaje->numero_guia_transportista)->toBe('V001-9090');
});

it('adjunta el archivo de una guía y lo expone en la pantalla', function (): void {
    Storage::fake('public');

    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.guias.store', $viaje), [
            'tipo' => TipoGuia::Transportista->value,
            'archivo' => guiaPdf('grt.pdf'),
        ])
        ->assertRedirect();

    expect($viaje->fresh()->getFirstMedia(TipoGuia::Transportista->coleccion()))->not->toBeNull();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.edit', $viaje))
        ->assertInertia(fn (Assert $page) => $page
            ->where('guias.1.tipo', TipoGuia::Transportista->value)
            ->where('guias.1.es_pdf', true)
            ->whereNot('guias.1.url', null)
        );
});

it('acepta el XML de la guía electrónica', function (): void {
    Storage::fake('public');

    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.guias.store', $viaje), [
            'tipo' => TipoGuia::Remitente->value,
            'archivo' => guiaXml(),
        ])
        ->assertSessionHasNoErrors();

    expect($viaje->fresh()->getFirstMedia(TipoGuia::Remitente->coleccion()))->not->toBeNull();
});

it('reemplaza el archivo al volver a subir la misma guía', function (): void {
    Storage::fake('public');

    $viaje = Viaje::factory()->create();

    foreach (['primera.pdf', 'segunda.pdf'] as $nombre) {
        actingAs(actorConRol('admin'))->post(route('viajes.guias.store', $viaje), [
            'tipo' => TipoGuia::Remitente->value,
            'archivo' => guiaPdf($nombre),
        ]);
    }

    $media = $viaje->fresh()->getMedia(TipoGuia::Remitente->coleccion());

    expect($media)->toHaveCount(1)
        ->and($media->first()->file_name)->toContain('segunda');
});

it('quita el archivo de una guía pero conserva su número', function (): void {
    Storage::fake('public');

    $viaje = Viaje::factory()->conGuias()->create();

    actingAs(actorConRol('admin'))->post(route('viajes.guias.store', $viaje), [
        'tipo' => TipoGuia::Remitente->value,
        'archivo' => guiaPdf('grr.pdf'),
    ]);

    actingAs(actorConRol('admin'))
        ->delete(route('viajes.guias.destroy', [$viaje, TipoGuia::Remitente->value]))
        ->assertRedirect();

    $viaje->refresh();

    expect($viaje->getFirstMedia(TipoGuia::Remitente->coleccion()))->toBeNull()
        ->and($viaje->numero_guia_remitente)->not->toBeNull();
});

it('devuelve 404 ante un tipo de guía que no existe', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('viajes.guias.destroy', [$viaje, 'inventada']))
        ->assertNotFound();
});

it('deja al visor consultar pero no tocar', function (): void {
    $viaje = Viaje::factory()->create();
    $visor = actorConRol('visor');

    actingAs($visor)->get(route('viajes.index'))->assertSuccessful();
    actingAs($visor)->get(route('viajes.edit', $viaje))->assertSuccessful();
    actingAs($visor)->get(route('viajes.create'))->assertForbidden();
    actingAs($visor)->delete(route('viajes.destroy', $viaje))->assertForbidden();
    actingAs($visor)->post(route('viajes.guias.store', $viaje), [
        'tipo' => TipoGuia::Remitente->value,
        'archivo' => guiaPdf('grr.pdf'),
    ])->assertForbidden();
});
