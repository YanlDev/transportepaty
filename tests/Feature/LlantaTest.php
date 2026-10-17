<?php

use App\Enums\EstadoLlanta;
use App\Enums\Permiso;
use App\Enums\TipoMovimientoLlanta;
use App\Models\Llanta;
use App\Models\MovimientoLlanta;
use App\Models\ParametroMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\EstadoLlantas;
use App\Services\PosicionesLlanta;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/** El llantero: ve mantenimiento y registra llantas, nada más. */
function llantero(): User
{
    $rol = Role::findOrCreate('llantero', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoLlantas->value]);

    return User::factory()->create()->assignRole($rol);
}

/** Una llanta puesta en la posición, con su última medida si se da. */
function llantaEn(Vehiculo $vehiculo, string $posicion, ?float $mm = null): Llanta
{
    $llanta = Llanta::factory()->create(['vehiculo_id' => $vehiculo->id, 'posicion' => $posicion]);

    if ($mm !== null) {
        MovimientoLlanta::factory()->create(['llanta_id' => $llanta->id, 'profundidad_mm' => $mm]);
    }

    return $llanta;
}

it('numbers 10 positions on a tracto and 12 plus two spares on a carreta', function (): void {
    expect(PosicionesLlanta::codigos(Vehiculo::factory()->make()->tipo))->toBe(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'])
        ->and(PosicionesLlanta::codigos(Vehiculo::factory()->carreta()->make()->tipo))
        ->toBe(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', 'R1', 'R2']);
});

it('colours each position from its last measurement, and leaves empty ones without state', function (): void {
    ParametroMantenimiento::vigentes()->update(['llantas_revisar_mm' => 5, 'llantas_cambiar_mm' => 3]);

    $tracto = Vehiculo::factory()->create();
    $gastada = llantaEn($tracto, '1', 12);
    MovimientoLlanta::factory()->create(['llanta_id' => $gastada->id, 'profundidad_mm' => 2.5, 'fecha' => now()->addDay()->toDateString()]);
    llantaEn($tracto, '2', 4.5);
    llantaEn($tracto, '3', 10);
    llantaEn($tracto, '4');

    $estados = collect(app(EstadoLlantas::class)->paraVehiculo($tracto))->keyBy('codigo');

    expect($estados['1']['estado'])->toBe(EstadoLlanta::Cambiar)
        ->and($estados['1']['llanta']['profundidad_mm'])->toBe(2.5)
        ->and($estados['2']['estado'])->toBe(EstadoLlanta::Revisar)
        ->and($estados['3']['estado'])->toBe(EstadoLlanta::Bien)
        ->and($estados['4']['estado'])->toBe(EstadoLlanta::SinMedir)
        ->and($estados['5']['llanta'])->toBeNull()
        ->and($estados['5']['estado'])->toBeNull();
});

it('installs a tire on an empty position, never on an occupied one', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();
    $datos = ['tipo' => 'instalacion', 'fecha' => now()->toDateString(), 'condicion' => 'reencauchada', 'marca' => 'Bridgestone', 'medida' => '295/80R22.5', 'profundidad_mm' => 14];

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', $carreta), [...$datos, 'posicion' => 'R1'])
        ->assertSessionHasNoErrors();

    $llanta = Llanta::query()->sole();

    expect($llanta->posicion)->toBe('R1')
        ->and($llanta->movimientos()->sole()->tipo)->toBe(TipoMovimientoLlanta::Instalacion)
        ->and((float) $llanta->movimientos()->sole()->profundidad_mm)->toBe(14.0);

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', $carreta), [...$datos, 'posicion' => 'R1'])
        ->assertSessionHasErrors('posicion');

    // Un tracto no tiene posición de repuesto.
    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', Vehiculo::factory()->create()), [...$datos, 'posicion' => 'R1'])
        ->assertSessionHasErrors('posicion');
});

it('measures several tires in one round', function (): void {
    $tracto = Vehiculo::factory()->create();
    llantaEn($tracto, '1');
    llantaEn($tracto, '2');

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.medir', $tracto), [
            'fecha' => now()->toDateString(),
            'kilometraje' => 320000,
            'mediciones' => [['posicion' => '1', 'profundidad_mm' => '2.8'], ['posicion' => '2', 'profundidad_mm' => 9]],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'warning');

    expect(MovimientoLlanta::query()->where('tipo', TipoMovimientoLlanta::Inspeccion)->count())->toBe(2);

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.medir', $tracto), [
            'fecha' => now()->toDateString(),
            'mediciones' => [['posicion' => '3', 'profundidad_mm' => 9]],
        ])
        ->assertSessionHasErrors('mediciones.0.profundidad_mm');
});

it('swaps two tires when rotating onto an occupied position', function (): void {
    $tracto = Vehiculo::factory()->create();
    $delantera = llantaEn($tracto, '1');
    $trasera = llantaEn($tracto, '3');

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'rotacion', 'posicion' => '1', 'posicion_destino' => '3', 'fecha' => now()->toDateString()])
        ->assertSessionHasNoErrors();

    expect($delantera->refresh()->posicion)->toBe('3')
        ->and($trasera->refresh()->posicion)->toBe('1')
        ->and(MovimientoLlanta::query()->where('tipo', TipoMovimientoLlanta::Rotacion)->count())->toBe(2);

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'rotacion', 'posicion' => '3', 'posicion_destino' => '5', 'fecha' => now()->toDateString()])
        ->assertSessionHasNoErrors();

    expect($delantera->refresh()->posicion)->toBe('5');
});

it('retires a tire and frees its position; a repair needs to say what was done', function (): void {
    $tracto = Vehiculo::factory()->create();
    $llanta = llantaEn($tracto, '7');

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'reparacion', 'posicion' => '7', 'fecha' => now()->toDateString()])
        ->assertSessionHasErrors('observacion');

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'retiro', 'posicion' => '7', 'fecha' => now()->toDateString(), 'motivo_retiro' => 'desgaste', 'profundidad_mm' => 2, 'kilometraje' => 410000])
        ->assertSessionHasNoErrors();

    expect($llanta->refresh()->retirada_el?->toDateString())->toBe(now()->toDateString())
        ->and($llanta->km_retiro)->toBe(410000)
        ->and(Llanta::query()->montadas()->count())->toBe(0);

    actingAs(llantero())
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'retiro', 'posicion' => '7', 'fecha' => now()->toDateString(), 'motivo_retiro' => 'desgaste'])
        ->assertSessionHasErrors('posicion');
});

it('lists the units with tires to change first and shows the diagram of each', function (): void {
    $bien = Vehiculo::factory()->create(['placa' => 'AAA-111']);
    llantaEn($bien, '1', 12);
    $gastada = Vehiculo::factory()->create(['placa' => 'ZZZ-999']);
    llantaEn($gastada, '2', 2);

    actingAs(llantero())
        ->get(route('mantenimiento.llantas.index'))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/llantas/index')
            ->where('unidades.0.id', $gastada->id)
            ->where('unidades.0.cambiar', 1)
            ->where('unidades.1.id', $bien->id)
        );

    actingAs(llantero())
        ->get(route('mantenimiento.llantas.show', $gastada))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/llantas/show')
            ->has('posiciones', 10)
            ->where('posiciones.1.estado', 'cambiar')
            ->where('posiciones.0.estado', null)
            ->where('posiciones.0.estado_label', 'Vacía')
            ->has('movimientos', 1)
        );
});

it('lets whoever sees mantenimiento look at the tires, but only the llantero register', function (): void {
    $tracto = Vehiculo::factory()->create();

    $rol = Role::findOrCreate('mirador', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value]);
    $mirador = User::factory()->create()->assignRole($rol);

    actingAs($mirador)->get(route('mantenimiento.llantas.show', $tracto))->assertSuccessful();
    actingAs($mirador)
        ->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'instalacion', 'posicion' => '1', 'fecha' => now()->toDateString(), 'condicion' => 'nueva'])
        ->assertForbidden();

    actingAs(User::factory()->create())->get(route('mantenimiento.llantas.index'))->assertForbidden();
});

// ─── Correcciones ───────────────────────────────────────────────────────────

it('corrects the data of an installed tire', function (): void {
    $llanta = llantaEn(Vehiculo::factory()->create(), '1');

    actingAs(llantero())
        ->put(route('mantenimiento.llantas.update', $llanta), ['marca' => 'Goodyear', 'medida' => '315/80R22.5', 'codigo' => 'G-77', 'condicion' => 'reencauchada'])
        ->assertSessionHasNoErrors();

    expect($llanta->refresh()->only(['marca', 'medida', 'codigo']))->toBe(['marca' => 'Goodyear', 'medida' => '315/80R22.5', 'codigo' => 'G-77'])
        ->and($llanta->condicion->value)->toBe('reencauchada');
});

it('corrects a badly typed measurement, and the date of an installation on the tire too', function (): void {
    $tracto = Vehiculo::factory()->create();
    $llanta = llantaEn($tracto, '2');
    $instalacion = MovimientoLlanta::factory()->create(['llanta_id' => $llanta->id, 'tipo' => TipoMovimientoLlanta::Instalacion, 'profundidad_mm' => 16]);
    $medicion = MovimientoLlanta::factory()->create(['llanta_id' => $llanta->id, 'profundidad_mm' => 25, 'fecha' => now()->toDateString()]);

    actingAs(llantero())
        ->put(route('mantenimiento.llantas.movimientos.update', $medicion), ['fecha' => now()->toDateString(), 'profundidad_mm' => 2.5])
        ->assertSessionHasNoErrors();

    expect(app(EstadoLlantas::class)->paraVehiculo($tracto)[1]['estado'])->toBe(EstadoLlanta::Cambiar);

    actingAs(llantero())
        ->put(route('mantenimiento.llantas.movimientos.update', $instalacion), ['fecha' => '2026-08-01', 'kilometraje' => 290000, 'profundidad_mm' => 16])
        ->assertSessionHasNoErrors();

    expect($llanta->refresh()->instalada_el->toDateString())->toBe('2026-08-01')
        ->and($llanta->km_instalacion)->toBe(290000);

    actingAs(llantero())
        ->put(route('mantenimiento.llantas.movimientos.update', $medicion), ['fecha' => now()->toDateString()])
        ->assertSessionHasErrors('profundidad_mm');
});

it('deletes only what can be undone cleanly', function (): void {
    $tracto = Vehiculo::factory()->create();

    // Una llanta cargada por error: su instalación se elimina con ella.
    $porError = llantaEn($tracto, '1');
    $instalacion = MovimientoLlanta::factory()->create(['llanta_id' => $porError->id, 'tipo' => TipoMovimientoLlanta::Instalacion]);

    actingAs(llantero())->delete(route('mantenimiento.llantas.movimientos.destroy', $instalacion))->assertSessionHasNoErrors();
    expect(Llanta::query()->find($porError->id))->toBeNull();

    // Con una medición después, la instalación ya no se elimina, la medición sí.
    $llanta = llantaEn($tracto, '2');
    $instalada = MovimientoLlanta::factory()->create(['llanta_id' => $llanta->id, 'tipo' => TipoMovimientoLlanta::Instalacion, 'fecha' => now()->subDay()->toDateString()]);
    $medicion = MovimientoLlanta::factory()->create(['llanta_id' => $llanta->id, 'fecha' => now()->toDateString()]);

    actingAs(llantero())->delete(route('mantenimiento.llantas.movimientos.destroy', $instalada))->assertSessionHasErrors('movimiento');
    actingAs(llantero())->delete(route('mantenimiento.llantas.movimientos.destroy', $medicion))->assertSessionHasNoErrors();
    expect(MovimientoLlanta::query()->find($medicion->id))->toBeNull();

    // Un retiro se deshace mientras la posición siga libre.
    actingAs(llantero())->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'retiro', 'posicion' => '2', 'fecha' => now()->toDateString(), 'motivo_retiro' => 'dano']);
    $retiro = MovimientoLlanta::query()->where('tipo', TipoMovimientoLlanta::Retiro)->sole();

    actingAs(llantero())->delete(route('mantenimiento.llantas.movimientos.destroy', $retiro))->assertSessionHasNoErrors();
    expect($llanta->refresh()->retirada_el)->toBeNull()
        ->and($llanta->motivo_retiro)->toBeNull();

    // Una rotación nunca.
    actingAs(llantero())->post(route('mantenimiento.llantas.store', $tracto), ['tipo' => 'rotacion', 'posicion' => '2', 'posicion_destino' => '3', 'fecha' => now()->toDateString()]);
    $rotacion = MovimientoLlanta::query()->where('tipo', TipoMovimientoLlanta::Rotacion)->sole();

    actingAs(llantero())->delete(route('mantenimiento.llantas.movimientos.destroy', $rotacion))->assertSessionHasErrors('movimiento');

    actingAs(llantero())
        ->get(route('mantenimiento.llantas.show', $tracto))
        ->assertInertia(fn ($page) => $page
            ->where('movimientos.0.tipo', 'rotacion')
            ->where('movimientos.0.eliminable', false)
        );
});

it('lets only whoever configures the plans change the cuts of the semáforo', function (): void {
    actingAs(llantero())
        ->put(route('mantenimiento.llantas.parametros.update'), ['llantas_revisar_mm' => 6, 'llantas_cambiar_mm' => 4])
        ->assertForbidden();

    $rol = Role::findOrCreate('jefe_taller', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoConfigurar->value]);
    $jefe = User::factory()->create()->assignRole($rol);

    actingAs($jefe)
        ->put(route('mantenimiento.llantas.parametros.update'), ['llantas_revisar_mm' => 4, 'llantas_cambiar_mm' => 4])
        ->assertSessionHasErrors('llantas_cambiar_mm');

    actingAs($jefe)
        ->put(route('mantenimiento.llantas.parametros.update'), ['llantas_revisar_mm' => '6.5', 'llantas_cambiar_mm' => 4])
        ->assertSessionHasNoErrors();

    $tracto = Vehiculo::factory()->create();
    llantaEn($tracto, '1', 6);

    expect(ParametroMantenimiento::vigentes()->umbralesLlanta())->toBe(['revisar_mm' => 6.5, 'cambiar_mm' => 4.0])
        ->and(app(EstadoLlantas::class)->paraVehiculo($tracto)[0]['estado'])->toBe(EstadoLlanta::Revisar);
});

it('keeps corrections for the llantero', function (): void {
    $rol = Role::findOrCreate('mirador', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value]);
    $mirador = User::factory()->create()->assignRole($rol);

    $llanta = llantaEn(Vehiculo::factory()->create(), '1', 10);
    $medicion = $llanta->movimientos()->sole();

    actingAs($mirador)->put(route('mantenimiento.llantas.update', $llanta), ['condicion' => 'nueva'])->assertForbidden();
    actingAs($mirador)->put(route('mantenimiento.llantas.movimientos.update', $medicion), ['fecha' => now()->toDateString(), 'profundidad_mm' => 3])->assertForbidden();
    actingAs($mirador)->delete(route('mantenimiento.llantas.movimientos.destroy', $medicion))->assertForbidden();
});
