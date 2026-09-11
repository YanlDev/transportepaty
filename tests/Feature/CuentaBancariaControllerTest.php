<?php

use App\Models\CuentaBancaria;
use App\Models\Factura;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function datosCuenta(array $extra = []): array
{
    return [
        'banco' => 'BCP',
        'alias' => 'BCP Soles',
        'numero_cuenta' => '191-1234567-0-89',
        'cci' => '00219100123456708912',
        'moneda' => 'PEN',
        'activa' => true,
        'notas' => null,
        ...$extra,
    ];
}

it('keeps the visor out of the cuentas', function (): void {
    actingAs(actorConRol('visor'))
        ->get(route('cuentas-bancarias.index'))
        ->assertForbidden();

    actingAs(actorConRol('visor'))
        ->post(route('cuentas-bancarias.store'), datosCuenta())
        ->assertForbidden();
});

it('registers a cuenta', function (): void {
    actingAs(actorConRol('contador'))
        ->post(route('cuentas-bancarias.store'), datosCuenta())
        ->assertSessionHasNoErrors();

    $cuenta = CuentaBancaria::query()->sole();

    expect($cuenta->alias)->toBe('BCP Soles')
        ->and($cuenta->moneda->value)->toBe('PEN')
        ->and($cuenta->activa)->toBeTrue();
});

it('rejects the same numero de cuenta twice in the same banco', function (): void {
    CuentaBancaria::factory()->create(['banco' => 'BCP', 'numero_cuenta' => '191-1234567-0-89']);

    actingAs(actorConRol('contador'))
        ->post(route('cuentas-bancarias.store'), datosCuenta())
        ->assertSessionHasErrors('numero_cuenta');
});

it('deactivates a cuenta instead of losing it', function (): void {
    $cuenta = CuentaBancaria::factory()->create();

    actingAs(actorConRol('contador'))
        ->put(route('cuentas-bancarias.update', $cuenta), datosCuenta([
            'banco' => $cuenta->banco,
            'numero_cuenta' => $cuenta->numero_cuenta,
            'activa' => false,
        ]))
        ->assertSessionHasNoErrors();

    expect($cuenta->refresh()->activa)->toBeFalse();
});

/**
 * Borrar una cuenta que ya cobró dejaría sin rastro por dónde entró esa plata.
 * Para eso está la baja lógica.
 */
it('refuses to delete a cuenta that already cobró facturas', function (): void {
    $cuenta = CuentaBancaria::factory()->create();
    Factura::factory()->create([
        'fecha_pago' => '2026-09-01',
        'cuenta_bancaria_id' => $cuenta->id,
    ]);

    actingAs(actorConRol('contador'))
        ->delete(route('cuentas-bancarias.destroy', $cuenta))
        ->assertForbidden();

    expect(CuentaBancaria::query()->count())->toBe(1);
});

it('deletes a cuenta that never cobró anything', function (): void {
    $cuenta = CuentaBancaria::factory()->create();

    actingAs(actorConRol('contador'))
        ->delete(route('cuentas-bancarias.destroy', $cuenta))
        ->assertSessionHasNoErrors();

    expect(CuentaBancaria::query()->count())->toBe(0);
});

it('lists the cuentas with how many facturas each one cobró', function (): void {
    $cuenta = CuentaBancaria::factory()->create(['alias' => 'BCP Soles']);
    Factura::factory()->count(2)->create([
        'fecha_pago' => '2026-09-01',
        'cuenta_bancaria_id' => $cuenta->id,
    ]);

    actingAs(actorConRol('contador'))
        ->get(route('cuentas-bancarias.index'))
        ->assertInertia(fn ($page) => $page
            ->has('cuentas', 1)
            ->where('cuentas.0.facturas_count', 2)
        );
});
