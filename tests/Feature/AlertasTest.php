<?php

use App\Enums\EstadoEnvio;
use App\Enums\EstadoVuelta;
use App\Enums\TipoAlerta;
use App\Enums\TipoCarga;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Jobs\EnviarAvisoWhatsapp;
use App\Models\AreaAviso;
use App\Models\ConfiguracionAlerta;
use App\Models\EnvioWhatsapp;
use App\Models\Factura;
use App\Models\RevisionTaller;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\Alertas;
use App\Services\AvisosPorWhatsapp;
use App\Services\ContactoWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/**
 * Las alertas: el circuito del ticket avisa a quien le toca, por la campanita
 * y, si está configurado, por WhatsApp. Cada tipo se prende, se apaga y se
 * dirige desde la configuración.
 */
beforeEach(function (): void {
    config([
        'transpaty.whatsapp.url' => 'http://whatsapp.test',
        'transpaty.whatsapp.token' => 'token-de-prueba',
    ]);
    Queue::fake();
    // Los demás roles base los crea la migración de permisos; este no.
    Role::findOrCreate('operaciones', 'web');

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'VEP-897']);
});

function alertasDe(User $usuario): array
{
    return $usuario->fresh()->notifications->pluck('data.tipo')->all();
}

it('tells the workshop when a ticket is programmed, not whoever programmed it', function (): void {
    $taller = actorConRol('taller');
    $abastecimiento = actorConRol('abastecimiento');
    $programador = actorConRol('admin')->assignRole('taller');

    actingAs($programador)->post(route('vueltas.store'), [
        'tracto_id' => $this->tracto->id,
        'carreta_id' => null,
        'conductor_id' => null,
        'inicio' => now()->toDateString(),
        'paradas' => [['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado']],
        'rutas' => [['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null]],
    ])->assertSessionHasNoErrors();

    $alerta = $taller->fresh()->notifications->sole();

    expect($alerta->data['tipo'])->toBe('ticket_programado')
        ->and($alerta->data['mensaje'])->toContain('VEP-897')
        ->and($alerta->data['url'])->toBe(route('taller.index'))
        ->and(alertasDe($abastecimiento))->toBe([])
        // Quien lo hizo no se avisa a sí mismo, aunque tenga el rol.
        ->and(alertasDe($programador))->toBe([]);
});

it('tells supply when the workshop gives the OK and operations when it does not', function (): void {
    $abastecimiento = actorConRol('abastecimiento');
    $operaciones = actorConRol('operaciones');
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id, 'inicio' => now()->toDateString()]);

    actingAs(actorConRol('taller'))->post(route('taller.store', $vuelta), ['resultado' => 'apto']);

    expect(alertasDe($abastecimiento))->toBe(['taller_apto'])
        ->and(alertasDe($operaciones))->toBe([]);

    actingAs(actorConRol('taller'))->post(route('taller.store', $vuelta), [
        'resultado' => 'no_apto',
        'disponible_estimado' => now()->addDay()->format('Y-m-d\TH:i'),
        'motivo' => 'Fuga de aire en la carreta',
    ]);

    $alerta = $operaciones->fresh()->notifications->sole();

    expect($alerta->data['tipo'])->toBe('taller_no_apto')
        ->and($alerta->data['mensaje'])->toContain('Motivo: Fuga de aire en la carreta')
        ->and($alerta->data['url'])->toBe(route('vueltas.show', $vuelta));
});

it('tells operations when the first GR is assigned to a programmed ticket, once', function (): void {
    $operaciones = actorConRol('operaciones');
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id, 'inicio' => now()->toDateString()]);

    $guia = fn () => actingAs(actorConRol('admin'))->post(route('viajes.ticket.asignar', Viaje::factory()->create([
        'tracto_id' => $this->tracto->id,
        'fecha_traslado' => now()->toDateString(),
        'origen' => 'SAN RAFAEL - ANTAUTA - MELGAR - PUNO',
        'destino' => 'KM 245 - PARACAS - PISCO - ICA',
        'tipo_carga' => TipoCarga::Concentrado,
    ])), ['vuelta_id' => $vuelta->id, 'sentido' => 'ida'])->assertSessionHasNoErrors();

    $guia();
    $guia();

    expect($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso)
        ->and(alertasDe($operaciones))->toBe(['ticket_salio']);
});

it('tells accounting when a ticket gets closed', function (): void {
    $contador = actorConRol('contador');
    $vuelta = Vuelta::factory()->create(['tracto_id' => $this->tracto->id, 'llegada_el' => now()->toDateString()]);

    actingAs(actorConRol('admin'))->post(route('vueltas.cerrar', $vuelta))->assertSessionHas('toast.type', 'success');

    expect(alertasDe($contador))->toBe(['ticket_cerrado']);
});

it('sends nothing for an alert that is turned off', function (): void {
    $abastecimiento = actorConRol('abastecimiento');
    ConfiguracionAlerta::factory()->create(['tipo' => TipoAlerta::TallerApto, 'activa' => false, 'roles' => ['abastecimiento']]);
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id]);

    actingAs(actorConRol('taller'))->post(route('taller.store', $vuelta), ['resultado' => 'apto']);

    expect(alertasDe($abastecimiento))->toBe([]);
});

it('also reaches people chosen one by one, and only once', function (): void {
    $gerente = actorConRol('gerencia');
    $abastecimiento = actorConRol('abastecimiento');
    ConfiguracionAlerta::factory()->create([
        'tipo' => TipoAlerta::TallerApto,
        'roles' => ['abastecimiento'],
        'usuarios' => [$gerente->id, $abastecimiento->id],
    ]);
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id]);

    actingAs(actorConRol('taller'))->post(route('taller.store', $vuelta), ['resultado' => 'apto']);

    expect(alertasDe($gerente))->toBe(['taller_apto'])
        ->and(alertasDe($abastecimiento))->toBe(['taller_apto']);
});

it('queues WhatsApp for users with a phone and for the chosen area numbers', function (): void {
    $conCelular = actorConRol('abastecimiento');
    $conCelular->update(['celular' => '987654321']);
    $sinCelular = actorConRol('abastecimiento');
    $area = AreaAviso::factory()->create(['nombre' => 'Grifo', 'numero' => '950301883']);
    ConfiguracionAlerta::factory()->create([
        'tipo' => TipoAlerta::TallerApto,
        'por_app' => false,
        'por_whatsapp' => true,
        'roles' => ['abastecimiento'],
        'areas' => [$area->id],
    ]);
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id]);

    actingAs(actorConRol('taller'))->post(route('taller.store', $vuelta), ['resultado' => 'apto']);

    $envios = EnvioWhatsapp::query()->orderBy('id')->get();

    expect($envios->pluck('numero')->all())->toBe(['51987654321', '51950301883'])
        ->and($envios->pluck('tipo')->unique()->all())->toBe([EnvioWhatsapp::TIPO_ALERTA])
        ->and($envios->pluck('alerta')->unique()->all())->toBe(['taller_apto'])
        ->and($envios[0]->user_id)->toBe($conCelular->id)
        ->and($envios[1]->area_aviso_id)->toBe($area->id)
        ->and($envios[0]->texto)->toContain('*Ticket '.$vuelta->codigo.' apto')
        // Sin la campanita prendida, no queda en la app.
        ->and(alertasDe($conCelular))->toBe([])
        ->and(alertasDe($sinCelular))->toBe([]);

    Queue::assertPushed(EnviarAvisoWhatsapp::class, 2);
});

it('sends the alert text as it was written when the worker gets to it', function (): void {
    Http::fake(['whatsapp.test/enviar' => Http::response(['id' => 'ALERTA1'])]);

    $envio = app(AvisosPorWhatsapp::class)->encolarAlerta(TipoAlerta::TallerApto, '51987654321', 'Juan', "🔔 *Ticket V-1 apto*\nVEP-897");
    app(AvisosPorWhatsapp::class)->mandar($envio);

    expect($envio->fresh()->estado)->toBe(EstadoEnvio::Enviado)
        ->and($envio->fresh()->mensaje_id)->toBe('ALERTA1');

    Http::assertSent(fn ($request): bool => $request['numero'] === '51987654321' && $request['texto'] === "🔔 *Ticket V-1 apto*\nVEP-897");
});

it('never breaks what triggered it when notifying fails', function (): void {
    actorConRol('abastecimiento')->update(['celular' => '987654321']);
    ConfiguracionAlerta::factory()->create(['tipo' => TipoAlerta::TallerApto, 'por_whatsapp' => true, 'roles' => ['abastecimiento']]);
    $this->mock(ContactoWhatsapp::class)->shouldReceive('numeroWhatsapp')->andThrow(new RuntimeException('WhatsApp caído'));
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id]);

    // La revisión se guarda igual: la alerta es un extra, no una condición.
    actingAs(actorConRol('taller'))
        ->post(route('taller.store', $vuelta), ['resultado' => 'apto'])
        ->assertSessionHas('toast.type', 'success');

    expect(RevisionTaller::query()->count())->toBe(1);
});

it('lists my alerts, opens one marking it read and marks them all', function (): void {
    $yo = actorConRol('abastecimiento');
    $otro = actorConRol('abastecimiento');
    ConfiguracionAlerta::factory()->create(['tipo' => TipoAlerta::TallerApto, 'roles' => ['abastecimiento']]);

    foreach (Vuelta::factory()->programada()->count(2)->create(['tracto_id' => $this->tracto->id]) as $vuelta) {
        actingAs(actorConRol('taller'))->post(route('taller.store', $vuelta), ['resultado' => 'apto']);
    }

    actingAs($yo)
        ->get(route('alertas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('alertas/index')
            ->has('alertas.data', 2)
            ->where('sinLeer', 2)
            ->where('alertas.data.0.tipo', 'taller_apto')
            ->where('alertas.data.0.leida', false)
            // La campanita viaja en todas las páginas.
            ->where('alertas.data.0.titulo', fn (string $titulo): bool => str_contains($titulo, 'apto')));

    $primera = $yo->notifications()->first();

    actingAs($yo)
        ->post(route('alertas.abrir', $primera->id))
        ->assertRedirect(route('abastecimiento.index'));

    expect($yo->unreadNotifications()->count())->toBe(1);

    // No se puede abrir la alerta de otro.
    actingAs($otro)->post(route('alertas.abrir', $primera->id))->assertNotFound();

    actingAs($yo)->post(route('alertas.leidas'))->assertRedirect();

    expect($yo->unreadNotifications()->count())->toBe(0)
        ->and($otro->unreadNotifications()->count())->toBe(2);
});

it('shares the bell with every page', function (): void {
    $yo = actorConRol('abastecimiento');
    ConfiguracionAlerta::factory()->create(['tipo' => TipoAlerta::TallerApto, 'roles' => ['abastecimiento']]);
    $vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $this->tracto->id]);
    actingAs(actorConRol('taller'))->post(route('taller.store', $vuelta), ['resultado' => 'apto']);

    actingAs($yo)
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alertas.sin_leer', 1)
            ->has('alertas.recientes', 1)
            ->where('alertas.recientes.0.tipo', 'taller_apto'));
});

it('lets only whoever may configure alerts see and change them', function (): void {
    actingAs(actorConRol('operaciones'))->get(route('alertas.configuracion'))->assertForbidden();

    actingAs(actorConRol('admin'))
        ->get(route('alertas.configuracion'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('configuracion/alertas')
            ->has('tipos', count(TipoAlerta::cases()))
            ->where('tipos.0.tipo', 'ticket_programado')
            // Sin configurar, salen con sus valores de fábrica.
            ->where('tipos.0.roles', ['taller'])
            ->where('tipos.0.por_app', true)
            ->where('tipos.0.por_whatsapp', false));
});

it('saves how an alert is sent and to whom', function (): void {
    $gerente = actorConRol('gerencia');
    $area = AreaAviso::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('alertas.configuracion.update', TipoAlerta::TicketCerrado->value), [
            'activa' => true,
            'por_app' => true,
            'por_whatsapp' => true,
            'roles' => ['contador', 'gerencia'],
            'usuarios' => [$gerente->id],
            'areas' => [$area->id],
        ])
        ->assertSessionHasNoErrors();

    $configuracion = ConfiguracionAlerta::para(TipoAlerta::TicketCerrado);

    expect($configuracion->exists)->toBeTrue()
        ->and($configuracion->por_whatsapp)->toBeTrue()
        ->and($configuracion->roles)->toBe(['contador', 'gerencia'])
        ->and($configuracion->usuarios)->toBe([$gerente->id])
        ->and($configuracion->areas)->toBe([$area->id])
        ->and($configuracion->hora)->toBeNull();
});

it('asks for the hour of a daily summary and rejects unknown roles', function (): void {
    actingAs(actorConRol('admin'))
        ->put(route('alertas.configuracion.update', TipoAlerta::DocumentosPorVencer->value), [
            'activa' => true, 'por_app' => true, 'por_whatsapp' => false,
            'roles' => ['inventado'], 'usuarios' => [], 'areas' => [], 'hora' => null,
        ])
        ->assertSessionHasErrors(['hora', 'roles.0']);
});

it('sends a test alert only to whoever asks for it', function (): void {
    $admin = actorConRol('admin');
    $taller = actorConRol('taller');

    actingAs($admin)
        ->post(route('alertas.configuracion.probar', TipoAlerta::TicketProgramado->value))
        ->assertSessionHas('toast.type', 'success');

    expect(alertasDe($admin))->toBe(['ticket_programado'])
        ->and(alertasDe($taller))->toBe([]);
});

it('keeps the phone of a user as digits and rejects one that is not a phone', function (): void {
    $usuario = User::factory()->create(['username' => 'jperez'])->assignRole('operaciones');

    actingAs(actorConRol('admin'))
        ->put(route('usuarios.update', $usuario), [
            'name' => $usuario->name,
            'username' => $usuario->username,
            'email' => null,
            'celular' => '987 654 321',
            'role' => 'operaciones',
            'permisos' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($usuario->fresh()->celular)->toBe('987654321');

    actingAs(actorConRol('admin'))
        ->put(route('usuarios.update', $usuario), [
            'name' => $usuario->name,
            'username' => $usuario->username,
            'celular' => '123',
            'role' => 'operaciones',
            'permisos' => [],
        ])
        ->assertSessionHasErrors('celular');
});

it('sends the documents summary once a day, after its hour', function (): void {
    $admin = actorConRol('admin');
    $this->travelTo(CarbonImmutable::parse('2026-11-03 07:00', 'America/Lima'));
    VehiculoDocumento::query()->create([
        'vehiculo_id' => $this->tracto->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => '2026-11-01',
    ]);
    VehiculoDocumento::query()->create([
        'vehiculo_id' => $this->tracto->id,
        'tipo' => TipoDocumento::RevisionTecnicaCarga,
        'fecha_vencimiento' => '2026-11-10',
    ]);

    // Antes de las 07:30 no sale.
    artisan('transpaty:alertas-diarias');
    expect(alertasDe($admin))->toBe([]);

    $this->travelTo(CarbonImmutable::parse('2026-11-03 07:31', 'America/Lima'));
    artisan('transpaty:alertas-diarias');
    artisan('transpaty:alertas-diarias');

    $alerta = $admin->fresh()->notifications->sole();

    expect($alerta->data['titulo'])->toBe('1 documento vencido y 1 por vencer')
        ->and($alerta->data['mensaje'])->toContain('VEP-897 · '.TipoDocumento::Soat->label().' · venció el 01/11')
        ->and($alerta->data['mensaje'])->toContain('vence el 10/11');

    // Al día siguiente vuelve a salir.
    $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00', 'America/Lima'));
    artisan('transpaty:alertas-diarias');

    expect($admin->fresh()->notifications)->toHaveCount(2);
});

it('sends no summary when there is nothing to tell', function (): void {
    $admin = actorConRol('admin');
    $contador = actorConRol('contador');
    $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00', 'America/Lima'));

    artisan('transpaty:alertas-diarias');

    expect(alertasDe($admin))->toBe([])
        ->and(alertasDe($contador))->toBe([]);
});

it('sends the overdue invoices to accounting with what is still owed', function (): void {
    $contador = actorConRol('contador');
    $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00', 'America/Lima'));
    Factura::factory()->create([
        'numero' => 'E001-00100',
        'monto' => 7500,
        'fecha_emision' => '2026-09-01',
        'cliente_razon_social' => 'DECORA HOGAR S.R.L.',
    ]);
    // Cobrada del todo: no va.
    Factura::factory()->create([
        'monto' => 1000,
        'fecha_emision' => '2026-09-01',
        'fecha_pago' => '2026-09-20',
        'fecha_detraccion' => '2026-09-20',
    ]);

    artisan('transpaty:alertas-diarias');

    $alerta = $contador->fresh()->notifications->sole();

    expect($alerta->data['titulo'])->toBe('1 factura vencida sin cobrar')
        ->and($alerta->data['mensaje'])->toContain('E001-00100 · DECORA HOGAR S.R.L. · S/ 8,850.00');
});
