<?php

use App\Enums\EstadoGre;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\PuntoTraslado;
use App\Models\Ubigeo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Storage::fake('local');
    Storage::disk('local')->put(
        'gre/certificado.pem',
        (string) file_get_contents(base_path('tests/Fixtures/certificado-prueba.pem'))
    );

    config()->set('gre.emisor', [
        'ruc' => '20364000643',
        'razon_social' => 'EMPRESA DE TRANSPORTES PATY S.C.R.L.',
        'nombre_comercial' => 'TRANSPORTES PATY',
        'direccion' => 'MZ. C4 LOTE. 6Y7 LOTIZACION INDUSTRIAL HUACHIPA',
        'ubigeo' => '150118',
        'registro_mtc' => '210122CNG',
    ]);
    config()->set('gre.serie', 'V001');
    config()->set('gre.certificado', 'gre/certificado.pem');
    // Sin credenciales SOL: la guía se genera y firma, pero no se envía.
    config()->set('gre.api', [
        'client_id' => null,
        'client_secret' => null,
        'usuario_sol' => null,
        'clave_sol' => null,
    ]);

    Ubigeo::query()->insert([
        ['codigo' => '070101', 'distrito' => 'Callao', 'provincia' => 'Prov. Const. del Callao', 'departamento' => 'Callao', 'busqueda' => 'CALLAO PROV. CONST. DEL CALLAO CALLAO'],
        ['codigo' => '210802', 'distrito' => 'Antauta', 'provincia' => 'Melgar', 'departamento' => 'Puno', 'busqueda' => 'ANTAUTA MELGAR PUNO'],
    ]);
});

function adminGuias(): User
{
    return User::factory()->create()->assignRole('admin');
}

/** La misma placa en dos llamadas del helper es la misma unidad, no un duplicado. */
function unidad(string $placa, string $tuc): Vehiculo
{
    return Vehiculo::query()->firstOrCreate(
        ['placa' => $placa],
        Vehiculo::factory()->make(['placa' => $placa, 'tuc' => $tuc])->toArray(),
    );
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosGuia(array $overrides = []): array
{
    // `firstOrCreate`: el helper se llama más de una vez por prueba cuando se
    // emiten dos guías seguidas, y el RUC es único.
    $cliente = Cliente::query()->firstOrCreate(
        ['ruc' => '20100136741'],
        Cliente::factory()->make(['ruc' => '20100136741', 'razon_social' => 'MINSUR S.A.'])->toArray(),
    );

    return array_merge([
        'fecha_traslado' => now()->toDateString(),
        'cliente_id' => $cliente->id,
        'destinatario' => 'MINSUR S.A.',
        'destinatario_ruc' => '20100136741',
        'partida' => [
            'ubigeo' => '070101',
            'direccion' => 'AV. NESTOR GAMBETTA 3235 CALLAO',
            'nombre' => 'Planta Callao',
        ],
        'llegada' => [
            'ubigeo' => '210802',
            'direccion' => 'KM 102 ASIENTO MINERO SAN RAFAEL',
            'nombre' => 'Mina San Rafael',
        ],
        'tracto_id' => unidad('VEP793', '21M25000279E')->id,
        'carreta_id' => unidad('BRI984', '21M25000102E')->id,
        'conductor_id' => Conductor::query()->firstOrCreate(
            ['documento' => '01328149'],
            Conductor::factory()->make(['documento' => '01328149', 'licencia' => 'U01328149'])->toArray(),
        )->id,
        'peso' => '10150.000',
        'unidad_peso' => 'KGM',
        'tipo_carga' => 'concentrado',
        'motivo_traslado' => '04',
        'guias_remitente' => [['numero' => 'T012-855', 'ruc' => '20100136741']],
        'observaciones' => null,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('guias.create'))->assertRedirect(route('login'));
});

it('shows the form with the padron already loaded', function (): void {
    Vehiculo::factory()->create(['placa' => 'VEP793', 'tuc' => '21M25000279E', 'tipo' => 'tracto']);
    Conductor::factory()->create(['licencia' => 'U01328149']);
    Cliente::factory()->create();

    actingAs(adminGuias())
        ->get(route('guias.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guias/create')
            ->where('serie', 'V001')
            ->where('emisorConfigurado', true)
            ->has('clientes')
            ->has('tractos')
            ->has('conductores')
            ->has('motivosTraslado'));
});

it('forbids a visor from emitting', function (): void {
    $visor = User::factory()->create()->assignRole('visor');

    actingAs($visor)->get(route('guias.create'))->assertForbidden();
});

it('creates the viaje with a number taken from the serie', function (): void {
    actingAs(adminGuias())->post(route('guias.store'), datosGuia())->assertRedirect();

    expect(Viaje::first())
        ->numero_gr->toBe('V001-00000001')
        ->and(Viaje::first()->gre_estado)->toBe(EstadoGre::Generada);
});

it('numbers guides consecutively without repeating', function (): void {
    $admin = adminGuias();

    actingAs($admin)->post(route('guias.store'), datosGuia());
    actingAs($admin)->post(route('guias.store'), datosGuia());

    expect(Viaje::orderBy('id')->pluck('numero_gr')->all())
        ->toBe(['V001-00000001', 'V001-00000002']);
});

it('signs the XML and keeps it, so a failed send can be retried', function (): void {
    actingAs(adminGuias())->post(route('guias.store'), datosGuia());

    Storage::disk('local')->assertExists('gre/20364000643-31-V001-1.xml');

    $xml = (string) Storage::disk('local')->get('gre/20364000643-31-V001-1.xml');

    expect($xml)->toContain('<cbc:DespatchAdviceTypeCode')
        ->toContain('>31<')
        ->toContain('ds:Signature');
});

it('saves the places used, so the catalogue fills itself by emitting', function (): void {
    actingAs(adminGuias())->post(route('guias.store'), datosGuia());

    expect(PuntoTraslado::count())->toBe(2)
        ->and(PuntoTraslado::where('ubigeo', '210802')->first()->nombre)->toBe('Mina San Rafael');
});

it('reuses a place instead of duplicating it on every guide', function (): void {
    $admin = adminGuias();

    actingAs($admin)->post(route('guias.store'), datosGuia());
    actingAs($admin)->post(route('guias.store'), datosGuia());

    expect(PuntoTraslado::count())->toBe(2);
});

it('copies the TUC and licencia from the padron into the guide', function (): void {
    actingAs(adminGuias())->post(route('guias.store'), datosGuia());

    $xml = (string) Storage::disk('local')->get('gre/20364000643-31-V001-1.xml');

    expect($xml)->toContain('21M25000279E')
        ->toContain('21M25000102E')
        ->toContain('U01328149')
        ->toContain('210122CNG');
});

it('rejects an ubigeo that is not in the catalogue', function (): void {
    actingAs(adminGuias())
        ->post(route('guias.store'), datosGuia([
            'llegada' => ['ubigeo' => '999999', 'direccion' => 'NINGUNA PARTE', 'nombre' => null],
        ]))
        ->assertSessionHasErrors('llegada.ubigeo');

    expect(Viaje::count())->toBe(0);
});

it('rejects a destinatario RUC that is not 11 digits', function (): void {
    actingAs(adminGuias())
        ->post(route('guias.store'), datosGuia(['destinatario_ruc' => '2010013']))
        ->assertSessionHasErrors('destinatario_ruc');
});

it('refuses to emit a guide for a conductor without licencia', function (): void {
    actingAs(adminGuias())
        ->post(route('guias.store'), datosGuia([
            'conductor_id' => Conductor::factory()->create(['licencia' => null])->id,
        ]))
        ->assertRedirect();

    // El viaje queda creado pero rechazado: el correlativo ya se consumió y
    // saltearlo sería peor que dejar la guía marcada como fallida.
    expect(Viaje::first()->gre_estado)->toBe(EstadoGre::Rechazada)
        ->and(Viaje::first()->gre_respuesta['mensaje'])->toContain('licencia');
});

it('searches districts without caring about accents or order', function (): void {
    actingAs(adminGuias())
        ->getJson(route('ubigeos.buscar', ['buscar' => 'melgar antauta']))
        ->assertOk()
        ->assertJsonFragment(['codigo' => '210802', 'etiqueta' => 'Antauta, Melgar, Puno']);
});

it('does not search with a single letter', function (): void {
    actingAs(adminGuias())
        ->getJson(route('ubigeos.buscar', ['buscar' => 'a']))
        ->assertOk()
        ->assertExactJson([]);
});

it('lists the guides emitted from the system', function (): void {
    $admin = adminGuias();
    actingAs($admin)->post(route('guias.store'), datosGuia());

    actingAs($admin)
        ->get(route('guias.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guias/index')
            ->has('guias.data', 1)
            ->where('guias.data.0.numero_gr', 'V001-00000001')
            ->where('guias.data.0.estado', 'generada')
            ->where('guias.data.0.reintentable', true)
            ->where('credencialesConfiguradas', false));
});

it('keeps imported PDF trips out of the guides list', function (): void {
    Viaje::factory()->create(['numero_gr' => 'EG03-00012342']);

    actingAs(adminGuias())
        ->get(route('guias.index'))
        ->assertInertia(fn (Assert $page) => $page->has('guias.data', 0));
});

it('does not let an accepted guide be re-sent', function (): void {
    $viaje = Viaje::factory()->create([
        'numero_gr' => 'V001-00000009',
        'gre_estado' => EstadoGre::Aceptada,
    ]);

    actingAs(adminGuias())
        ->post(route('guias.reintentar', $viaje))
        ->assertRedirect();

    expect($viaje->fresh()->gre_estado)->toBe(EstadoGre::Aceptada);
});
