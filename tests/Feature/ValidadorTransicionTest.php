<?php

use App\Enums\NivelAlerta;
use App\Enums\TipoAlerta;
use App\Enums\TipoCarga;
use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use App\Services\ValidadorTransicion;
use Database\Seeders\UbicacionSeeder;

beforeEach(function (): void {
    $this->seed(UbicacionSeeder::class);
    $this->validador = new ValidadorTransicion;
    $this->tracto = Vehiculo::factory()->create();
});

/**
 * Crea un estado de la unidad bajo prueba en la fecha indicada.
 */
function estadoEn(string $fecha, TipoCarga $carga, string $ubicacion): EstadoUnidad
{
    return EstadoUnidad::factory()
        ->conCarga($carga)
        ->en($ubicacion)
        ->create(['tracto_id' => test()->tracto->id, 'fecha' => $fecha]);
}

it('no dice nada de la primera aparición de una unidad', function (): void {
    $estado = estadoEn('2026-07-20', TipoCarga::Concentrado, 'nazca');

    expect($this->validador->validar($estado, null))->toBe([]);
});

it('deja pasar a la unidad que sigue en el mismo tramo al día siguiente', function (): void {
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado, 'camana');
    $hoy = estadoEn('2026-07-21', TipoCarga::Concentrado, 'chala');

    expect($this->validador->validar($hoy, $ayer))->toBe([]);
});

it('detecta el concentrado que amanece con carga particular', function (): void {
    // El salto que motivó esta capa: cada fila por separado se ve bien, pero
    // juntas describen una unidad que se saltó el retorno de Pisco entero.
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado, 'nazca');
    $hoy = estadoEn('2026-07-21', TipoCarga::Particular, 'lima');

    $alerta = alertaDe($this->validador->validar($hoy, $ayer), TipoAlerta::SaltoDeFase);

    expect($alerta)->not->toBeNull()
        ->and($alerta->nivel())->toBe(NivelAlerta::Imposible)
        ->and($alerta->detalle)->toContain('San Rafael → Pisco');
});

it('acepta las transiciones que el circuito sí permite', function (): void {
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado, 'pisco');
    $hoy = estadoEn('2026-07-21', TipoCarga::Escoria, 'pisco');

    expect(tiposDeAlerta($this->validador->validar($hoy, $ayer)))
        ->not->toContain(TipoAlerta::SaltoDeFase->value);
});

it('detecta la carga que cambió en mitad del corredor', function (): void {
    // En Nazca no hay nada que cargar ni dónde descargar: si la carga cambió
    // ahí, uno de los dos reportes está mal.
    $ayer = estadoEn('2026-07-20', TipoCarga::Metalico, 'nazca');
    $hoy = estadoEn('2026-07-21', TipoCarga::Materiales, 'nazca');

    $alerta = alertaDe($this->validador->validar($hoy, $ayer), TipoAlerta::CargaCambioFueraDePunto);

    expect($alerta)->not->toBeNull()
        ->and($alerta->detalle)->toContain('Nazca');
});

it('acepta el cambio de carga en un punto de carga o descarga', function (): void {
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado, 'pisco');
    $hoy = estadoEn('2026-07-21', TipoCarga::Metalico, 'pisco');

    expect(tiposDeAlerta($this->validador->validar($hoy, $ayer)))
        ->not->toContain(TipoAlerta::CargaCambioFueraDePunto->value);
});

it('detecta el avance que no se puede hacer en el tiempo transcurrido', function (): void {
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado, 'san_rafael');
    $hoy = estadoEn('2026-07-21', TipoCarga::Concentrado, 'pisco');

    $alerta = alertaDe($this->validador->validar($hoy, $ayer), TipoAlerta::AvanceImposible);

    expect($alerta)->not->toBeNull()
        ->and($alerta->nivel())->toBe(NivelAlerta::Imposible)
        ->and($alerta->detalle)->toContain('un día');
});

it('acepta el mismo trayecto cuando pasaron los días que de verdad toma', function (): void {
    // Mina a Pisco son dos o tres días: con tres, el mismo salto es normal.
    $antes = estadoEn('2026-07-18', TipoCarga::Concentrado, 'san_rafael');
    $hoy = estadoEn('2026-07-21', TipoCarga::Concentrado, 'pisco');

    expect(tiposDeAlerta($this->validador->validar($hoy, $antes)))
        ->not->toContain(TipoAlerta::AvanceImposible->value);
});

it('acepta que la unidad se quede en la base los días de permanencia', function (): void {
    $antes = estadoEn('2026-07-19', TipoCarga::Vacio, 'juliaca');
    $hoy = estadoEn('2026-07-21', TipoCarga::Vacio, 'juliaca');

    expect(tiposDeAlerta($this->validador->validar($hoy, $antes)))
        ->not->toContain(TipoAlerta::UnidadDetenida->value);
});

it('avisa de la unidad parada en un punto de paso', function (): void {
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado, 'chala');
    $hoy = estadoEn('2026-07-21', TipoCarga::Concentrado, 'chala');

    $alerta = alertaDe($this->validador->validar($hoy, $ayer), TipoAlerta::UnidadDetenida);

    expect($alerta)->not->toBeNull()
        ->and($alerta->nivel())->toBe(NivelAlerta::Improbable)
        ->and($alerta->detalle)->toContain('Chala');
});

it('avisa de la unidad que se pasa de los días de permanencia de la base', function (): void {
    $antes = estadoEn('2026-07-15', TipoCarga::Vacio, 'juliaca');
    $hoy = estadoEn('2026-07-21', TipoCarga::Vacio, 'juliaca');

    expect(tiposDeAlerta($this->validador->validar($hoy, $antes)))
        ->toContain(TipoAlerta::UnidadDetenida->value);
});

it('no juzga transiciones cuando falta la ubicación de alguno de los dos', function (): void {
    $ayer = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->create(['tracto_id' => $this->tracto->id, 'fecha' => '2026-07-20']);

    $hoy = estadoEn('2026-07-21', TipoCarga::Concentrado, 'pisco');

    expect(tiposDeAlerta($this->validador->validar($hoy, $ayer)))->not->toContain(
        TipoAlerta::AvanceImposible->value,
        TipoAlerta::UnidadDetenida->value,
        TipoAlerta::CargaCambioFueraDePunto->value,
    );
});
