<?php

use App\Models\Programacion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    config([
        'transpaty.whatsapp.url' => 'http://whatsapp.test',
        'transpaty.whatsapp.token' => 'token-de-prueba',
    ]);
});

it('renders every notice of a salida as a PNG preview', function (string $tipo): void {
    $programacion = Programacion::factory()->create(['precio_flete' => 1500]);

    $respuesta = actingAs(actorConRol('admin'))
        ->get(route('programacion.aviso.imagen', [$programacion, $tipo]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png');

    expect(substr($respuesta->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(getimagesizefromstring($respuesta->getContent())[0])->toBe(1080);
})->with(['conductor', 'advertencia', 'abastecimiento', 'facturacion']);

it('returns 404 for an unknown kind of notice', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('programacion.aviso.imagen', [Programacion::factory()->create(), 'otro']))
        ->assertNotFound();
});

it('keeps a visor from previewing or sending notices', function (): void {
    Http::fake();
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('visor'))->get(route('programacion.aviso.imagen', [$programacion, 'conductor']))->assertForbidden();
    actingAs(actorConRol('visor'))->post(route('programacion.aviso.enviar', [$programacion, 'conductor']), ['numero' => '987654321'])->assertForbidden();

    Http::assertNothingSent();
});

it('sends the driver notice as an image and marks the salida as avisada', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => 'ABC'])]);
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.aviso.enviar', [$programacion, 'conductor']), ['numero' => '987654321'])
        ->assertSessionHas('toast.type', 'success');

    Http::assertSent(fn (Request $request): bool => $request['numero'] === '51987654321'
        && str_starts_with(base64_decode($request['imagen']), "\x89PNG")
        && str_contains($request['texto'], $programacion->vehiculo->placa));

    expect($programacion->fresh()->aviso_enviado_at)->not->toBeNull();
});

it('does not mark the salida as avisada for an area notice', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => 'ABC'])]);
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.aviso.enviar', [$programacion, 'abastecimiento']), ['numero' => '950301881'])
        ->assertSessionHas('toast.type', 'success');

    expect($programacion->fresh()->aviso_enviado_at)->toBeNull();
});

it('shows the error and does not mark as avisada when WhatsApp fails', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['error' => 'WhatsApp no está conectado.'], 503)]);
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.aviso.enviar', [$programacion, 'conductor']), ['numero' => '987654321'])
        ->assertSessionHas('toast', ['type' => 'error', 'message' => 'WhatsApp no está conectado.']);

    expect($programacion->fresh()->aviso_enviado_at)->toBeNull();
});

it('tells the programación page whether WhatsApp is linked', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page->where('whatsappConectado', false));
});
