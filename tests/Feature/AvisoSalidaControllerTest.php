<?php

use App\Enums\EstadoEnvio;
use App\Jobs\EnviarAvisoWhatsapp;
use App\Models\Ajuste;
use App\Models\AreaAviso;
use App\Models\EnvioWhatsapp;
use App\Models\Programacion;
use App\Services\AvisosPorWhatsapp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
})->with(['conductor', 'advertencia']);

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

it('renders the area notice, with the flete only for areas that see it', function (): void {
    $programacion = Programacion::factory()->create(['precio_flete' => 1500]);
    $conFlete = AreaAviso::factory()->veFlete()->create();
    $sinFlete = AreaAviso::factory()->create();

    $alto = fn (AreaAviso $area): int => getimagesizefromstring(
        actingAs(actorConRol('admin'))
            ->get(route('programacion.avisoArea.imagen', [$programacion, $area]))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'image/png')
            ->getContent()
    )[1];

    // El recuadro del flete hace más alta la imagen de quien lo ve.
    expect($alto($conFlete))->toBeGreaterThan($alto($sinFlete));
});

it('sends an area notice to the saved number of the area, not to one in the request', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => 'ABC'])]);
    $programacion = Programacion::factory()->create();
    $area = AreaAviso::factory()->create(['nombre' => 'Centro de Control', 'numero' => '950301883']);

    actingAs(actorConRol('admin'))
        ->post(route('programacion.avisoArea.enviar', [$programacion, $area]), ['numero' => '999999999'])
        ->assertSessionHas('toast', ['type' => 'success', 'message' => 'Enviando a Centro de Control…']);

    Http::assertSent(fn (Request $request): bool => $request['numero'] === '51950301883'
        && str_starts_with(base64_decode($request['imagen']), "\x89PNG"));

    // Solo lo que va al conductor deja la salida como avisada.
    expect($programacion->fresh()->aviso_enviado_at)->toBeNull();
});

it('does not send to an area that is turned off', function (): void {
    Http::fake();
    $area = AreaAviso::factory()->inactiva()->create();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.avisoArea.enviar', [Programacion::factory()->create(), $area]))
        ->assertSessionHas('toast.type', 'error');

    Http::assertNothingSent();
});

it('queues the notice and answers right away, without waiting for WhatsApp', function (): void {
    Queue::fake();
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.aviso.enviar', [$programacion, 'conductor']), ['numero' => '987654321', 'destino' => 'Alterno'])
        ->assertSessionHas('toast.type', 'success');

    $envio = EnvioWhatsapp::query()->sole();

    expect($envio->estado)->toBe(EstadoEnvio::Pendiente)
        ->and($envio->numero)->toBe('51987654321')
        ->and($envio->destino)->toBe('Alterno');

    Queue::assertPushedOn('whatsapp', EnviarAvisoWhatsapp::class);
});

it('marks the envío as failed, and the salida as not avisada, when WhatsApp gives up', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['error' => 'WhatsApp no está conectado.'], 503)]);
    Queue::fake();
    $envio = EnvioWhatsapp::factory()->create();

    $trabajo = new EnviarAvisoWhatsapp($envio);

    expect(fn () => $trabajo->handle(app(AvisosPorWhatsapp::class)))->toThrow(RuntimeException::class);

    $trabajo->failed(new RuntimeException('WhatsApp no está conectado.'));

    expect($envio->fresh())
        ->estado->toBe(EstadoEnvio::Fallido)
        ->error->toBe('WhatsApp no está conectado.')
        ->and($envio->programacion->fresh()->aviso_enviado_at)->toBeNull();
});

it('records the WhatsApp message id so the receipts can find the envío', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => '3EB0ABC'])]);
    $envio = EnvioWhatsapp::factory()->create();

    (new EnviarAvisoWhatsapp($envio))->handle(app(AvisosPorWhatsapp::class));

    expect($envio->fresh())
        ->estado->toBe(EstadoEnvio::Enviado)
        ->mensaje_id->toBe('3EB0ABC')
        ->enviado_at->not->toBeNull();
});

it('tells the programación page whether WhatsApp is linked', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page->where('whatsappConectado', false));
});

it('draws the advertencia once and redraws it when the oficina phone changes', function (): void {
    $programacion = Programacion::factory()->create();
    $admin = actorConRol('admin');
    $pedir = fn (): string => actingAs($admin)
        ->get(route('programacion.aviso.imagen', [$programacion, 'advertencia']))
        ->getContent();

    Ajuste::guardar(Ajuste::TELEFONO_OFICINA, '923-275-353');
    $primera = $pedir();

    expect($pedir())->toBe($primera);

    Ajuste::guardar(Ajuste::TELEFONO_OFICINA, '999-888-777');

    expect($pedir())->not->toBe($primera);
});
