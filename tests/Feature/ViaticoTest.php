<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\HistorialCambio;
use App\Models\LineaReglaAbastecimiento;
use App\Models\ParadaVuelta;
use App\Models\PuntoAbastecimiento;
use App\Models\ReglaAbastecimiento;
use App\Models\RevisionTaller;
use App\Models\Vehiculo;
use App\Models\Viatico;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Los viáticos de cada ticket: se depositan (los de la pizarra o un
 * imprevisto), se corrigen y se anulan con su motivo. Nunca se borran y cada
 * movimiento queda en el historial.
 */
beforeEach(function (): void {
    foreach (['admin', 'contador', 'visor'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'VEQ-825']);
    $this->vuelta = Vuelta::factory()->programada()->create(['tracto_id' => $tracto->id, 'inicio' => now()->toDateString()]);
    ParadaVuelta::factory()->create([
        'vuelta_id' => $this->vuelta->id, 'sentido' => SentidoTramo::Ida, 'orden' => 1,
        'zona_origen' => ZonaRuta::Mina, 'zona_destino' => ZonaRuta::Pisco, 'tipo_carga' => TipoCarga::Concentrado,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);
    RevisionTaller::factory()->create(['vuelta_id' => $this->vuelta->id]);

    $lineas = ReglaAbastecimiento::query()->where('nombre', 'Juliaca – Mina – Lima')->sole()->lineas->keyBy('concepto');
    $this->viaticoMina = $lineas['Viático mina'];
    $this->viaticoLima = $lineas['Viático Lima'];
});

function depositar(array $datos, string $rol = 'abastecimiento')
{
    return actingAs(actorConRol($rol))->post(route('viaticos.store'), [
        'fecha' => now()->toDateString(),
        'numero_operacion' => '000123',
        ...$datos,
    ]);
}

it('does not deposit allowances without the workshop OK', function (): void {
    $this->vuelta->revisiones()->delete();

    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 143])
        ->assertSessionHasErrors('vuelta_id');

    expect(Viatico::query()->count())->toBe(0);
});

it('deposits a board allowance on a ticket that already has fuel at several points', function (): void {
    foreach (PuntoAbastecimiento::query()->whereIn('nombre', ['Mina', 'Kio'])->get() as $punto) {
        Abastecimiento::factory()->create(['vehiculo_id' => $this->vuelta->tracto_id, 'vuelta_id' => $this->vuelta->id, 'punto_abastecimiento_id' => $punto->id]);
    }

    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 143])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');
});

it('deposits a board allowance with the amount actually deposited', function (): void {
    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 150])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');

    $viatico = Viatico::query()->sole();

    expect($viatico->concepto)->toBe('Viático mina')
        ->and((float) $viatico->monto_previsto)->toBe(143.0)
        ->and((float) $viatico->monto)->toBe(150.0)
        ->and($viatico->vehiculo_id)->toBe($this->vuelta->tracto_id)
        ->and($viatico->numero_operacion)->toBe('000123');
});

it('shows each allowance pending or deposited on the board', function (): void {
    Viatico::factory()->create([
        'vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id,
        'concepto' => 'Viático mina', 'monto_previsto' => 143, 'monto' => 143,
    ]);

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vueltas.0.viaticos.0.concepto', 'Viático mina')
            ->where('vueltas.0.viaticos.0.deposito.monto', 143)
            ->where('vueltas.0.viaticos.1.concepto', 'Viático Lima')
            ->where('vueltas.0.viaticos.1.deposito', null)
            ->where('vueltas.0.viatico_pendiente', 700)
            ->where('vueltas.0.viatico_depositado', 143)
            ->has('vueltas.0.movimientos_viaticos', 1));
});

it('does not deposit the same board allowance twice', function (): void {
    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 143]);

    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 143])
        ->assertSessionHasErrors('linea_regla_abastecimiento_id');

    expect(Viatico::query()->count())->toBe(1);
});

it('does not deposit an allowance from another card', function (): void {
    $otra = LineaReglaAbastecimiento::query()
        ->where('tipo', 'viatico')
        ->whereNotIn('id', [$this->viaticoMina->id, $this->viaticoLima->id])
        ->firstOrFail();

    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $otra->id, 'monto' => 100])
        ->assertSessionHasErrors('linea_regla_abastecimiento_id');
});

it('adds an unplanned allowance with its own concept', function (): void {
    depositar(['vuelta_id' => $this->vuelta->id, 'monto' => 80])
        ->assertSessionHasErrors('concepto');

    depositar(['vuelta_id' => $this->vuelta->id, 'concepto' => 'Alojamiento en Arequipa', 'monto' => 80])
        ->assertSessionHasNoErrors();

    $viatico = Viatico::query()->sole();
    expect($viatico->linea_regla_abastecimiento_id)->toBeNull()
        ->and($viatico->monto_previsto)->toBeNull()
        ->and($viatico->concepto)->toBe('Alojamiento en Arequipa');
});

it('rejects an amount of zero or a future date', function (): void {
    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 0])
        ->assertSessionHasErrors('monto');

    depositar([
        'vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id,
        'monto' => 143, 'fecha' => now()->addDays(5)->toDateString(),
    ])->assertSessionHasErrors('fecha');
});

it('lets billing correct a deposit and keeps the previous value in the history', function (): void {
    $viatico = Viatico::factory()->create([
        'vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoLima->id,
        'concepto' => 'Viático Lima', 'monto_previsto' => 700, 'monto' => 700,
    ]);
    $facturacion = actorConRol('contador');

    actingAs($facturacion)
        ->put(route('viaticos.update', $viatico), ['monto' => 650, 'fecha' => now()->toDateString()])
        ->assertSessionHasNoErrors();

    $movimiento = HistorialCambio::query()->where('modelo', 'Viatico')->where('accion', 'editado')->sole();

    expect((float) $viatico->fresh()->monto)->toBe(650.0)
        ->and($movimiento->user_id)->toBe($facturacion->id)
        ->and(array_map(floatval(...), $movimiento->cambios['monto']))->toBe([700.0, 650.0]);
});

it('does not change the concept of a board allowance', function (): void {
    $viatico = Viatico::factory()->create([
        'vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoLima->id,
        'concepto' => 'Viático Lima', 'monto' => 700,
    ]);

    actingAs(actorConRol('abastecimiento'))
        ->put(route('viaticos.update', $viatico), ['concepto' => 'Otro', 'monto' => 700, 'fecha' => now()->toDateString()])
        ->assertSessionHasErrors('concepto');
});

it('voids a deposit with its reason instead of deleting it, and frees the allowance', function (): void {
    $viatico = Viatico::factory()->create([
        'vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id,
        'concepto' => 'Viático mina', 'monto' => 143,
    ]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('viaticos.anular', $viatico), [])
        ->assertSessionHasErrors('motivo');

    actingAs(actorConRol('abastecimiento'))
        ->post(route('viaticos.anular', $viatico), ['motivo' => 'Depósito duplicado'])
        ->assertSessionHasNoErrors();

    expect($viatico->fresh()->estaAnulado())->toBeTrue()
        ->and($viatico->fresh()->motivo_anulacion)->toBe('Depósito duplicado')
        ->and(HistorialCambio::query()->where('modelo', 'Viatico')->where('accion', 'anulado')->exists())->toBeTrue();

    // Anulado ya no se toca, y la línea se puede volver a depositar.
    actingAs(actorConRol('abastecimiento'))
        ->put(route('viaticos.update', $viatico), ['monto' => 1, 'fecha' => now()->toDateString()])
        ->assertForbidden();

    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 143])
        ->assertSessionHasNoErrors();
});

it('keeps the allowances to abastecimiento, billing and the admin', function (): void {
    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 143], 'visor')
        ->assertForbidden();

    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoMina->id, 'monto' => 143], 'contador')
        ->assertSessionHasNoErrors();

    depositar(['vuelta_id' => $this->vuelta->id, 'linea_regla_abastecimiento_id' => $this->viaticoLima->id, 'monto' => 700], 'admin')
        ->assertSessionHasNoErrors();
});

it('does not remove a ticket that already has allowances', function (): void {
    Viatico::factory()->anulado()->create(['vuelta_id' => $this->vuelta->id]);

    actingAs(actorConRol('admin'))
        ->delete(route('vueltas.destroy', $this->vuelta))
        ->assertSessionHas('toast.type', 'error');

    expect($this->vuelta->fresh())->not->toBeNull();
});
