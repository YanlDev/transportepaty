<?php

use App\Enums\EstadoEnvio;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Jobs\EnviarAvisoWhatsapp;
use App\Models\AreaAviso;
use App\Models\Conductor;
use App\Models\EnvioWhatsapp;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use App\Services\AvisoDeVuelta;
use App\Services\AvisosPorWhatsapp;
use App\Services\WhatsappServicio;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Programación es la vuelta: se ve qué sale cada día y se avisa por WhatsApp
 * al conductor y a las áreas desde ahí.
 */
beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');

    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'BYM-861']);
    $conductor = Conductor::factory()->create(['nombres' => 'Alberto', 'apellidos' => 'Machaca', 'telefono' => '987654321']);
    $this->vuelta = Vuelta::factory()->programada()->create([
        'tracto_id' => $tracto->id,
        'conductor_id' => $conductor->id,
        'inicio' => '2026-10-27',
    ]);

    foreach ([
        [SentidoTramo::Ida, ZonaRuta::Juliaca, ZonaRuta::Mina, TipoCarga::Vacio],
        [SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado],
        [SentidoTramo::Regreso, ZonaRuta::Pisco, ZonaRuta::Mina, TipoCarga::Escoria],
    ] as $orden => [$sentido, $origen, $destino, $carga]) {
        ParadaVuelta::factory()->create([
            'vuelta_id' => $this->vuelta->id, 'sentido' => $sentido, 'orden' => $orden + 1,
            'zona_origen' => $origen, 'zona_destino' => $destino, 'tipo_carga' => $carga,
            'lugar_origen' => null, 'lugar_destino' => null, 'cliente' => null, 'fecha' => null,
        ]);
    }
});

it('writes the trip for the driver with its ticket and both legs', function (): void {
    $mensaje = app(AvisoDeVuelta::class)->mensajeParaConductor($this->vuelta->fresh(['tracto', 'carreta', 'conductor', 'paradas.viajes']));

    expect($mensaje)
        ->toContain("Ticket: *{$this->vuelta->codigo}*")
        ->toContain('Unidad: *BYM-861*')
        ->toContain('Conductor: Alberto Machaca')
        ->toContain("*Ida*\n• Juliaca → Mina San Rafael · vacío\n• Mina San Rafael → Pisco · Concentrado")
        ->toContain("*Vuelta*\n• Pisco → Mina San Rafael · Escoria")
        ->toContain('No inicies el viaje sin documentación validada');
});

it('tells the areas what the board assigns to the trip', function (): void {
    $mensaje = app(AvisoDeVuelta::class)->mensajeParaArea($this->vuelta->fresh(['tracto', 'carreta', 'conductor', 'paradas.viajes']));

    expect($mensaje)
        ->toContain('Combustible: ida 220 gal (Mina 100 gal · Arequipa 120 gal) · vuelta 130 gal (Total del tramo 130 gal)')
        ->toContain('Viático mina S/ 143.00');
});

it('shows the trips that leave on the chosen day', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index', ['fecha' => '2026-10-27']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/index')
            ->where('fecha', '2026-10-27')
            ->has('salidas', 1)
            ->where('salidas.0.placa', 'BYM-861')
            ->where('salidas.0.aviso.destinatarios.0.numero', '51987654321')
            ->where('semana', fn ($semana): bool => collect($semana)->firstWhere('fecha', '2026-10-27')['programadas'] === 1));

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index', ['fecha' => '2026-10-28']))
        ->assertInertia(fn (Assert $page) => $page->has('salidas', 0));
});

it('queues the notices of the trip to the driver and to an area', function (): void {
    Queue::fake();
    $area = AreaAviso::factory()->create(['nombre' => 'Abastecimiento', 'numero' => '912345678']);

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.aviso', $this->vuelta), ['numero' => '987654321', 'destino' => 'Conductor'])
        ->assertSessionHas('toast.type', 'success');
    actingAs(actorConRol('admin'))
        ->post(route('vueltas.avisoArea', ['vuelta' => $this->vuelta, 'area' => $area]))
        ->assertSessionHas('toast.type', 'success');

    expect(EnvioWhatsapp::query()->where('vuelta_id', $this->vuelta->id)->orderBy('id')->pluck('tipo')->all())
        ->toBe([EnvioWhatsapp::TIPO_CONDUCTOR, EnvioWhatsapp::TIPO_AREA]);
    Queue::assertPushed(EnviarAvisoWhatsapp::class, 2);
});

it('sends the notice of the trip as text', function (): void {
    $envio = EnvioWhatsapp::query()->create([
        'vuelta_id' => $this->vuelta->id, 'tipo' => EnvioWhatsapp::TIPO_CONDUCTOR,
        'destino' => 'Conductor', 'numero' => '51987654321', 'estado' => EstadoEnvio::Pendiente,
    ]);

    $this->mock(WhatsappServicio::class)
        ->shouldReceive('enviar')
        ->once()
        ->withArgs(fn (string $numero, string $texto, ?string $imagen = null): bool => $numero === '51987654321'
            && str_contains($texto, $this->vuelta->codigo)
            && $imagen === null)
        ->andReturn('mensaje-1');

    app(AvisosPorWhatsapp::class)->mandar($envio);

    expect($envio->fresh()->estado)->toBe(EstadoEnvio::Enviado)
        ->and($envio->fresh()->mensaje_id)->toBe('mensaje-1');
});
