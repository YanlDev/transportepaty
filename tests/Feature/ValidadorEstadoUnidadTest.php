<?php

use App\Enums\TipoAlerta;
use App\Enums\TipoCarga;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use App\Services\ValidadorEstadoUnidad;

beforeEach(function (): void {
    $this->validador = new ValidadorEstadoUnidad;
});

it('avisa cuando falta un extremo de la ruta', function (): void {
    $estado = EstadoUnidad::factory()->create([
        'tipo_carga' => TipoCarga::Particular,
        'origen' => 'Lima',
        'destino' => null,
    ]);

    expect(tiposDeAlerta($this->validador->validar($estado)))
        ->toContain(TipoAlerta::RutaIncompleta->value);
});

it('no exige ruta cuando todavía no hay carga registrada', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => null]);

    expect(tiposDeAlerta($this->validador->validar($estado)))
        ->not->toContain(TipoAlerta::RutaIncompleta->value);
});

it('no avisa cuando la carga declara origen y destino', function (): void {
    $estado = EstadoUnidad::factory()->create([
        'tipo_carga' => TipoCarga::Concentrado,
        'origen' => 'San Rafael',
        'destino' => 'Pisco',
    ]);

    expect(tiposDeAlerta($this->validador->validar($estado)))
        ->not->toContain(TipoAlerta::RutaIncompleta->value);
});

it('avisa de la unidad sin conductor, que no puede entrar a la programación', function (): void {
    $estado = EstadoUnidad::factory()->sinConductor()->create();

    expect(tiposDeAlerta($this->validador->validar($estado)))
        ->toContain(TipoAlerta::SinConductor->value);
});

it('avisa cuando el conductor no es el de la asignación vigente', function (): void {
    $asignacion = Asignacion::factory()->create();
    $otro = Conductor::factory()->create(['nombres' => 'Otro', 'apellidos' => 'Chofer']);

    $estado = EstadoUnidad::factory()->create([
        'tracto_id' => $asignacion->tracto_id,
        'carreta_id' => $asignacion->carreta_id,
        'conductor_id' => $otro->id,
    ]);

    $alerta = alertaDe($this->validador->validar($estado), TipoAlerta::ConductorDistintoAlAsignado);

    expect($alerta)->not->toBeNull()
        ->and($alerta->detalle)->toContain($asignacion->conductor->nombres);
});

it('calla cuando el conductor y la carreta son los asignados', function (): void {
    $asignacion = Asignacion::factory()->create();

    $estado = EstadoUnidad::factory()->create([
        'tracto_id' => $asignacion->tracto_id,
        'carreta_id' => $asignacion->carreta_id,
        'conductor_id' => $asignacion->conductor_id,
    ]);

    expect(tiposDeAlerta($this->validador->validar($estado)))->not->toContain(
        TipoAlerta::ConductorDistintoAlAsignado->value,
        TipoAlerta::CarretaDistintaALaAsignada->value,
        TipoAlerta::SinConductor->value,
    );
});

it('avisa cuando la carreta no es la de la asignación vigente', function (): void {
    $asignacion = Asignacion::factory()->create();
    $otra = Vehiculo::factory()->carreta()->create(['placa' => 'ZZZ-999']);

    $estado = EstadoUnidad::factory()->create([
        'tracto_id' => $asignacion->tracto_id,
        'carreta_id' => $otra->id,
        'conductor_id' => $asignacion->conductor_id,
    ]);

    $alerta = alertaDe($this->validador->validar($estado), TipoAlerta::CarretaDistintaALaAsignada);

    expect($alerta)->not->toBeNull()
        ->and($alerta->detalle)->toContain($asignacion->carreta->placa);
});

it('no compara contra asignaciones cuando el tracto no tiene ninguna vigente', function (): void {
    $estado = EstadoUnidad::factory()->create();

    expect(tiposDeAlerta($this->validador->validar($estado)))->not->toContain(
        TipoAlerta::ConductorDistintoAlAsignado->value,
        TipoAlerta::CarretaDistintaALaAsignada->value,
    );
});
