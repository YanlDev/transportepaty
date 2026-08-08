<?php

use App\Enums\EstadoProgramacion;
use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoVehiculo;
use App\Models\Asignacion;
use App\Models\Programacion;
use App\Models\Vehiculo;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('redirects guests to login', function (): void {
    $this->get(route('programacion.index'))->assertRedirect(route('login'));
});

it('lets a visor see the board but not mark it', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('programacion.index'))
        ->assertSuccessful();

    actingAs(actorConRol('visor'))
        ->patch(route('programacion.marcar', $tracto), [
            'fecha' => now()->toDateString(),
            'estado' => EstadoProgramacion::Metalico->value,
        ])
        ->assertForbidden();
});

it('lists only active tractos, not carretas nor tractos de baja', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'ABC-123']);
    Vehiculo::factory()->carreta()->create();
    Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'estado' => EstadoVehiculo::DadoDeBaja]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filas', 1)
            ->where('filas.0.vehiculo_id', $tracto->id)
            ->where('filas.0.marca', null)
        );
});

it('shows the conductor currently assigned to the tracto', function (): void {
    $asignacion = Asignacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas.0.vehiculo_id', $asignacion->tracto_id)
            ->where('filas.0.conductor_nombre', $asignacion->conductor->nombre_completo)
        );
});

it('lets an admin mark a tracto and change the mark afterwards', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.marcar', $tracto), [
            'fecha' => '2026-08-05',
            'estado' => EstadoProgramacion::Metalico->value,
        ])
        ->assertSessionHasNoErrors();

    expect(Programacion::query()->count())->toBe(1);
    expect(Programacion::query()->sole()->estado)->toBe(EstadoProgramacion::Metalico);

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.marcar', $tracto), [
            'fecha' => '2026-08-05',
            'estado' => EstadoProgramacion::Libre->value,
        ])
        ->assertSessionHasNoErrors();

    expect(Programacion::query()->count())->toBe(1)
        ->and(Programacion::query()->sole()->estado)->toBe(EstadoProgramacion::Libre);
});

it('keeps a quick glance of the previous estado and when it changed', function (): void {
    $tracto = Vehiculo::factory()->create();

    // El primer marcado del día no cuenta como «cambio»: no había nada antes.
    actingAs(actorConRol('admin'))->patch(route('programacion.marcar', $tracto), [
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Metalico->value,
    ]);

    expect(Programacion::query()->sole())
        ->estado_anterior->toBeNull()
        ->estado_cambiado_en->toBeNull();

    $this->travelTo(now()->setTime(14, 32));

    actingAs(actorConRol('admin'))->patch(route('programacion.marcar', $tracto), [
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Libre->value,
    ]);

    $programacion = Programacion::query()->sole();
    expect($programacion->estado_anterior)->toBe(EstadoProgramacion::Metalico)
        ->and($programacion->estado_cambiado_en->format('H:i'))->toBe('14:32');

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-08-05']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas.0.marca.estado_anterior_label', 'Metálico')
            ->where('filas.0.marca.estado_cambiado_en', '14:32')
        );
});

it('does not touch the previous-estado glance when the estado stays the same', function (): void {
    $tracto = Vehiculo::factory()->create();
    $programacion = Programacion::create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Metalico,
    ]);

    actingAs(actorConRol('admin'))->patch(route('programacion.marcar', $tracto), [
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Metalico->value,
        'observaciones' => 'sigue igual',
    ]);

    expect($programacion->refresh())
        ->estado_anterior->toBeNull()
        ->estado_cambiado_en->toBeNull()
        ->observaciones->toBe('sigue igual');
});

it('lets an admin clear a mark back to unmarked', function (): void {
    $tracto = Vehiculo::factory()->create();
    $programacion = Programacion::create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Ransa,
    ]);

    actingAs(actorConRol('admin'))
        ->delete(route('programacion.destroy', $programacion))
        ->assertSessionHasNoErrors();

    expect(Programacion::query()->count())->toBe(0);
});

it('rejects an invalid estado', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.marcar', $tracto), [
            'fecha' => '2026-08-05',
            'estado' => 'inventado',
        ])
        ->assertSessionHasErrors('estado');
});

it('only shows the mark for the requested date', function (): void {
    $tracto = Vehiculo::factory()->create();
    Programacion::create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-08-04',
        'estado' => EstadoProgramacion::Salida,
    ]);
    Programacion::create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Polytex,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-08-05']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fecha', '2026-08-05')
            ->where('filas.0.marca.estado', 'polytex')
        );
});

it('filters by caja: only automáticas suben a mina', function (): void {
    $automatica = Vehiculo::factory()->create(['placa' => 'AAA-111', 'caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->create(['placa' => 'MMM-111', 'caja' => TipoCaja::Mecanica]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['caja' => TipoCaja::Automatica->value]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.caja', 'automatica')
            ->has('filas', 1)
            ->where('filas.0.vehiculo_id', $automatica->id)
        );

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.caja', '')
            ->has('filas', 2)
        );
});

it('lets an admin record the client of a particular load', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.marcar', $tracto), [
            'fecha' => '2026-08-05',
            'estado' => EstadoProgramacion::Particular->value,
            'cliente' => 'Promart',
        ])
        ->assertSessionHasNoErrors();

    expect(Programacion::query()->sole())
        ->estado->toBe(EstadoProgramacion::Particular)
        ->cliente->toBe('Promart');

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-08-05']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas.0.marca.estado', 'particular')
            ->where('filas.0.marca.cliente', 'Promart')
        );
});

it('does not overwrite the client when only the estado changes', function (): void {
    $tracto = Vehiculo::factory()->create();
    $programacion = Programacion::create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Particular,
        'cliente' => 'Cerámica San Lorenzo',
    ]);

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.marcar', $tracto), [
            'fecha' => '2026-08-05',
            'estado' => EstadoProgramacion::Libre->value,
        ])
        ->assertSessionHasNoErrors();

    expect($programacion->refresh()->cliente)->toBe('Cerámica San Lorenzo');
});

it('lets an admin record the destino regardless of estado', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.marcar', $tracto), [
            'fecha' => '2026-08-05',
            'estado' => EstadoProgramacion::Metalico->value,
            'destino' => 'Callao',
        ])
        ->assertSessionHasNoErrors();

    expect(Programacion::query()->sole())
        ->estado->toBe(EstadoProgramacion::Metalico)
        ->destino->toBe('Callao');

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-08-05']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas.0.marca.destino', 'Callao')
        );
});

it('lists Minsur plus every distinct cliente already typed, without duplicates', function (): void {
    $tracto = Vehiculo::factory()->create();
    Programacion::create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-08-04',
        'estado' => EstadoProgramacion::Particular,
        'cliente' => 'Promart',
    ]);
    Programacion::create([
        'vehiculo_id' => $tracto->id,
        'fecha' => '2026-08-05',
        'estado' => EstadoProgramacion::Metalico,
        'cliente' => 'Minsur',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('clientes', ['Minsur', 'Promart'])
        );
});
