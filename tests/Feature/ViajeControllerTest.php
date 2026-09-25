<?php

use App\Enums\TipoCarga;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\ImportadorViaje;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

/**
 * Pasado el tope de PHP, el servidor descarta el cuerpo entero antes de que
 * Laravel lo vea y el usuario recibía «archivos es obligatorio», como si no
 * hubiera adjuntado nada. Validando por debajo del límite el mensaje dice lo
 * que realmente pasó.
 */
it('rechaza un lote más grande de lo que aguanta el servidor', function (): void {
    $lote = array_fill(0, 21, gr());

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => $lote])
        ->assertSessionHasErrors('archivos');

    expect(Viaje::query()->count())->toBe(0);
});

it('rechaza una GR más pesada de lo que acepta la subida', function (): void {
    $pesada = UploadedFile::fake()->create('gr-gigante.pdf', 3072, 'application/pdf');

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [$pesada]])
        ->assertSessionHasErrors('archivos.0');

    expect(Viaje::query()->count())->toBe(0);
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
        ->and($viaje->getFirstMedia('archivo'))->not->toBeNull()
        // La guía remitente referenciada es T007-9590: serie de concentrado.
        ->and($viaje->tipo_carga)->toBe(TipoCarga::Concentrado);
});

it('creates a viaje from a GRE de Bienes Fiscalizados even though it has no peso', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'TCK-922']);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'VDI-980']);
    $conductor = Conductor::factory()->create(['documento' => '02439078']);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-bienes-fiscalizados.pdf')]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    expect($viaje->numero_gr)->toBe('G010-00000142')
        ->and($viaje->fecha_traslado->toDateString())->toBe('2026-08-19')
        ->and($viaje->cliente)->toBe('SGS DEL PERU S.A.C.')
        ->and($viaje->destinatario)->toBe('SGS DEL PERU S.A.C.')
        ->and((float) $viaje->peso)->toBe(0.0)
        ->and($viaje->tracto_id)->toBe($tracto->id)
        ->and($viaje->carreta_id)->toBe($carreta->id)
        ->and($viaje->conductor_id)->toBe($conductor->id)
        ->and($viaje->getFirstMedia('archivo'))->not->toBeNull();
});

it('clasifica un viaje de Minsur como Materiales cuando la guía remitente es serie T012', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-minsur-ransa-peso-sin-decimales.pdf')]])
        ->assertSessionHasNoErrors();

    expect(Viaje::query()->sole()->tipo_carga)->toBe(TipoCarga::Materiales);
});

it('no reclasifica un viaje de Minsur que se resube si ya tenía un tipo de carga distinto de Particular', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-minsur-concentrado.pdf')]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();
    $viaje->update(['tipo_carga' => TipoCarga::Particular]);

    // Resubir la misma GR (mismo numero_gr) actualiza el viaje, no lo crea de
    // nuevo — la clasificación automática solo corre en la creación.
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-minsur-concentrado.pdf')]])
        ->assertSessionHasNoErrors();

    expect($viaje->refresh()->tipo_carga)->toBe(TipoCarga::Particular);
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

it('links an imported GR to the client padrón by RUC', function (): void {
    $cliente = Cliente::factory()->create(['ruc' => '20100136741']);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-minsur-concentrado.pdf')]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    // El texto de la GR se conserva: el padrón solo agrega el enlace.
    expect($viaje->cliente_id)->toBe($cliente->id)
        ->and($viaje->cliente)->toBe('MINSUR S.A.');
});

it('leaves cliente_id null when the RUC is not in the padrón, and fills it on reintentar', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-minsur-concentrado.pdf')]])
        ->assertSessionHasNoErrors();

    expect(Viaje::query()->sole()->cliente_id)->toBeNull();

    // Se da de alta después, como pasa siempre: reintentar cierra el hueco.
    $cliente = Cliente::factory()->create(['ruc' => '20100136741']);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.resolver'))
        ->assertSessionHasNoErrors();

    expect(Viaje::query()->sole()->cliente_id)->toBe($cliente->id);
});

it('collapses the spacing of the forma societaria so «MINSUR S. A.» is the same client as «MINSUR S.A.»', function (): void {
    // Las GR reales de Minsur llegan con las dos grafías: sin emparejarlas,
    // el mismo cliente se parte en dos barras del tablero y en dos opciones
    // del filtro de viajes.
    expect(ImportadorViaje::normalizarRazonSocial('MINSUR S. A.'))->toBe('MINSUR S.A.')
        ->and(ImportadorViaje::normalizarRazonSocial('Minsur S.A.'))->toBe('MINSUR S.A.')
        ->and(ImportadorViaje::normalizarRazonSocial('CRISAR LOGISTICA S. A. C.'))->toBe('CRISAR LOGISTICA S.A.C.')
        ->and(ImportadorViaje::normalizarRazonSocial('TRANSPORTES E. I. R. L.'))->toBe('TRANSPORTES E.I.R.L.');
});

it('leaves the branch suffix of a client alone when normalising', function (): void {
    // El paréntesis distingue sedes reales, no es ruido de tipeo: quitarlo
    // fundiría clientes que sí son distintos puntos.
    expect(ImportadorViaje::normalizarRazonSocial('COMERCIALIZADORA CODISAL S.A.C. (JULIACA)'))
        ->toBe('COMERCIALIZADORA CODISAL S.A.C. (JULIACA)')
        ->and(ImportadorViaje::normalizarRazonSocial('GUZMAN REVILLA CHRISTOPHER CHRISTIAN'))
        ->toBe('GUZMAN REVILLA CHRISTOPHER CHRISTIAN');
});

it('exposes every guía remitente referenced by the GR-transportista', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-san-lorenzo-multi-guia.pdf')]])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index'))
        ->assertInertia(fn ($page) => $page->where('viajes.data.0.guias_remitente', [
            ['numero' => 'T003 - 164953', 'ruc' => '20307146798'],
            ['numero' => 'T001 - 243466', 'ruc' => '20307146798'],
        ]));
});

it('derives the destination city from the address (distrito, not departamento)', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr()]])
        ->assertSessionHasNoErrors();

    $viaje = Viaje::query()->sole();

    expect($viaje->ciudadOrigen())->toBe('ANTAUTA')
        ->and($viaje->ciudadDestino())->toBe('PARACAS');

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.origen_ciudad', 'ANTAUTA')
            ->where('viajes.data.0.destino_ciudad', 'PARACAS'));
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

it('matches the conductor even when the padron lost the leading zero of his DNI', function (): void {
    // El padrón se cargó desde una hoja de cálculo que se comió el cero, y la
    // GR trae el DNI completo: es el mismo documento y debe matchear.
    $conductor = Conductor::factory()->create(['documento' => '2301443']);

    $viaje = Viaje::factory()->create([
        'conductor_id' => null,
        'conductor_dni' => '02301443',
    ]);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.resolver'))
        ->assertSessionHasNoErrors();

    expect($viaje->refresh()->conductor_id)->toBe($conductor->id);
});

it('does not confuse two conductores whose DNIs only differ by a leading zero', function (): void {
    Conductor::factory()->create(['documento' => '02301443']);
    $viaje = Viaje::factory()->create([
        'conductor_id' => null,
        'conductor_dni' => '12301443',
    ]);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.resolver'))
        ->assertSessionHasNoErrors();

    expect($viaje->refresh()->conductor_id)->toBeNull();
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
    // No usa el fixture default (gr-minsur-concentrado.pdf): ese sí se
    // autoclasifica ahora (ver ImportadorViajeTest / ClasificarCargaMinsur),
    // así que no sirve para probar el default "sin clasificar".
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr('gr-combustible-destinatario-partido.pdf')]])
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
        ->and($viaje->ciudadDestino())->toBe('ILO')
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

it('filters the list by cliente, tipo_carga and destino_ciudad', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('viajes.store'), ['archivos' => [gr(), gr('gr-san-lorenzo-multi-guia.pdf')]])
        ->assertSessionHasNoErrors();

    $minsur = Viaje::query()->where('cliente', 'MINSUR S.A.')->sole();

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['cliente' => 'MINSUR S.A.']))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1)
            ->where('viajes.data.0.cliente', 'MINSUR S.A.'));

    // La GR de Minsur se autoclasificó como Concentrado (serie T007); la de
    // San Lorenzo se queda en el default Particular — filtrar por
    // Concentrado debe traer solo la primera.
    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['tipo_carga' => TipoCarga::Concentrado->value]))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1)
            ->where('viajes.data.0.tipo_carga', TipoCarga::Concentrado->value));

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['destino_ciudad' => $minsur->ciudadDestino()]))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1)
            ->where('viajes.data.0.destino_ciudad', $minsur->ciudadDestino()));

    // Combinar filtros con la búsqueda de texto también debe funcionar.
    actingAs(actorConRol('admin'))
        ->get(route('viajes.index', ['cliente' => 'MINSUR S.A.', 'buscar' => 'CAM703']))
        ->assertInertia(fn ($page) => $page->has('viajes.data', 1));
});

/**
 * El listado se ordenaba por id, que es el orden de importación de las GR y no
 * su correlativo: dentro de un mismo día las filas salían salteadas.
 */
it('orders by fecha and then by the GR correlativo, not by import order', function (): void {
    foreach (['EG03-00012410', 'EG03-00012414', 'EG03-00012409'] as $numero) {
        Viaje::factory()->create([
            'numero_gr' => $numero,
            'fecha_traslado' => '2026-09-11',
        ]);
    }

    actingAs(actorConRol('admin'))
        ->get(route('viajes.index'))
        ->assertInertia(fn ($page) => $page
            ->where('viajes.data.0.numero_gr', 'EG03-00012414')
            ->where('viajes.data.1.numero_gr', 'EG03-00012410')
            ->where('viajes.data.2.numero_gr', 'EG03-00012409')
        );
});

/**
 * El buscador del listado recarga todo menos los catálogos de los selectores:
 * esos no dependen de lo que se escribe y armarlos en cada búsqueda era
 * recorrer los destinos de todos los viajes por cada tecla.
 */
it('filters the list without rebuilding the selector catalogs on a search reload', function (): void {
    Viaje::factory()->create(['placa_tracto' => 'ABC123']);
    Viaje::factory()->create(['placa_tracto' => 'XYZ999']);

    $consultas = [];
    DB::listen(function ($query) use (&$consultas): void {
        $consultas[] = $query->sql;
    });

    actingAs(actorConRol('admin'))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'viajes/index',
            'X-Inertia-Partial-Except' => 'tiposCarga,clientes,ciudadesDestino',
        ])
        ->get(route('viajes.index', ['buscar' => 'abc']))
        ->assertSuccessful()
        ->assertJsonPath('props.viajes.total', 1)
        ->assertJsonPath('props.filtros.buscar', 'abc')
        ->assertJsonMissingPath('props.clientes')
        ->assertJsonMissingPath('props.ciudadesDestino');

    expect(collect($consultas)->filter(fn (string $sql): bool => str_contains($sql, 'distinct')))->toBeEmpty();
});

/**
 * Volver a subir el PDF de una GR anulada no la registra de nuevo ni la
 * revive: sigue siendo la misma fila, y sigue anulada.
 */
it('does not duplicate nor revive an anulada GR when its PDF is uploaded again', function (): void {
    $admin = actorConRol('admin');

    actingAs($admin)->post(route('viajes.store'), ['archivos' => [gr()]]);
    $viaje = Viaje::query()->sole();

    actingAs($admin)->post(route('viajes.anular', $viaje), ['motivo' => 'Reemitida']);
    actingAs($admin)->post(route('viajes.store'), ['archivos' => [gr()]])->assertSessionHasNoErrors();

    expect(Viaje::query()->conAnuladas()->count())->toBe(1)
        ->and(Viaje::query()->count())->toBe(0)
        ->and(Viaje::query()->conAnuladas()->sole()->estaAnulada())->toBeTrue();
});
