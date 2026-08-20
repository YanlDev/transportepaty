<?php

use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
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
 * Copia real de una GRE de SUNAT que Paty emite; el contenido importa porque
 * el controller de verdad la parsea, no solo valida el mime.
 */
function gr(string $fixture = 'gr-minsur-concentrado.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $fixture,
        file_get_contents(base_path("tests/Fixtures/guias/{$fixture}")),
    );
}

it('redirects guests to login', function (): void {
    $this->get(route('viajes.index'))->assertRedirect(route('login'));
});

it('lets a visor see the list but not upload', function (): void {
    actingAs(actorConRol('visor'))
        ->get(route('viajes.index'))
        ->assertSuccessful();

    actingAs(actorConRol('visor'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertForbidden();
});

it('creates a viaje from a real GR and resolves the tracto, carreta and conductor', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'CAM-703']);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'VGK-987']);
    $conductor = Conductor::factory()->create(['documento' => '42466432']);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    expect($viaje->numero_gr)->toBe('EG03-00011965')
        ->and($viaje->fecha_traslado->toDateString())->toBe('2026-07-30')
        ->and($viaje->cliente)->toBe('MINSUR S.A.')
        ->and((float) $viaje->peso)->toBe(30.045)
        ->and($viaje->unidad_peso)->toBe('TNE')
        ->and($viaje->tracto_id)->toBe($tracto->id)
        ->and($viaje->carreta_id)->toBe($carreta->id)
        ->and($viaje->conductor_id)->toBe($conductor->id)
        ->and($viaje->getFirstMedia('archivo'))->not->toBeNull();
});

it('records the subcontratador as the cliente, not the remitente printed on the GR', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-ajeper-subcontratado-crisar.pdf')]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    // La GR dice «AJEPER S.A.» como remitente, pero quien contrata y paga a
    // Paty es el subcontratador: ese es el vínculo comercial real.
    expect($viaje->cliente)->toBe('CRISAR LOGISTICA S.A.C.')
        ->and($viaje->cliente_ruc)->toBe('20603930844');
});

it('uppercases the cliente so the same company does not look like two different ones', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-san-lorenzo-multi-guia.pdf')]])
        ->assertSessionHasNoErrors();

    // El PDF trae «Ceramica San Lorenzo S.A.C.» en mayúscula y minúscula
    // mezcladas; sin normalizar, la misma empresa aparecería como dos
    // clientes distintos según cómo la haya tipeado quien emitió la GR.
    expect(Viaje::query()->sole()->cliente)->toBe('CERAMICA SAN LORENZO S.A.C.');
});

it('derives the destination region from the tail of the address', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    expect($viaje->regionDestino())->toBe('ICA');

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index'))
        ->assertInertia(fn ($page) => $page->where('viajes.data.0.destino_region', 'ICA'));
});

it('keeps the raw plate and driver text even when nothing matches the padrón', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    expect($viaje->tracto_id)->toBeNull()
        ->and($viaje->carreta_id)->toBeNull()
        ->and($viaje->conductor_id)->toBeNull()
        ->and($viaje->placa_tracto)->toBe('CAM703')
        ->and($viaje->conductor_nombre)->toBe('PANDURO AMARO ADAN RONAL');
});

it('updates the same viaje instead of duplicating when the same GR is uploaded again', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    expect(Viaje::query()->count())->toBe(1);
});

it('skips a file that is not a recognisable GR without failing the rest of the batch', function (): void {
    $archivoInvalido = UploadedFile::fake()->createWithContent(
        'no-es-una-gr.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
    );

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr(), $archivoInvalido]])
        ->assertSessionHasNoErrors();

    expect(Viaje::query()->count())->toBe(1);
});

it('lets an admin delete a viaje', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    actingAs(actorConRol('admin'))
        ->delete(route('viajes.destroy', $viaje))
        ->assertSessionHasNoErrors();

    expect(Viaje::query()->count())->toBe(0);
});

it('resolves a viaje uploaded before the tracto, carreta or conductor existed once the padrón catches up', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();
    expect($viaje->tracto_id)->toBeNull();

    // El vehículo se crea después de subir la GR, como le pasó al usuario.
    $tracto = Vehiculo::factory()->create(['placa' => 'CAM-703']);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'VGK-987']);
    $conductor = Conductor::factory()->create(['documento' => '42466432']);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.resolver'))
        ->assertSessionHasNoErrors();

    $viaje->refresh();

    expect($viaje->tracto_id)->toBe($tracto->id)
        ->and($viaje->carreta_id)->toBe($carreta->id)
        ->and($viaje->conductor_id)->toBe($conductor->id);
});

it('never overwrites a match that already existed when resolving pending viajes', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'CAM-703']);
    $otroTracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();
    expect($viaje->tracto_id)->toBe($tracto->id);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.resolver'))
        ->assertSessionHasNoErrors();

    expect($viaje->refresh()->tracto_id)->toBe($tracto->id)
        ->and($viaje->tracto_id)->not->toBe($otroTracto->id);
});

it('counts pending viajes for the index and forbids a visor from resolving', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index'))
        ->assertInertia(fn ($page) => $page->where('pendientes', 1));

    actingAs(actorConRol('visor'))
        ->post(route('viajes.resolver'))
        ->assertForbidden();
});

it('defaults a new viaje to Particular and lets an admin reclassify it', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();
    expect($viaje->tipo_carga)->toBe(TipoCarga::Particular);

    actingAs(actorConRol('admin'))
        ->patch(route('viajes.actualizarTipoCarga', $viaje), ['tipo_carga' => TipoCarga::Concentrado->value])
        ->assertSessionHasNoErrors();

    expect($viaje->refresh()->tipo_carga)->toBe(TipoCarga::Concentrado);
});

it('forbids a visor from reclassifying a viaje and rejects the ride-status-only cargo types', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    actingAs(actorConRol('visor'))
        ->patch(route('viajes.actualizarTipoCarga', $viaje), ['tipo_carga' => TipoCarga::Concentrado->value])
        ->assertForbidden();

    actingAs(actorConRol('admin'))
        ->patch(route('viajes.actualizarTipoCarga', $viaje), ['tipo_carga' => TipoCarga::Vacio->value])
        ->assertSessionHasErrors('tipo_carga');
});

it('forbids a visor from the manual entry form and from submitting it', function (): void {
    $tracto = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('viajes.manual.create'))
        ->assertForbidden();

    actingAs(actorConRol('visor'))
        ->post(route('viajes.manual.store'), datosViajeManual($tracto, $conductor))
        ->assertForbidden();

    expect(Viaje::query()->count())->toBe(0);
});

/**
 * @return array<string, mixed>
 */
function datosViajeManual(Vehiculo $tracto, Conductor $conductor, array $overrides = []): array
{
    return [...[
        'numero_gr' => 'T001-24879',
        'fecha_traslado' => '2026-07-30',
        'origen' => 'Planta Ilo',
        'direccion_destino' => 'Jr. Piura 123 - Ilo - Ilo',
        'departamento_destino' => 'MOQUEGUA',
        'cliente' => 'MINSUR S.A.',
        'destinatario' => 'MINSUR S.A.',
        'peso' => 30.5,
        'unidad_peso' => 'TNE',
        'tracto_id' => $tracto->id,
        'conductor_id' => $conductor->id,
        'tipo_carga' => TipoCarga::Concentrado->value,
    ], ...$overrides];
}

it('lets an admin register a viaje manually when the remitente already emitted the GRE', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'CAM-703']);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'VGK-987']);
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.manual.create'))
        ->assertSuccessful();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.manual.store'), datosViajeManual($tracto, $conductor, [
            'carreta_id' => $carreta->id,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('viajes.index'));

    $viaje = Viaje::query()->sole();

    expect($viaje->numero_gr)->toBe('T001-24879')
        ->and($viaje->fecha_traslado->toDateString())->toBe('2026-07-30')
        ->and($viaje->tracto_id)->toBe($tracto->id)
        ->and($viaje->placa_tracto)->toBe($tracto->placa)
        ->and($viaje->carreta_id)->toBe($carreta->id)
        ->and($viaje->placa_carreta)->toBe($carreta->placa)
        ->and($viaje->conductor_id)->toBe($conductor->id)
        ->and($viaje->conductor_nombre)->toBe(strtoupper("{$conductor->apellidos} {$conductor->nombres}"))
        ->and($viaje->tipo_carga)->toBe(TipoCarga::Concentrado)
        ->and($viaje->destino)->toBe('JR. PIURA 123 - ILO - ILO - MOQUEGUA')
        ->and($viaje->regionDestino())->toBe('MOQUEGUA')
        ->and($viaje->getFirstMedia('archivo'))->toBeNull();
});

it('leaves the carreta empty when a manual viaje has none', function (): void {
    $tracto = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.manual.store'), datosViajeManual($tracto, $conductor))
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    expect($viaje->carreta_id)->toBeNull()
        ->and($viaje->placa_carreta)->toBeNull();
});

it('rejects a manual viaje with a GR number that already exists', function (): void {
    $tracto = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.manual.store'), datosViajeManual($tracto, $conductor))
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.manual.store'), datosViajeManual($tracto, $conductor))
        ->assertSessionHasErrors('numero_gr');

    expect(Viaje::query()->count())->toBe(1);
});

it('rejects the ride-status-only cargo types on a manual viaje', function (): void {
    $tracto = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.manual.store'), datosViajeManual($tracto, $conductor, [
            'tipo_carga' => TipoCarga::Vacio->value,
        ]))
        ->assertSessionHasErrors('tipo_carga');

    expect(Viaje::query()->count())->toBe(0);
});

it('finds a viaje by placa, cliente or GR number', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr(), gr('gr-san-lorenzo-multi-guia.pdf')]])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['buscar' => 'CAM703']))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1));

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['buscar' => 'San Lorenzo']))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1));

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['buscar' => 'EG03-00011965']))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1));
});

it('finds a viaje by destino', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $destino = Viaje::query()->sole()->destino;
    $fragmento = mb_substr($destino, 0, 8);

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['buscar' => $fragmento]))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1));
});
