<?php

use App\Enums\EstadoEnvio;
use App\Jobs\EnviarAvisoWhatsapp;
use App\Models\Ajuste;
use App\Models\AreaAviso;
use App\Models\EnvioWhatsapp;
use App\Models\Programacion;
use App\Models\Viaje;
use App\Services\AvisosPorWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    config([
        'transpaty.whatsapp.url' => 'http://whatsapp.test',
        'transpaty.whatsapp.token' => 'token-de-prueba',
    ]);

    $this->whatsappConectado = true;
    Http::fake(['whatsapp.test/estado' => fn () => Http::response([
        'estado' => $this->whatsappConectado ? 'conectado' : 'desconectado',
        'qr' => null,
        'numero' => null,
    ])]);
    Queue::fake();

    Ajuste::guardar(Ajuste::HORA_RECORDATORIO, '10:00');
    $this->travelTo(CarbonImmutable::parse('2026-09-26 10:05', 'America/Lima'));
});

function unidadProgramadaHoy(): Programacion
{
    return Programacion::factory()->create(['fecha' => '2026-09-26']);
}

it('reminds the marked areas of the units that still have no GR', function (): void {
    unidadProgramadaHoy();
    $centro = AreaAviso::factory()->create(['nombre' => 'Centro de Control', 'numero' => '950301883', 'recibe_recordatorio' => true]);
    AreaAviso::factory()->create(['nombre' => 'Abastecimiento', 'recibe_recordatorio' => false]);
    AreaAviso::factory()->inactiva()->create(['recibe_recordatorio' => true]);

    artisan('transpaty:recordatorio-sin-gr')->assertSuccessful();

    $envio = EnvioWhatsapp::query()->sole();

    expect($envio->tipo)->toBe(EnvioWhatsapp::TIPO_RECORDATORIO)
        ->and($envio->area_aviso_id)->toBe($centro->id)
        ->and($envio->numero)->toBe('51950301883');

    Queue::assertPushed(EnviarAvisoWhatsapp::class, 1);
});

it('sends it once a day, not every minute', function (): void {
    unidadProgramadaHoy();
    AreaAviso::factory()->create(['recibe_recordatorio' => true]);

    artisan('transpaty:recordatorio-sin-gr');
    $this->travel(30)->minutes();
    artisan('transpaty:recordatorio-sin-gr');

    expect(EnvioWhatsapp::query()->count())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-09-27 10:01', 'America/Lima'));
    Programacion::factory()->create(['fecha' => '2026-09-27']);
    artisan('transpaty:recordatorio-sin-gr');

    expect(EnvioWhatsapp::query()->count())->toBe(2);
});

it('waits for the configured hour, in Lima', function (): void {
    unidadProgramadaHoy();
    AreaAviso::factory()->create(['recibe_recordatorio' => true]);

    $this->travelTo(CarbonImmutable::parse('2026-09-26 09:59', 'America/Lima'));
    artisan('transpaty:recordatorio-sin-gr');

    expect(EnvioWhatsapp::query()->count())->toBe(0);
});

it('stays quiet when every unit already has its GR', function (): void {
    $programacion = unidadProgramadaHoy();
    AreaAviso::factory()->create(['recibe_recordatorio' => true]);
    Viaje::factory()->create(['tracto_id' => $programacion->vehiculo_id, 'fecha_traslado' => '2026-09-26']);

    artisan('transpaty:recordatorio-sin-gr');

    expect(EnvioWhatsapp::query()->count())->toBe(0);
});

it('stays quiet when the reminder is turned off', function (): void {
    unidadProgramadaHoy();
    AreaAviso::factory()->create(['recibe_recordatorio' => true]);
    Ajuste::guardar(Ajuste::HORA_RECORDATORIO, null);

    artisan('transpaty:recordatorio-sin-gr');

    expect(EnvioWhatsapp::query()->count())->toBe(0);
});

it('waits for WhatsApp to be linked instead of queueing a notice that would fail', function (): void {
    $this->whatsappConectado = false;
    unidadProgramadaHoy();
    AreaAviso::factory()->create(['recibe_recordatorio' => true]);

    artisan('transpaty:recordatorio-sin-gr');

    expect(EnvioWhatsapp::query()->count())->toBe(0);
});

it('sends only the units still without GR when the worker gets to it', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => 'REC1'])]);
    $conGr = unidadProgramadaHoy();
    unidadProgramadaHoy();
    $area = AreaAviso::factory()->create(['recibe_recordatorio' => true]);
    Viaje::factory()->create(['tracto_id' => $conGr->vehiculo_id, 'fecha_traslado' => '2026-09-26']);

    $envio = app(AvisosPorWhatsapp::class)->encolarRecordatorio($area, '51950301883');
    app(AvisosPorWhatsapp::class)->mandar($envio);

    expect($envio->fresh()->estado)->toBe(EstadoEnvio::Enviado);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://whatsapp.test/enviar' && $request['texto'] === '1 unidad sin GR');
});

it('does not send the reminder if by then every unit got its GR', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => 'REC1'])]);
    $programacion = unidadProgramadaHoy();
    $area = AreaAviso::factory()->create(['recibe_recordatorio' => true]);
    $envio = app(AvisosPorWhatsapp::class)->encolarRecordatorio($area, '51950301883');

    Viaje::factory()->create(['tracto_id' => $programacion->vehiculo_id, 'fecha_traslado' => '2026-09-26']);
    app(AvisosPorWhatsapp::class)->mandar($envio);

    expect($envio->fresh()->estado)->toBe(EstadoEnvio::Fallido);
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/enviar'));
});
