<?php

use App\Enums\TipoVehiculo;
use App\Models\Abastecimiento;
use App\Models\LecturaKilometraje;
use App\Models\Vehiculo;
use App\Services\OdometroDeUnidad;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'taller', 'abastecimiento'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $this->odometro = app(OdometroDeUnidad::class);
});

function lecturaDe(Vehiculo $tracto, int $km, string $cuando): LecturaKilometraje
{
    return LecturaKilometraje::factory()->create(['vehiculo_id' => $tracto->id, 'kilometraje' => $km, 'leido_en' => $cuando]);
}

it('does not let the odometer go back', function (): void {
    lecturaDe($this->tracto, 250000, now()->subDays(5)->toDateTimeString());

    expect($this->odometro->error($this->tracto, 249999))->toContain('no puede ser menor')
        ->and($this->odometro->error($this->tracto, 250000))->toBeNull()
        ->and($this->odometro->error($this->tracto, 251200))->toBeNull();
});

/**
 * El voucher se registra tarde: trae un km real, menor que el de la llegada
 * que ya se anotó. Se compara contra lo de antes y lo de después de su día,
 * no contra el mayor km de la historia.
 */
it('takes a late voucher whose km falls between the readings around its day', function (): void {
    lecturaDe($this->tracto, 250000, now()->subDays(10)->toDateTimeString());
    lecturaDe($this->tracto, 253000, now()->subDays(2)->toDateTimeString());
    $delVoucher = now()->subDays(6);

    expect($this->odometro->error($this->tracto, 251500, null, $delVoucher))->toBeNull()
        ->and($this->odometro->error($this->tracto, 249000, null, $delVoucher))->toContain('no puede ser menor')
        ->and($this->odometro->error($this->tracto, 254000, null, $delVoucher))->toContain('no puede ser mayor');
});

it('refuses a jump of more than 500,000 km as a typo', function (): void {
    lecturaDe($this->tracto, 150000, now()->subDays(5)->toDateTimeString());

    expect($this->odometro->error($this->tracto, 1500000))->toContain('revisa si sobra un dígito')
        ->and($this->odometro->error($this->tracto, 650000))->toBeNull()
        ->and($this->odometro->error($this->tracto, 650001))->not->toBeNull();
});

it('accepts the first km of a unit without a cap to compare against', function (): void {
    expect($this->odometro->error($this->tracto, 890000))->toBeNull();
});

it('keeps the reading on the day the odometer was read, not the day it was typed', function (): void {
    $delVoucher = now()->subDays(4);

    $this->odometro->anotar($this->tracto->id, 251500, null, 'Al cargar combustible', 'abastecimiento:1', $delVoucher);

    expect(LecturaKilometraje::query()->sole()->leido_en->toDateString())->toBe($delVoucher->toDateString());
});

it('rejects by hand a km lower than the last one, with the same rule', function (): void {
    lecturaDe($this->tracto, 250000, now()->subDays(1)->toDateTimeString());

    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.kilometrajes.store'), ['vehiculo_id' => $this->tracto->id, 'kilometraje' => 240000])
        ->assertSessionHasErrors('kilometraje');
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.kilometrajes.store'), ['vehiculo_id' => $this->tracto->id, 'kilometraje' => 950000])
        ->assertSessionHasErrors('kilometraje');
    actingAs(actorConRol('taller'))
        ->post(route('mantenimiento.kilometrajes.store'), ['vehiculo_id' => $this->tracto->id, 'kilometraje' => 250800])
        ->assertSessionHasNoErrors();
});

/**
 * Quitar una lectura cambia el km de la unidad: lo hace el administrador, no
 * quien registra kilometrajes.
 */
it('lets only the admin remove a km already registered', function (): void {
    $lectura = lecturaDe($this->tracto, 250000, now()->subDays(1)->toDateTimeString());

    actingAs(actorConRol('taller'))->delete(route('mantenimiento.kilometrajes.destroy', $lectura))->assertForbidden();
    expect(LecturaKilometraje::query()->count())->toBe(1);

    actingAs(actorConRol('admin'))->delete(route('mantenimiento.kilometrajes.destroy', $lectura))->assertSessionHasNoErrors();
    expect(LecturaKilometraje::query()->count())->toBe(0);
});

it('drops the km of a combustible when it is annulled', function (): void {
    $registro = Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'kilometraje' => 251000]);
    $this->odometro->anotar($this->tracto->id, 251000, null, 'Al cargar combustible', OdometroDeUnidad::deDespacho($registro->id));

    actingAs(actorConRol('admin'))
        ->post(route('abastecimiento.anular', $registro), ['motivo' => 'Era de otra unidad'])
        ->assertSessionHasNoErrors();

    expect(LecturaKilometraje::query()->count())->toBe(0)
        ->and($this->odometro->error($this->tracto, 200000))->toBeNull();
});
