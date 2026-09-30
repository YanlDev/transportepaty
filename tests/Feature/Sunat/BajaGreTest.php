<?php

use App\Enums\MotivoBajaGre;
use App\Enums\Permiso;
use App\Models\Viaje;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
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

/**
 * La baja real de EG03-12631 hecha en SOL el 29-09-2026.
 *
 * @return array<string, mixed>
 */
function bajaGrabada(): array
{
    return json_decode((string) file_get_contents(base_path('tests/Fixtures/sunat/baja-eg03-12631.json')), true);
}

/** @return array<int, Request> */
function pedidosDeBaja(): array
{
    return collect(Http::recorded())
        ->map(fn (array $par): Request => $par[0])
        ->filter(fn (Request $pedido): bool => str_ends_with($pedido->url(), '/baja'))
        ->values()
        ->all();
}

it('da de baja la GR en SUNAT igual que SOL y la deja anulada', function (): void {
    $grabada = bajaGrabada();
    fingirSol(tokenSol(), api: ['-31-EG03-12631/baja' => Http::response($grabada['respuesta'])]);
    $admin = actorConRol('admin');
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012631']);

    actingAs($admin)
        ->post(route('viajes.anular', $viaje), ['baja_sunat' => MotivoBajaGre::AntesDeIniciarElTraslado->value])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');

    $pedidos = pedidosDeBaja();

    expect($pedidos)->toHaveCount(1)
        ->and($pedidos[0]->method())->toBe($grabada['metodo'])
        ->and($pedidos[0]->url())->toBe('https://api-cpe.sunat.gob.pe'.$grabada['ruta'])
        ->and($pedidos[0]->data())->toBe($grabada['cuerpo']);

    $viaje = Viaje::query()->conAnuladas()->findOrFail($viaje->id);

    expect($viaje->estaAnulada())->toBeTrue()
        ->and($viaje->anulada_por)->toBe($admin->id)
        ->and($viaje->baja_sunat_at?->toIso8601String())->toBe('2026-09-29T20:29:28+00:00')
        ->and($viaje->motivo_baja_sunat)->toBe(MotivoBajaGre::AntesDeIniciarElTraslado)
        ->and($viaje->motivo_anulacion)->toBe('Dada de baja en SUNAT: Antes de iniciar el traslado');
});

it('no anula nada si SUNAT rechaza la baja', function (): void {
    fingirSol(tokenSol(), api: ['/baja' => Http::response(['errors' => [['cod' => 1234, 'msg' => 'El comprobante ya se encuentra de baja']]], 422)]);
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012631']);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.anular', $viaje), ['baja_sunat' => '02'])
        ->assertSessionHasErrors(['baja_sunat' => 'SUNAT no la dio de baja: El comprobante ya se encuentra de baja']);

    $viaje->refresh();

    expect($viaje->estaAnulada())->toBeFalse()
        ->and($viaje->baja_sunat_at)->toBeNull();
});

it('no reintenta la baja si se corta la conexión', function (): void {
    $intentos = 0;
    fingirSol(tokenSol(), api: ['/baja' => function () use (&$intentos): never {
        $intentos++;

        throw new ConnectionException('corte');
    }]);
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012631']);

    actingAs(actorConRol('admin'))
        ->post(route('viajes.anular', $viaje), ['baja_sunat' => '02'])
        ->assertSessionHasErrors('baja_sunat');

    expect($intentos)->toBe(1)
        ->and($viaje->refresh()->estaAnulada())->toBeFalse();
});

it('pide el permiso de emitir GR para dar de baja en SUNAT', function (): void {
    Role::findOrCreate('operador', 'web')->givePermissionTo(
        Permission::findOrCreate(Permiso::ViajesAnular->value, 'web'),
    );
    Permission::findOrCreate(Permiso::ViajesEmitir->value, 'web');
    Http::fake();
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012631']);

    actingAs(actorConRol('operador'))
        ->post(route('viajes.anular', $viaje), ['baja_sunat' => '02'])
        ->assertForbidden();

    Http::assertNothingSent();
    expect($viaje->refresh()->estaAnulada())->toBeFalse();
});

it('rechaza un motivo de baja que SUNAT no tiene', function (): void {
    Http::fake();
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('viajes.anular', $viaje), ['baja_sunat' => '99'])
        ->assertSessionHasErrors('baja_sunat');

    Http::assertNothingSent();
});

it('no reactiva una GR dada de baja en SUNAT', function (): void {
    $viaje = Viaje::factory()->create([
        'anulada_at' => now(),
        'baja_sunat_at' => now(),
        'motivo_baja_sunat' => '02',
    ]);

    actingAs(actorConRol('admin'))
        ->delete(route('viajes.reactivar', $viaje))
        ->assertSessionHas('toast.type', 'error');

    expect(Viaje::query()->conAnuladas()->findOrFail($viaje->id)->estaAnulada())->toBeTrue();
});

it('marca en el listado que la anulada está de baja en SUNAT', function (): void {
    $viaje = Viaje::factory()->create(['anulada_at' => now(), 'baja_sunat_at' => '2026-09-29 20:29:28']);

    actingAs(actorConRol('visor'))
        ->get(route('viajes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viajes.data.0.id', $viaje->id)
            ->where('viajes.data.0.anulacion.baja_sunat', '2026-09-29T20:29:28+00:00')
        );
});
