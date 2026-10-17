<?php

use App\Enums\EstadoMantenimiento;
use App\Enums\EstadoOrdenTrabajo;
use App\Enums\Permiso;
use App\Enums\TipoOrdenTrabajo;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\ServicioMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\ProximoMantenimiento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Storage::fake('public');

    foreach (['admin', 'visor', 'contador'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

/** El servicio Scania del plan que carga la migración. */
function servicioScania(string $codigo): ServicioMantenimiento
{
    return PlanMantenimiento::deMarca('SCANIA')->servicios()->where('codigo', $codigo)->sole();
}

function scania(): Vehiculo
{
    return Vehiculo::factory()->create(['marca' => 'SCANIA']);
}

/** Una preventiva finalizada del servicio Scania indicado, a ese km. */
function hecho(Vehiculo $vehiculo, string $codigo, int $km, string $fecha = '2026-09-01'): OrdenTrabajo
{
    return OrdenTrabajo::factory()->create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => servicioScania($codigo)->id,
        'estado' => EstadoOrdenTrabajo::Finalizada,
        'kilometraje' => $km,
        'fecha' => $fecha,
    ]);
}

function km(Vehiculo $vehiculo, int $km): LecturaKilometraje
{
    return LecturaKilometraje::factory()->create(['vehiculo_id' => $vehiculo->id, 'kilometraje' => $km]);
}

/** Alguien de taller: registra km y órdenes, sin ver nada más. */
function tallerista(): User
{
    $rol = Role::findOrCreate('taller', 'web');
    $rol->syncPermissions([Permiso::MantenimientoVer->value, Permiso::MantenimientoRegistrarKm->value, Permiso::MantenimientoGestionar->value]);

    return User::factory()->create()->assignRole($rol);
}

// ─── El cálculo ─────────────────────────────────────────────────────────────

/**
 * Tras una S a los 300 mil, la X toca a los 320 mil y la S a los 340 mil.
 * La M y la L nunca se hicieron: no hay de dónde contarlas.
 */
it('counts the next services from the last preventiva of their level or above', function (): void {
    $unidad = scania();
    hecho($unidad, 'S', 300000);
    km($unidad, 315000);

    $estado = app(ProximoMantenimiento::class)->paraVehiculo($unidad);
    $servicios = collect($estado['servicios'])->keyBy('codigo');

    expect($estado['km_actual'])->toBe(315000)
        ->and($servicios['X']['proximo_km'])->toBe(320000)
        ->and($servicios['X']['faltan_km'])->toBe(5000)
        ->and($servicios['S']['proximo_km'])->toBe(340000)
        ->and($servicios['M']['estado'])->toBe(EstadoMantenimiento::SinDatos)
        ->and($estado['proximo']['codigo'])->toBe('X')
        ->and($estado['estado'])->toBe(EstadoMantenimiento::AlDia);
});

it('restarts every lower level when the L is done', function (): void {
    $unidad = scania();
    hecho($unidad, 'S', 300000);
    hecho($unidad, 'L', 400000);
    km($unidad, 401000);

    $servicios = collect(app(ProximoMantenimiento::class)->paraVehiculo($unidad)['servicios'])->keyBy('codigo');

    expect($servicios->pluck('proximo_km', 'codigo')->all())
        ->toBe(['X' => 420000, 'S' => 440000, 'M' => 480000, 'L' => 560000]);
});

it('turns amber when the next service is close and red once it is passed', function (): void {
    $cerca = scania();
    hecho($cerca, 'S', 300000);
    km($cerca, 318000);

    $pasada = scania();
    hecho($pasada, 'S', 300000);
    km($pasada, 341000);

    $estados = app(ProximoMantenimiento::class)->paraFlota(collect([$cerca, $pasada]));

    expect($estados[$cerca->id]['estado'])->toBe(EstadoMantenimiento::Proximo)
        ->and($estados[$pasada->id]['estado'])->toBe(EstadoMantenimiento::Vencido)
        // La X y la S ya pasaron: lo que toca es la S, que incluye la X.
        ->and($estados[$pasada->id]['proximo']['codigo'])->toBe('S');
});

it('prefers the higher level when two services fall on the same km', function (): void {
    $unidad = scania();
    hecho($unidad, 'S', 300000);
    hecho($unidad, 'X', 320000);
    km($unidad, 330000);

    // La X vuelve a tocar a los 340 mil, justo cuando toca la S.
    expect(app(ProximoMantenimiento::class)->paraVehiculo($unidad)['proximo']['codigo'])->toBe('S');
});

it('also expires a service by time when it has an interval in months', function (): void {
    $servicio = servicioScania('S');
    $servicio->update(['intervalo_meses' => 6]);

    $unidad = scania();
    hecho($unidad, 'S', 300000, now()->subMonths(7)->toDateString());
    km($unidad, 305000);

    $servicios = collect(app(ProximoMantenimiento::class)->paraVehiculo($unidad)['servicios'])->keyBy('codigo');

    expect($servicios['S']['estado'])->toBe(EstadoMantenimiento::Vencido);
});

it('takes the km from an orden when it is ahead of the last lectura', function (): void {
    $unidad = scania();
    km($unidad, 290000);
    hecho($unidad, 'S', 300000);

    expect(app(ProximoMantenimiento::class)->paraVehiculo($unidad)['km_actual'])->toBe(300000);
});

it('says sin datos instead of guessing, and leaves brands without a plan out', function (): void {
    $sinNada = scania();
    $foton = Vehiculo::factory()->create(['marca' => 'FOTON']);
    km($foton, 100000);

    $estados = app(ProximoMantenimiento::class)->paraFlota(collect([$sinNada, $foton]));

    expect($estados[$sinNada->id]['estado'])->toBe(EstadoMantenimiento::SinDatos)
        ->and($estados[$sinNada->id]['proximo'])->toBeNull()
        ->and($estados[$foton->id]['plan'])->toBeNull()
        ->and($estados[$foton->id]['km_actual'])->toBe(100000);
});

// ─── Pantallas ──────────────────────────────────────────────────────────────

/**
 * Por ahora el módulo es solo del admin: ningún rol base lo recibe al
 * sincronizar los permisos, y se le da a otro rol a mano desde el panel.
 */
it('keeps mantenimiento for the admin only', function (string $rol): void {
    Role::findOrCreate('operaciones', 'web');

    actingAs(actorConRol($rol))->get(route('mantenimiento.index'))->assertForbidden();
    actingAs(actorConRol($rol))->get(route('mantenimiento.ordenes.create'))->assertForbidden();
    actingAs(actorConRol($rol))
        ->post(route('mantenimiento.kilometrajes.store'), ['vehiculo_id' => scania()->id, 'kilometraje' => 1000])
        ->assertForbidden();
})->with(['visor', 'contador', 'operaciones']);

it('lets the admin in', function (): void {
    actingAs(actorConRol('admin'))->get(route('mantenimiento.index'))->assertSuccessful();
});

it('lists the units with a plan, the overdue ones first', function (): void {
    $alDia = scania();
    hecho($alDia, 'S', 300000);
    km($alDia, 301000);

    $vencida = scania();
    hecho($vencida, 'S', 300000);
    km($vencida, 345000);

    Vehiculo::factory()->create(['marca' => 'FOTON']);

    actingAs(tallerista())
        ->get(route('mantenimiento.preventivo'))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/preventivo')
            ->has('unidades', 2)
            ->where('unidades.0.id', $vencida->id)
            ->where('unidades.0.estado', 'vencido')
            // El km se registra para todos los tractos, tengan plan o no.
            ->has('tractos', 3)
        );
});

it('shows the parts for the next service, including the levels it contains', function (): void {
    $unidad = scania();
    hecho($unidad, 'X', 300000);
    hecho($unidad, 'S', 280000);
    km($unidad, 315000);

    // Próxima: la S a los 320 mil, que lleva lo de la X (4) y lo suyo (3).
    actingAs(tallerista())
        ->get(route('mantenimiento.show', $unidad))
        ->assertInertia(fn ($page) => $page
            ->component('mantenimiento/show')
            ->where('unidad.proximo.codigo', 'S')
            ->has('repuestos', 7)
            ->has('ordenes', 2)
        );
});

// ─── Kilometraje ────────────────────────────────────────────────────────────

it('registers the km with its dashboard photo and says what is left', function (): void {
    $unidad = scania();
    hecho($unidad, 'S', 300000);

    actingAs(tallerista())
        ->post(route('mantenimiento.kilometrajes.store'), [
            'vehiculo_id' => $unidad->id,
            'kilometraje' => 312500,
            'foto' => UploadedFile::fake()->image('tablero.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.message', "Km de {$unidad->placa} registrado. Faltan 7,500 km para el Servicio X.");

    $lectura = LecturaKilometraje::query()->sole();

    expect($lectura->kilometraje)->toBe(312500)
        ->and($lectura->getFirstMedia('foto'))->not->toBeNull();
});

it('rejects a km lower than the last one known', function (): void {
    $unidad = scania();
    km($unidad, 300000);

    actingAs(tallerista())
        ->post(route('mantenimiento.kilometrajes.store'), ['vehiculo_id' => $unidad->id, 'kilometraje' => 299000])
        ->assertSessionHasErrors('kilometraje');

    expect(LecturaKilometraje::query()->count())->toBe(1);
});

it('does not take a km for a carreta', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs(tallerista())
        ->post(route('mantenimiento.kilometrajes.store'), ['vehiculo_id' => $carreta->id, 'kilometraje' => 1000])
        ->assertSessionHasErrors('vehiculo_id');
});

// ─── Órdenes de trabajo ─────────────────────────────────────────────────────

it('registers a preventiva with its photos and restarts the plan from it', function (): void {
    $unidad = scania();
    hecho($unidad, 'S', 300000);
    km($unidad, 339000);

    actingAs(tallerista())
        ->post(route('mantenimiento.ordenes.store'), [
            'vehiculo_id' => $unidad->id,
            'tipo' => 'preventiva',
            'servicio_mantenimiento_id' => servicioScania('S')->id,
            'estado' => 'finalizada',
            'fecha' => '2026-10-14',
            'kilometraje' => 340000,
            'trabajo' => 'Servicio S completo',
            'fotos_antes' => [UploadedFile::fake()->image('antes.jpg')],
            'fotos_despues' => [UploadedFile::fake()->image('despues-1.jpg'), UploadedFile::fake()->image('despues-2.jpg')],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('mantenimiento.show', $unidad));

    $orden = OrdenTrabajo::query()->latest('id')->first();
    $estado = app(ProximoMantenimiento::class)->paraVehiculo($unidad);

    expect($orden->getMedia('antes'))->toHaveCount(1)
        ->and($orden->getMedia('despues'))->toHaveCount(2)
        ->and($estado['proximo']['codigo'])->toBe('X')
        ->and($estado['proximo']['proximo_km'])->toBe(360000);
});

it('asks for the km of a finished preventiva', function (): void {
    actingAs(tallerista())
        ->post(route('mantenimiento.ordenes.store'), [
            'vehiculo_id' => scania()->id,
            'tipo' => 'preventiva',
            'servicio_mantenimiento_id' => servicioScania('S')->id,
            'estado' => 'finalizada',
            'fecha' => '2026-10-14',
            'trabajo' => 'Servicio S',
        ])
        ->assertSessionHasErrors('kilometraje');
});

it('rejects a service from another plan and a preventiva for a brand without plan', function (): void {
    $otroPlan = ServicioMantenimiento::factory()->create();
    $foton = Vehiculo::factory()->create(['marca' => 'FOTON']);

    $datos = ['tipo' => 'preventiva', 'estado' => 'pendiente', 'fecha' => '2026-10-14', 'trabajo' => 'Servicio'];

    actingAs(tallerista())
        ->post(route('mantenimiento.ordenes.store'), [...$datos, 'vehiculo_id' => scania()->id, 'servicio_mantenimiento_id' => $otroPlan->id])
        ->assertSessionHasErrors('servicio_mantenimiento_id');

    actingAs(tallerista())
        ->post(route('mantenimiento.ordenes.store'), [...$datos, 'vehiculo_id' => $foton->id, 'servicio_mantenimiento_id' => servicioScania('S')->id])
        ->assertSessionHasErrors('servicio_mantenimiento_id');
});

it('registers a correctiva for any tracto without touching the plan', function (): void {
    $foton = Vehiculo::factory()->create(['marca' => 'FOTON']);

    actingAs(tallerista())
        ->post(route('mantenimiento.ordenes.store'), [
            'vehiculo_id' => $foton->id,
            'tipo' => 'correctiva',
            'estado' => 'en_proceso',
            'fecha' => '2026-10-14',
            'trabajo' => 'Cambio de pastillas de freno',
        ])
        ->assertSessionHasNoErrors();

    expect(OrdenTrabajo::query()->sole()->estado)->toBe(EstadoOrdenTrabajo::EnProceso);
});

it('adds photos when the orden is updated and removes only its own', function (): void {
    $orden = OrdenTrabajo::factory()->create(['vehiculo_id' => scania()->id, 'estado' => EstadoOrdenTrabajo::EnProceso]);
    $ajena = OrdenTrabajo::factory()->create();
    $fotoAjena = $ajena->addMedia(UploadedFile::fake()->image('otra.jpg'))->toMediaCollection('antes');

    actingAs(tallerista())
        ->post(route('mantenimiento.ordenes.update', $orden), [
            '_method' => 'PUT',
            'tipo' => 'correctiva',
            'estado' => 'finalizada',
            'fecha' => '2026-10-14',
            'trabajo' => 'Listo',
            'fotos_despues' => [UploadedFile::fake()->image('despues.jpg')],
        ])
        ->assertSessionHasNoErrors();

    $foto = $orden->fresh()->getFirstMedia('despues');

    expect($orden->fresh()->estado)->toBe(EstadoOrdenTrabajo::Finalizada)
        ->and($foto)->not->toBeNull();

    actingAs(tallerista())
        ->delete(route('mantenimiento.ordenes.fotos.destroy', [$orden, $fotoAjena]))
        ->assertNotFound();

    actingAs(tallerista())
        ->delete(route('mantenimiento.ordenes.fotos.destroy', [$orden, $foto]))
        ->assertRedirect();

    expect($orden->fresh()->getMedia('despues'))->toHaveCount(0)
        ->and($ajena->fresh()->getMedia('antes'))->toHaveCount(1);
});
