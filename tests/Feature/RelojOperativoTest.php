<?php

use App\Enums\TipoDocumento;
use App\Models\Factura;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * La franja en la que UTC y Lima están en días distintos.
 *
 * Las 23:00 del 10 de setiembre en Lima son las 04:00 del 11 en UTC. Todo lo
 * que se registre en ese rato —el final de la jornada, que es justo cuando se
 * cierra el día y se carga lo pendiente— tiene que quedar fechado el 10, que
 * es el día que tiene delante quien lo está cargando.
 *
 * El resto de la suite corre congelada a mediodía justamente para no depender
 * de esto (ver `tests/Pest.php`); acá se entra a propósito.
 */
const MEDIANOCHE_CASI_EN_LIMA = '2026-09-11 04:00:00';
const DIA_EN_LIMA = '2026-09-10';

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->travelTo(MEDIANOCHE_CASI_EN_LIMA);
});

it('lee el día de calendario en hora de Lima y no la del servidor', function (): void {
    expect(RelojOperativo::hoy())->toBe(DIA_EN_LIMA)
        // El contraste que da sentido a la clase: en UTC ya es el día siguiente.
        ->and(now()->toDateString())->toBe('2026-09-11');
});

it('fecha la factura sin fecha explícita en el día que ve el contador', function (): void {
    $viaje = Viaje::factory()->create();

    actingAs(actorConRol('contador'))
        ->post(route('facturas.store'), ['numero' => 'F001-00123', 'viaje_ids' => [$viaje->id]])
        ->assertSessionHasNoErrors();

    expect(Factura::query()->sole()->fecha_emision->toDateString())->toBe(DIA_EN_LIMA);
});

/**
 * Una factura emitida hoy lleva cero días sin cobrarse. Leída contra el reloj
 * del servidor aparecería con uno, y la cobranza mostraría vencida una factura
 * recién cargada.
 */
it('no le cuenta un día de más a la factura emitida hoy', function (): void {
    $factura = Factura::factory()->create([
        'fecha_emision' => DIA_EN_LIMA,
        'fecha_pago' => null,
    ]);

    expect($factura->diasVencida())->toBe(0);
});

/**
 * El criterio de la ficha es que un documento sigue vigente el día que vence,
 * hasta la medianoche. La medianoche que importa es la de Lima.
 */
it('no da por vencido el documento que vence hoy en Lima', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    $documento = VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => DIA_EN_LIMA,
    ]);

    expect($documento->estado()->value)->toBe('por_vencer');
});

it('da por vencido el documento que venció ayer en Lima', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    $documento = VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => '2026-09-09',
    ]);

    expect($documento->estado()->value)->toBe('vencido');
});

/**
 * El indicador del área se mide contra el mes de Lima. A las 23:00 del 10 el
 * mes lleva diez días corridos, no once.
 */
it('cuenta los días del mes contra el calendario de Lima', function (): void {
    expect(RelojOperativo::ahora()->day)->toBe(10)
        ->and(RelojOperativo::inicioDelMes()->toDateString())->toBe('2026-09-01')
        ->and(RelojOperativo::finDelMes()->toDateString())->toBe('2026-09-30');
});
