<?php

use App\Enums\EstadoVehiculo;
use App\Enums\OrigenDato;
use App\Enums\TipoAlerta;
use App\Enums\TipoCaja;
use App\Enums\TipoCarga;
use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('exige sesión para ver la disponibilidad', function (): void {
    $this->get(route('disponibilidad.index'))->assertRedirect(route('login'));
});

it('no deja entrar a un conductor', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('disponibilidad.index'))
        ->assertForbidden();
});

it('siempre lista todos los tractos activos, con o sin estado ese día', function (): void {
    $tractoB = Vehiculo::factory()->create(['placa' => 'BBB-111']);
    $tractoA = Vehiculo::factory()->create(['placa' => 'AAA-222']);
    Vehiculo::factory()->create(['placa' => 'ZZZ-999', 'estado' => EstadoVehiculo::Inactivo]);

    EstadoUnidad::factory()->create(['tracto_id' => $tractoB->id, 'fecha' => '2026-07-21']);
    // $tractoA no tiene EstadoUnidad ese día: debe aparecer igual, vacío.

    actingAs(actorConRol('admin'))
        ->get(route('disponibilidad.index', ['fecha' => '2026-07-21']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('disponibilidad/index')
            ->where('fecha', '2026-07-21')
            ->has('filas', 2)
            ->where('filas.0.tracto.placa', 'AAA-222')
            ->where('filas.0.id', null)
            ->where('filas.1.tracto.placa', 'BBB-111')
            ->where('filas.1.id', fn (?int $id): bool => $id !== null)
        );
});

it('filtra por tipo de caja cuando se pide, porque solo las automáticas suben a mina', function (): void {
    Vehiculo::factory()->create(['placa' => 'AAA-111', 'caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->create(['placa' => 'BBB-222', 'caja' => TipoCaja::Mecanica]);

    actingAs(actorConRol('admin'))
        ->get(route('disponibilidad.index', ['fecha' => '2026-07-21', 'caja' => 'automatica']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filas', 1)
            ->where('filas.0.tracto.placa', 'AAA-111')
            ->where('caja', 'automatica')
        );
});

it('ignora un filtro de caja que no existe y muestra toda la flota', function (): void {
    Vehiculo::factory()->create(['caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->create(['caja' => TipoCaja::Mecanica]);

    actingAs(actorConRol('admin'))
        ->get(route('disponibilidad.index', ['fecha' => '2026-07-21', 'caja' => 'voladora']))
        ->assertInertia(fn (Assert $page) => $page->has('filas', 2));
});

it('cae en el día de hoy cuando la fecha pedida no sirve', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('disponibilidad.index', ['fecha' => 'no-es-una-fecha']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('fecha', now()->toDateString()));
});

it('entrega las alertas junto a cada fila', function (): void {
    // Carga registrada pero sin destino: falta un extremo de la ruta.
    EstadoUnidad::factory()->create([
        'fecha' => '2026-07-21',
        'tipo_carga' => TipoCarga::Concentrado,
        'origen' => 'Juliaca',
        'destino' => null,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('disponibilidad.index', ['fecha' => '2026-07-21']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas.0.improbables', 1)
            ->where('filas.0.alertas.0.tipo', TipoAlerta::RutaIncompleta->value)
            ->where('resumen.improbables', 1)
        );
});

it('crea el estado del día cuando la celda editada no tenía nada', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'tipo_carga',
            'valor' => TipoCarga::Vacio->value,
        ])
        ->assertRedirect();

    $estado = EstadoUnidad::query()->where('tracto_id', $tracto->id)->firstOrFail();

    expect($estado->fecha->toDateString())->toBe('2026-07-21')
        ->and($estado->tipo_carga)->toBe(TipoCarga::Vacio)
        ->and($estado->origenDe('tipo_carga'))->toBe(OrigenDato::Manual);
});

it('actualiza el estado existente sin pisar los demás campos', function (): void {
    $estado = EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->create(['fecha' => '2026-07-21']);

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $estado->tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'ubicacion',
            'valor' => 'Nazca',
        ])
        ->assertRedirect();

    $estado->refresh();

    expect($estado->ubicacion)->toBe('Nazca')
        ->and($estado->origenDe('ubicacion'))->toBe(OrigenDato::Manual)
        ->and($estado->tipo_carga)->toBe(TipoCarga::Concentrado);
});

it('permite volver a editar un campo ya confirmado a mano', function (): void {
    $estado = EstadoUnidad::factory()
        ->en('Nazca')
        ->confirmado(['ubicacion'])
        ->create(['fecha' => '2026-07-21']);

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $estado->tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'ubicacion',
            'valor' => 'Pisco',
        ])
        ->assertRedirect();

    $estado->refresh();

    // El endpoint de celda ES la vía manual: reeditar un campo manual lo pisa,
    // nunca se bloquea por admiteSobrescritura (eso protege de la importación).
    expect($estado->ubicacion)->toBe('Pisco');
});

it('no marca origen para observaciones', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'observaciones',
            'valor' => 'Llanta baja',
        ])
        ->assertRedirect();

    $estado = EstadoUnidad::query()->where('tracto_id', $tracto->id)->firstOrFail();

    expect($estado->observaciones)->toBe('Llanta baja')
        ->and($estado->origenDe('observaciones'))->toBeNull();
});

it('deja anotar las próximas paradas de un viaje particular', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'proximas_paradas',
            'valor' => 'AQP -> Tacna -> Juliaca',
        ])
        ->assertRedirect();

    $estado = EstadoUnidad::query()->where('tracto_id', $tracto->id)->firstOrFail();

    expect($estado->proximas_paradas)->toBe('AQP -> Tacna -> Juliaca')
        ->and($estado->origenDe('proximas_paradas'))->toBeNull();
});

it('deja anotar cuándo queda libre la unidad', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'fecha_disponible',
            'valor' => '2026-08-02',
        ])
        ->assertRedirect();

    $estado = EstadoUnidad::query()->where('tracto_id', $tracto->id)->firstOrFail();

    expect($estado->fecha->toDateString())->toBe('2026-07-21')
        ->and($estado->fecha_disponible->toDateString())->toBe('2026-08-02')
        ->and($estado->origenDe('fecha_disponible'))->toBeNull();
});

it('rechaza un valor que no es una fecha para fecha_disponible', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'fecha_disponible',
            'valor' => 'no-es-una-fecha',
        ])
        ->assertSessionHasErrors('valor');
});

it('arrastra la fecha disponible junto con el resto del estado', function (): void {
    $ayer = EstadoUnidad::factory()->create([
        'fecha' => '2026-07-20',
        'fecha_disponible' => '2026-07-25',
    ]);

    actingAs(actorConRol('admin'))->post(route('disponibilidad.arrastrar'), ['fecha' => '2026-07-21']);

    $hoy = EstadoUnidad::query()->delDia('2026-07-21')->firstOrFail();

    expect($hoy->fecha_disponible->toDateString())->toBe('2026-07-25');
});

it('solo acepta campos de la whitelist', function (): void {
    $tracto = Vehiculo::factory()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $tracto), [
            'fecha' => '2026-07-21',
            'campo' => 'id',
            'valor' => '1',
        ])
        ->assertSessionHasErrors('campo');
});

it('no acepta un vehículo que no sea tracto', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs(actorConRol('admin'))
        ->patch(route('disponibilidad.celda', $carreta), [
            'fecha' => '2026-07-21',
            'campo' => 'observaciones',
            'valor' => 'x',
        ])
        ->assertNotFound();
});

it('vacía la carga y la ruta sin tocar carreta ni conductor', function (): void {
    $estado = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->en('Nazca')
        ->create(['fecha' => '2026-07-21']);

    $carretaId = $estado->carreta_id;
    $conductorId = $estado->conductor_id;

    actingAs(actorConRol('admin'))
        ->delete(route('disponibilidad.destroy', $estado))
        ->assertRedirect(route('disponibilidad.index', ['fecha' => '2026-07-21']));

    $estado->refresh();

    expect(EstadoUnidad::query()->count())->toBe(1)
        ->and($estado->carreta_id)->toBe($carretaId)
        ->and($estado->conductor_id)->toBe($conductorId)
        ->and($estado->tipo_carga)->toBeNull()
        ->and($estado->cliente)->toBeNull()
        ->and($estado->origen)->toBeNull()
        ->and($estado->destino)->toBeNull()
        ->and($estado->ubicacion)->toBeNull();
});

it('arrastra al día las unidades del último día registrado', function (): void {
    $ayer = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->en('Nazca')
        ->create(['fecha' => '2026-07-20']);

    actingAs(actorConRol('admin'))
        ->post(route('disponibilidad.arrastrar'), ['fecha' => '2026-07-21'])
        ->assertRedirect(route('disponibilidad.index', ['fecha' => '2026-07-21']));

    $hoy = EstadoUnidad::query()->delDia('2026-07-21')->firstOrFail();

    expect($hoy->tracto_id)->toBe($ayer->tracto_id)
        ->and($hoy->tipo_carga)->toBe(TipoCarga::Concentrado)
        ->and($hoy->ubicacion)->toBe($ayer->ubicacion);
});

it('arrastra sin heredar lo que ya estaba confirmado', function (): void {
    // Un día nuevo es una observación nueva: lo de ayer vuelve a ser suposición
    // hasta que alguien lo mire.
    $ayer = EstadoUnidad::factory()->en('Nazca')->create(['fecha' => '2026-07-20']);
    $ayer->confirmar(['ubicacion'])->save();

    actingAs(actorConRol('admin'))->post(route('disponibilidad.arrastrar'), ['fecha' => '2026-07-21']);

    $hoy = EstadoUnidad::query()->delDia('2026-07-21')->firstOrFail();

    expect($hoy->esManual('ubicacion'))->toBeFalse();
});

it('no pisa las unidades que ya estaban cargadas en el día', function (): void {
    $tracto = Vehiculo::factory()->create();

    EstadoUnidad::factory()->en('Nazca')->create([
        'tracto_id' => $tracto->id,
        'fecha' => '2026-07-20',
    ]);
    EstadoUnidad::factory()->en('Pisco')->create([
        'tracto_id' => $tracto->id,
        'fecha' => '2026-07-21',
    ]);

    actingAs(actorConRol('admin'))->post(route('disponibilidad.arrastrar'), ['fecha' => '2026-07-21']);

    expect(EstadoUnidad::query()->delDia('2026-07-21')->count())->toBe(1)
        ->and(EstadoUnidad::query()->delDia('2026-07-21')->first()->ubicacion)->toBe('Pisco');
});

it('anuncia cuántas unidades quedan por arrastrar', function (): void {
    EstadoUnidad::factory()->create(['fecha' => '2026-07-20']);
    EstadoUnidad::factory()->create(['fecha' => '2026-07-20']);

    actingAs(actorConRol('admin'))
        ->get(route('disponibilidad.index', ['fecha' => '2026-07-21']))
        ->assertInertia(fn (Assert $page) => $page->where('arrastrables', 2));
});

it('deja mirar al visor pero no tocar', function (): void {
    $estado = EstadoUnidad::factory()->create(['fecha' => '2026-07-21']);
    $visor = actorConRol('visor');

    actingAs($visor)->get(route('disponibilidad.index'))->assertSuccessful();
    actingAs($visor)->patch(route('disponibilidad.celda', $estado->tracto), [
        'fecha' => '2026-07-21',
        'campo' => 'observaciones',
        'valor' => 'x',
    ])->assertForbidden();
    actingAs($visor)->delete(route('disponibilidad.destroy', $estado))->assertForbidden();
    actingAs($visor)->post(route('disponibilidad.arrastrar'), ['fecha' => '2026-07-21'])->assertForbidden();
});

it('exige sesión para editar una celda', function (): void {
    $tracto = Vehiculo::factory()->create();

    $this->patch(route('disponibilidad.celda', $tracto), [
        'fecha' => '2026-07-21',
        'campo' => 'observaciones',
        'valor' => 'x',
    ])->assertRedirect(route('login'));
});
