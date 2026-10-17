<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\Permiso;
use App\Enums\TipoOrdenTrabajo;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\ProximoMantenimiento;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
});

/** Quien puede configurar los planes, y nada más del módulo que verlo. */
function configuradorDePlanes(): User
{
    $rol = Role::findOrCreate('jefe_taller', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoConfigurar->value]);

    return User::factory()->create()->assignRole($rol);
}

/**
 * El plan tal como lo manda la pantalla, armado desde lo que está en la base,
 * con los cambios que pida cada prueba.
 *
 * @return array{nombre: string, servicios: list<array<string, mixed>>}
 */
function planComoFormulario(PlanMantenimiento $plan): array
{
    return [
        'nombre' => $plan->nombre,
        'servicios' => $plan->servicios()->with('repuestos')->get()
            ->map(fn ($servicio): array => [
                'codigo' => $servicio->codigo,
                'nombre' => $servicio->nombre,
                'intervalo_km' => $servicio->intervalo_km,
                'intervalo_meses' => $servicio->intervalo_meses,
                'repuestos' => $servicio->repuestos
                    ->map(fn ($repuesto): array => $repuesto->only(['descripcion', 'codigo', 'cantidad', 'unidad']))
                    ->all(),
            ])
            ->all(),
    ];
}

it('loads the four brands on the same X · S · M · L levels', function (): void {
    $planes = PlanMantenimiento::query()->with('servicios')->get()->keyBy('marca');

    expect($planes->keys()->sort()->values()->all())->toBe(['INTERNATIONAL', 'MERCEDES BENZ', 'SCANIA', 'VOLVO'])
        ->and($planes['SCANIA']->servicios->pluck('intervalo_km', 'codigo')->all())->toBe(['X' => 20000, 'S' => 40000, 'M' => 80000, 'L' => 160000])
        ->and($planes['MERCEDES BENZ']->servicios->pluck('intervalo_km', 'codigo')->all())->toBe(['X' => 60000, 'S' => 120000, 'M' => 240000])
        ->and($planes['VOLVO']->servicios->pluck('intervalo_km', 'codigo')->all())->toBe(['X' => 25000, 'S' => 75000, 'M' => 150000])
        ->and($planes['INTERNATIONAL']->servicios->pluck('intervalo_km', 'codigo')->all())->toBe(['X' => 40000, 'S' => 80000, 'M' => 160000, 'L' => 320000]);
});

it('sums up the fleet per brand on the dashboard, without the list of units', function (): void {
    Vehiculo::factory()->create(['marca' => 'MERCEDES BENZ']);
    Vehiculo::factory()->create(['marca' => 'SCANIA']);

    actingAs(actorConRol('admin'))
        ->get(route('mantenimiento.index'))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/index')
            ->missing('unidades.0')
            ->where('unidades', 2)
            ->has('marcas', 4)
            ->where('marcas.1.marca', 'MERCEDES BENZ')
            ->where('marcas.1.logo_url', asset('images/marcas/mercedes-benz.png'))
            ->has('marcas.1.niveles', 3)
            ->where('marcas.1.conteo.sin_datos', 1)
            ->where('marcas.0.conteo.sin_datos', 0)
            ->where('resumen.3.cantidad', 2)
        );
});

it('opens the preventivo already filtered from the dashboard', function (): void {
    $mercedes = Vehiculo::factory()->create(['marca' => 'MERCEDES BENZ']);

    actingAs(actorConRol('admin'))
        ->get(route('mantenimiento.preventivo', ['marca' => 'mercedes benz', 'estado' => 'vencido', 'desactualizados' => 1]))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/preventivo')
            ->where('filtros', ['marca' => 'MERCEDES BENZ', 'estado' => 'vencido', 'desactualizados' => true])
            ->where('unidades.0.id', $mercedes->id)
            ->where('unidades.0.plan_id', PlanMantenimiento::deMarca('MERCEDES BENZ')->id)
        );
});

it('keeps the plan configuration for whoever has its permission', function (): void {
    $plan = PlanMantenimiento::deMarca('VOLVO');

    $taller = Role::findOrCreate('taller', 'web');
    $taller->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoGestionar->value]);
    $tallerista = User::factory()->create()->assignRole($taller);

    actingAs($tallerista)->get(route('mantenimiento.planes.edit', $plan))->assertForbidden();
    actingAs($tallerista)->put(route('mantenimiento.planes.update', $plan), planComoFormulario($plan))->assertForbidden();

    actingAs(configuradorDePlanes())
        ->get(route('mantenimiento.planes.edit', $plan))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/plan')
            ->where('plan.marca', 'VOLVO')
            ->has('plan.servicios', 3)
            ->has('plan.servicios.0.repuestos', 7)
            ->has('niveles', 4)
        );
});

it('saves the brand intervals and items, and the semáforo follows them', function (): void {
    $plan = PlanMantenimiento::deMarca('SCANIA');
    $unidad = Vehiculo::factory()->create(['marca' => 'SCANIA']);
    OrdenTrabajo::factory()->create([
        'vehiculo_id' => $unidad->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => $plan->servicios()->where('codigo', 'X')->value('id'),
        'estado' => EstadoOrdenTrabajo::Finalizada,
        'kilometraje' => 100000,
        'fecha' => '2026-09-01',
    ]);
    LecturaKilometraje::factory()->create(['vehiculo_id' => $unidad->id, 'kilometraje' => 112000]);

    // En la base la X se hace cada 15 mil, no cada 20 mil, y lleva un ítem más.
    $datos = planComoFormulario($plan);
    $datos['servicios'][0]['intervalo_km'] = 15000;
    $datos['servicios'][0]['intervalo_meses'] = 4;
    $datos['servicios'][0]['repuestos'][] = ['descripcion' => 'Revisión de frenos', 'codigo' => null, 'cantidad' => null, 'unidad' => null];

    actingAs(configuradorDePlanes())
        ->put(route('mantenimiento.planes.update', $plan), $datos)
        ->assertRedirect(route('mantenimiento.preventivo', ['marca' => 'SCANIA']));

    $x = $plan->servicios()->where('codigo', 'X')->sole();

    expect($x->intervalo_km)->toBe(15000)
        ->and($x->intervalo_meses)->toBe(4)
        ->and($x->repuestos()->pluck('descripcion')->last())->toBe('Revisión de frenos')
        ->and(OrdenTrabajo::query()->sole()->servicio_mantenimiento_id)->toBe($x->id);

    $proximo = app(ProximoMantenimiento::class)->paraVehiculo($unidad)['proximo'];

    expect($proximo['codigo'])->toBe('X')
        ->and($proximo['proximo_km'])->toBe(115000);
});

it('rejects levels out of order, skipped or without more km than the one below', function (): void {
    $plan = PlanMantenimiento::deMarca('VOLVO');
    $datos = planComoFormulario($plan);

    $menor = $datos;
    $menor['servicios'][1]['intervalo_km'] = 25000;

    actingAs(configuradorDePlanes())
        ->put(route('mantenimiento.planes.update', $plan), $menor)
        ->assertSessionHasErrors('servicios.1.intervalo_km');

    $salteado = $datos;
    $salteado['servicios'][1]['codigo'] = 'M';
    $salteado['servicios'][2]['codigo'] = 'L';

    actingAs(configuradorDePlanes())
        ->put(route('mantenimiento.planes.update', $plan), $salteado)
        ->assertSessionHasErrors('servicios');

    expect($plan->servicios()->pluck('intervalo_km', 'codigo')->all())
        ->toBe(['X' => 25000, 'S' => 75000, 'M' => 150000]);
});

it('adds and removes levels, but not one that already has órdenes', function (): void {
    $plan = PlanMantenimiento::deMarca('MERCEDES BENZ');
    $datos = planComoFormulario($plan);

    $conL = $datos;
    $conL['servicios'][] = ['codigo' => 'L', 'nombre' => 'Servicio L', 'intervalo_km' => 480000, 'intervalo_meses' => null, 'repuestos' => [
        ['descripcion' => 'Calibración de inyectores', 'codigo' => null, 'cantidad' => 1, 'unidad' => 'serv'],
    ]];

    actingAs(configuradorDePlanes())->put(route('mantenimiento.planes.update', $plan), $conL)->assertSessionHasNoErrors();

    expect($plan->servicios()->pluck('nivel', 'codigo')->all())->toBe(['X' => 1, 'S' => 2, 'M' => 3, 'L' => 4]);

    OrdenTrabajo::factory()->create([
        'vehiculo_id' => Vehiculo::factory()->create(['marca' => 'MERCEDES BENZ'])->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => $plan->servicios()->where('codigo', 'L')->value('id'),
    ]);

    actingAs(configuradorDePlanes())
        ->put(route('mantenimiento.planes.update', $plan), $datos)
        ->assertSessionHasErrors('servicios');

    expect($plan->servicios()->count())->toBe(4);
});
