<?php

use App\Enums\Permiso;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\Sunat\EmisionEnDuda;
use App\Services\Sunat\EmisionGre;
use App\Services\Sunat\EmisionRechazada;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Las respuestas reales de SUNAT de la emisión EG03-12623 (28-09-2026) y el
 * cuerpo que envió SOL al emitirla.
 *
 * @return array<string, mixed>
 */
function emisionGrabada(): array
{
    return json_decode((string) file_get_contents(base_path('tests/Fixtures/sunat/emision-eg03-12623.json')), true);
}

/**
 * @param  array<string, mixed>  $extra  respuestas adicionales o reemplazos de api-cpe
 */
function fingirSunatDeLaEmision(array $extra = []): void
{
    $grabada = emisionGrabada();

    fingirSol(tokenSol(), api: $extra + [
        '/comprobantes/20609175789-09-T001-34367' => Http::response($grabada['guia']),
        '/BYM852/numPlaca' => Http::response($grabada['tracto']),
        '/VES982/numPlaca' => Http::response($grabada['carreta']),
        '/numLicencia' => Http::response(['errors' => [['cod' => 2037, 'msg' => 'No encontramos']]], 422),
        '/personas/02424215' => Http::response($grabada['persona']),
        '/contribuyentes/20601239079' => Http::response(['datosContribuyente' => $grabada['pagador']['datosContribuyente']]),
        '/contribuyentes/20364000643' => Http::response(['datosContribuyente' => ['desRazonSocial' => 'EMPRESA DE TRANSPORTES PATY SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA']]),
        '/31-EG03/emision' => Http::response($grabada['emision_respuesta']),
        '/descarga/pdf' => Http::response(['pdf' => base64_encode('%PDF-1.5 no es un PDF de verdad')]),
    ]);
}

/** @return array{0: Vehiculo, 1: Vehiculo, 2: Conductor} */
function unidadDeLaEmision(): array
{
    return [
        Vehiculo::factory()->create(['placa' => 'BYM-852', 'tipo' => TipoVehiculo::Tracto]),
        Vehiculo::factory()->create(['placa' => 'VES-982', 'tipo' => TipoVehiculo::Carreta]),
        Conductor::factory()->create(['nombres' => 'Adolfo', 'apellidos' => 'Mamani Masco', 'documento' => '02424215', 'licencia' => 'U02424215']),
    ];
}

beforeEach(function (): void {
    config([
        'services.sunat_sol.ruc' => '20364000643',
        'services.sunat_sol.usuario' => 'PATYGRE',
        'services.sunat_sol.clave' => 'secreta',
        'services.sunat_sol.serie_gre' => 'EG03',
        'services.sunat_sol.registro_mtc' => '210122CNG',
    ]);
});

$guias = [['ruc' => '20609175789', 'serie' => 'T001', 'numero' => 34367]];

it('arma exactamente el mismo cuerpo que envió SOL en la emisión grabada', function () use ($guias): void {
    fingirSunatDeLaEmision();
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    $cuerpo = app(EmisionGre::class)->armar($guias, $tracto, $carreta, $conductor, '2026-09-28', EmisionGre::PAGADOR_TERCERO, '20601239079');

    expect($cuerpo)->toEqual(emisionGrabada()['emision_enviada']);
});

it('emite, lee el número que asigna SUNAT y registra el viaje con el PDF', function () use ($guias): void {
    fingirSunatDeLaEmision();
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    $resultado = app(EmisionGre::class)->emitir($guias, $tracto, $carreta, $conductor, '2026-09-28', EmisionGre::PAGADOR_TERCERO, '20601239079');

    expect($resultado['numero_gr'])->toBe('EG03-00012623');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/gre/comprobantes/31-EG03/emision')
        && $request['docRelacionado'][0]['numDocumento'] === '34367');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '20364000643-31-EG03-12623/descarga/pdf'));
});

it('no emite si la GR-remitente ya tiene GR-transportista en Transpaty', function () use ($guias): void {
    fingirSunatDeLaEmision();
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();
    Viaje::factory()->create(['numero_gr' => 'EG03-00012623', 'guias_remitente' => [['numero' => 'T001 - 34367', 'ruc' => '20609175789']]]);

    expect(fn () => app(EmisionGre::class)->emitir($guias, $tracto, $carreta, $conductor, '2026-09-28', EmisionGre::PAGADOR_TERCERO, '20601239079'))
        ->toThrow(RuntimeException::class, 'ya tiene GR-transportista: EG03-00012623');

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/emision'));
});

it('no emite si el MTC no tiene TUCE para la placa', function () use ($guias): void {
    fingirSunatDeLaEmision(['/BYM852/numPlaca' => Http::response(['errors' => [['cod' => 1, 'msg' => 'no']]], 422)]);
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    expect(fn () => app(EmisionGre::class)->emitir($guias, $tracto, $carreta, $conductor, '2026-09-28', EmisionGre::PAGADOR_TERCERO, '20601239079'))
        ->toThrow(RuntimeException::class, 'no tiene TUCE para la placa BYM852');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/emision'));
});

it('devuelve el motivo cuando SUNAT rechaza la GR', function () use ($guias): void {
    fingirSunatDeLaEmision(['/31-EG03/emision' => Http::response(['errors' => [['cod' => 3001, 'msg' => 'El peso no coincide']]], 422)]);
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    expect(fn () => app(EmisionGre::class)->emitir($guias, $tracto, $carreta, $conductor, '2026-09-28', EmisionGre::PAGADOR_TERCERO, '20601239079'))
        ->toThrow(EmisionRechazada::class, 'El peso no coincide');
});

it('nunca reintenta la emisión cuando SUNAT responde con error de servidor', function () use ($guias): void {
    fingirSunatDeLaEmision(['/31-EG03/emision' => Http::response(null, 500)]);
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    expect(fn () => app(EmisionGre::class)->emitir($guias, $tracto, $carreta, $conductor, '2026-09-28', EmisionGre::PAGADOR_TERCERO, '20601239079'))
        ->toThrow(EmisionEnDuda::class);

    expect(collect(Http::recorded())->filter(fn (array $par) => str_contains($par[0]->url(), '/emision'))->count())->toBe(1);
});

it('solo deja emitir a quien tiene el permiso de emitir GR', function () use ($guias): void {
    Role::findOrCreate('operador', 'web')->givePermissionTo(
        Permission::findOrCreate(Permiso::ViajesRegistrar->value, 'web'),
    );
    Permission::findOrCreate(Permiso::ViajesEmitir->value, 'web');
    Http::fake();
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    Pest\Laravel\actingAs(actorConRol('operador'))
        ->postJson(route('viajes.emitir.store'), [
            'guias' => $guias,
            'tracto_id' => $tracto->id,
            'carreta_id' => $carreta->id,
            'conductor_id' => $conductor->id,
            'fecha_traslado' => '2026-09-28',
            'pagador' => EmisionGre::PAGADOR_TERCERO,
            'ruc_pagador' => '20601239079',
        ])
        ->assertForbidden();

    Http::assertNothingSent();
});

it('responde con el número emitido', function () use ($guias): void {
    Role::findOrCreate('admin', 'web');
    fingirSunatDeLaEmision();
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    Pest\Laravel\actingAs(actorConRol('admin'))
        ->postJson(route('viajes.emitir.store'), [
            'guias' => $guias,
            'tracto_id' => $tracto->id,
            'carreta_id' => $carreta->id,
            'conductor_id' => $conductor->id,
            'fecha_traslado' => '2026-09-28',
            'pagador' => EmisionGre::PAGADOR_TERCERO,
            'ruc_pagador' => '20601239079',
        ])
        ->assertOk()
        ->assertJson(['estado' => 'emitida', 'numeroGr' => 'EG03-00012623']);
});

it('avisa que la emisión quedó en duda y no la da por emitida', function () use ($guias): void {
    Role::findOrCreate('admin', 'web');
    fingirSunatDeLaEmision(['/31-EG03/emision' => Http::response(null, 503)]);
    [$tracto, $carreta, $conductor] = unidadDeLaEmision();

    Pest\Laravel\actingAs(actorConRol('admin'))
        ->postJson(route('viajes.emitir.store'), [
            'guias' => $guias,
            'tracto_id' => $tracto->id,
            'carreta_id' => $carreta->id,
            'conductor_id' => $conductor->id,
            'fecha_traslado' => '2026-09-28',
            'pagador' => EmisionGre::PAGADOR_TERCERO,
            'ruc_pagador' => '20601239079',
        ])
        ->assertStatus(504)
        ->assertJsonPath('estado', 'en_duda');
});
