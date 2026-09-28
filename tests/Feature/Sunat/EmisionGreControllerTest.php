<?php

use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    config([
        'services.sunat_sol.ruc' => '20364000643',
        'services.sunat_sol.usuario' => 'PATYGRE',
        'services.sunat_sol.clave' => 'secreta',
    ]);
});

/** @return array<string, mixed> */
function guiaSunat(string $transportista = '20364000643', string $fecha = 'now'): array
{
    return [
        'codEstado' => '01',
        'desEstado' => 'Emitido',
        'emisor' => ['desNombre' => 'MINSUR S.A.'],
        'receptor' => ['desNombre' => 'MINSUR S.A.', 'numDocIdentidad' => '20100136741'],
        'traslado' => [
            'fecInicioTraslado' => now()->parse($fecha)->format('Y-m-d\T00:00:00'),
            'numPesoBruto' => 30.049,
            'codUnidadMedidaPb' => 'TNE',
            'numBultosPallets' => 20,
            'desMotivoTraslado' => 'Traslado entre establecimientos de la misma empresa',
            'partida' => ['direccion' => ['desDireccion' => 'ASIENTO MINERO  SAN RAFAEL', 'desDistrito' => 'ANTAUTA', 'desProvincia' => 'MELGAR ', 'desDepartamento' => 'PUNO ']],
            'llegada' => ['direccion' => ['desDireccion' => 'PANAMERICANA SUR KM.238', 'desDistrito' => 'PARACAS', 'desProvincia' => 'PISCO ', 'desDepartamento' => 'ICA ']],
            'transportista' => ['numDocIdentidad' => $transportista],
        ],
    ];
}

it('muestra la pantalla de emisión a quien registra viajes', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('viajes.emitir'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('viajes/emitir')
            ->where('sunatConfigurado', true)
            ->has('tractos')
            ->has('conductores'));
});

it('no deja al visor consultar SUNAT', function (): void {
    Http::fake();

    actingAs(actorConRol('visor'))
        ->getJson(route('viajes.emitir.guia', ['ruc' => '20100136741', 'serie' => 'T007', 'numero' => 10089]))
        ->assertForbidden();

    Http::assertNothingSent();
});

it('resume la GR-remitente de SUNAT para la vista previa', function (): void {
    fingirSol(tokenSol(), api: ['/gre/comprobantes/20100136741-09-T007-10089' => Http::response(guiaSunat())]);

    actingAs(actorConRol('admin'))
        ->getJson(route('viajes.emitir.guia', ['ruc' => '20100136741', 'serie' => 't007', 'numero' => 10089]))
        ->assertOk()
        ->assertJson([
            'serie' => 'T007',
            'numero' => 10089,
            'completa' => true,
            'remitente' => 'MINSUR S.A.',
            'partida' => 'ASIENTO MINERO SAN RAFAEL (ANTAUTA, MELGAR, PUNO)',
            'llegada' => 'PANAMERICANA SUR KM.238 (PARACAS, PISCO, ICA)',
            'peso' => 30.049,
            'bultos' => 20,
            'avisos' => [],
        ]);
});

it('avisa si la GR-remitente ya tiene GR-transportista, es de otro transportista y es vieja', function (): void {
    Viaje::factory()->create([
        'numero_gr' => 'EG03-00012569',
        'guias_remitente' => [['numero' => 'T007 - 010088', 'ruc' => '20100136741']],
    ]);
    fingirSol(tokenSol(), api: [
        '/gre/comprobantes/20100136741-09-T007-10088' => Http::response(guiaSunat('20100228191', '-2 years')),
    ]);

    $avisos = actingAs(actorConRol('admin'))
        ->getJson(route('viajes.emitir.guia', ['ruc' => '20100136741', 'serie' => 'T007', 'numero' => 10088]))
        ->assertOk()
        ->json('avisos');

    expect($avisos)->toHaveCount(3)
        ->and($avisos[0])->toContain('EG03-00012569')
        ->and($avisos[1])->toContain('20100228191')
        ->and($avisos[2])->toContain('más de 30 días');
});

it('responde 404 con mensaje cuando SUNAT no encuentra la GR-remitente', function (): void {
    fingirSol(tokenSol());

    actingAs(actorConRol('admin'))
        ->getJson(route('viajes.emitir.guia', ['ruc' => '20100136741', 'serie' => 'T007', 'numero' => 1]))
        ->assertNotFound()
        ->assertJsonPath('mensaje', 'SUNAT no encontró la GR-remitente T007-1 del RUC 20100136741.');
});

it('valida el RUC y la serie antes de ir a SUNAT', function (): void {
    Http::fake();

    actingAs(actorConRol('admin'))
        ->getJson(route('viajes.emitir.guia', ['ruc' => '123', 'serie' => 'T7', 'numero' => 5]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ruc', 'serie']);

    Http::assertNothingSent();
});

it('trae el TUCE de la placa y verifica el DNI del conductor en RENIEC', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'TCK-922']);
    $conductor = Conductor::factory()->create(['nombres' => 'Adolfo', 'apellidos' => 'Mamani Masco', 'documento' => '02424215', 'licencia' => 'U02424215']);
    $otro = Conductor::factory()->create(['nombres' => 'Juan', 'apellidos' => 'Perez', 'documento' => '02424216']);
    fingirSol(tokenSol(), api: [
        '/TCK922/numPlaca' => Http::response(['numTucChv' => '21M22000519E', 'indTucChv' => '2', 'indVigencia' => '1']),
        '/numLicencia' => Http::response(['errors' => [['cod' => 2037, 'msg' => 'No encontramos la licencia']]], 422),
        '/personas/' => Http::response(['apePaterno' => 'MAMANI', 'apeMaterno' => 'MASCO', 'nomPerNat' => 'ADOLFO']),
    ]);
    $admin = actorConRol('admin');

    actingAs($admin)
        ->getJson(route('viajes.emitir.vehiculo', $tracto))
        ->assertOk()
        ->assertExactJson(['placa' => 'TCK-922', 'numero' => '21M22000519E', 'origen' => 'mtc', 'vence' => null, 'placaEnSunat' => true]);

    actingAs($admin)
        ->getJson(route('viajes.emitir.conductor', $conductor))
        ->assertOk()
        ->assertExactJson([
            'dni' => ['encontrado' => true, 'nombre' => 'MAMANI MASCO ADOLFO', 'coincide' => true],
            'licencia' => ['encontrada' => false, 'mensaje' => 'No encontramos la licencia'],
        ]);

    // DNI mal cargado: RENIEC devuelve a otra persona.
    actingAs($admin)
        ->getJson(route('viajes.emitir.conductor', $otro))
        ->assertOk()
        ->assertJsonPath('dni.coincide', false)
        ->assertJsonPath('dni.nombre', 'MAMANI MASCO ADOLFO');
});
