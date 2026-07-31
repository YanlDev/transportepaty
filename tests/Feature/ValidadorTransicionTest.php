<?php

use App\Enums\NivelAlerta;
use App\Enums\TipoAlerta;
use App\Enums\TipoCarga;
use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use App\Services\ValidadorTransicion;

beforeEach(function (): void {
    $this->validador = new ValidadorTransicion;
    $this->tracto = Vehiculo::factory()->create();
});

/**
 * Crea un estado de la unidad bajo prueba en la fecha indicada.
 */
function estadoEn(string $fecha, TipoCarga $carga): EstadoUnidad
{
    return EstadoUnidad::factory()
        ->conCarga($carga)
        ->create(['tracto_id' => test()->tracto->id, 'fecha' => $fecha]);
}

it('no dice nada de la primera aparición de una unidad', function (): void {
    $estado = estadoEn('2026-07-20', TipoCarga::Concentrado);

    expect($this->validador->validar($estado, null))->toBe([]);
});

it('detecta el concentrado que amanece con carga particular', function (): void {
    // El salto que motivó esta capa: cada fila por separado se ve bien, pero
    // juntas describen una unidad que se saltó el retorno de Pisco entero.
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado);
    $hoy = estadoEn('2026-07-21', TipoCarga::Particular);

    $alerta = alertaDe($this->validador->validar($hoy, $ayer), TipoAlerta::SaltoDeFase);

    expect($alerta)->not->toBeNull()
        ->and($alerta->nivel())->toBe(NivelAlerta::Imposible)
        ->and($alerta->detalle)->toContain('San Rafael → Pisco');
});

it('acepta las transiciones que el circuito sí permite', function (): void {
    $ayer = estadoEn('2026-07-20', TipoCarga::Concentrado);
    $hoy = estadoEn('2026-07-21', TipoCarga::Escoria);

    expect(tiposDeAlerta($this->validador->validar($hoy, $ayer)))
        ->not->toContain(TipoAlerta::SaltoDeFase->value);
});
