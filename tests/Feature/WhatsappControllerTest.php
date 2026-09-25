<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    config([
        'transpaty.whatsapp.url' => 'http://whatsapp.test',
        'transpaty.whatsapp.token' => 'token-de-prueba',
    ]);
});

it('keeps everyone but the admin out of the whatsapp screen', function (string $rol): void {
    Http::fake();

    actingAs(actorConRol($rol))->get(route('whatsapp.index'))->assertForbidden();
    actingAs(actorConRol($rol))->post(route('whatsapp.probar'), ['numero' => '987654321'])->assertForbidden();

    Http::assertNothingSent();
})->with(['visor', 'contador']);

it('shows the link state reported by the service', function (): void {
    Http::fake([
        'whatsapp.test/estado' => Http::response(['estado' => 'conectado', 'qr' => null, 'numero' => '51950301881']),
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('whatsapp.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('whatsapp/index')
            ->where('estado.estado', 'conectado')
            ->where('estado.numero', '51950301881')
        );

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token-de-prueba'));
});

it('reports the service as unavailable instead of failing when it is down', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    actingAs(actorConRol('admin'))
        ->get(route('whatsapp.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('estado.estado', 'sin_servicio'));
});

it('sends a test message to the number with the country code added', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => 'ABC123'])]);

    actingAs(actorConRol('admin'))
        ->post(route('whatsapp.probar'), ['numero' => '987 654 321'])
        ->assertSessionHas('toast.type', 'success');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://whatsapp.test/enviar'
        && $request['numero'] === '51987654321'
        && str_contains($request['texto'], 'Mensaje de prueba'));
});

it('shows the service error when the message cannot be sent', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['error' => 'El número 51987654321 no tiene WhatsApp.'], 422)]);

    actingAs(actorConRol('admin'))
        ->post(route('whatsapp.probar'), ['numero' => '987654321'])
        ->assertSessionHas('toast', ['type' => 'error', 'message' => 'El número 51987654321 no tiene WhatsApp.']);
});

it('rejects a test number that is not a phone', function (): void {
    Http::fake();

    actingAs(actorConRol('admin'))
        ->post(route('whatsapp.probar'), ['numero' => '123'])
        ->assertSessionHasErrors('numero');

    Http::assertNothingSent();
});

it('asks the service for a pairing code when linking by phone number', function (): void {
    Http::fake(['whatsapp.test/vincular' => Http::response(['codigo' => 'ABCD1234'])]);

    actingAs(actorConRol('admin'))
        ->post(route('whatsapp.vincular'), ['telefono' => '950301881'])
        ->assertSessionHas('codigo_vinculacion', 'ABCD1234');

    Http::assertSent(fn (Request $request): bool => $request['telefono'] === '51950301881');
});
