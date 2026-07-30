<?php

use App\Enums\NivelAlerta;
use App\Enums\TipoAlerta;
use App\Enums\TipoCarga;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use App\Services\ValidadorEstadoUnidad;
use Database\Seeders\UbicacionSeeder;

beforeEach(function (): void {
    $this->seed(UbicacionSeeder::class);
    $this->validador = new ValidadorEstadoUnidad;
});

it('no levanta alertas de ruta cuando la carga y el tramo se corresponden', function (): void {
    $estado = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->en('nazca')
        ->create();

    expect(tiposDeAlerta($this->validador->validar($estado)))->not->toContain(
        TipoAlerta::RutaIncompatibleConCarga->value,
        TipoAlerta::UbicacionFueraDeRuta->value,
    );
});

it('detecta la carga futura anotada en vez de vacío al subir a mina', function (): void {
    $estado = EstadoUnidad::factory()->create([
        'tipo_carga' => TipoCarga::Concentrado,
        'origen_id' => Ubicacion::query()->where('codigo', 'juliaca')->value('id'),
        'destino_id' => Ubicacion::query()->where('codigo', 'san_rafael')->value('id'),
    ]);

    expect(tiposDeAlerta($this->validador->validar($estado)))
        ->toContain(TipoAlerta::RutaIncompatibleConCarga->value);
});

it('detecta la unidad que declara una ruta por la que no está pasando', function (): void {
    // Dice llevar concentrado hacia Pisco pero está en Cusco, que es destino
    // válido de carga particular y queda fuera del corredor.
    $estado = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->en('cusco')
        ->create();

    $alerta = alertaDe($this->validador->validar($estado), TipoAlerta::UbicacionFueraDeRuta);

    expect($alerta)->not->toBeNull()
        ->and($alerta->nivel())->toBe(NivelAlerta::Imposible)
        ->and($alerta->detalle)->toContain('Cusco');
});

it('no molesta a la unidad que baja de mina por Arequipa', function (): void {
    // Los reportes reales muestran este trayecto: Azángaro, Yura, Arequipa,
    // Camaná, Pisco. Antes se marcaba como imposible porque el corredor se
    // modelaba como una fila recta que pasaba solo por Imata y La Joya.
    $estado = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->en('arequipa')
        ->create();

    expect(alertaDe($this->validador->validar($estado), TipoAlerta::UbicacionFueraDeRuta))
        ->toBeNull();
});

it('acepta las rutas alternativas de una misma zona', function (string $codigo): void {
    // La Joya, Majes y Yura son alternativas del mismo tramo; las tres salen en
    // los reportes y ninguna puede leerse como error.
    $estado = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->en($codigo)
        ->create();

    expect(alertaDe($this->validador->validar($estado), TipoAlerta::UbicacionFueraDeRuta))
        ->toBeNull();
})->with(['la_joya', 'majes', 'yura', 'la_reparticion']);

it('avisa cuando falta un extremo de la ruta', function (): void {
    $estado = EstadoUnidad::factory()->create([
        'tipo_carga' => TipoCarga::Particular,
        'origen_id' => Ubicacion::query()->where('codigo', 'lima')->value('id'),
        'destino_id' => null,
    ]);

    expect(tiposDeAlerta($this->validador->validar($estado)))
        ->toContain(TipoAlerta::RutaIncompleta->value);
});

it('no exige ruta cuando todavía no hay carga registrada', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => null]);

    expect(tiposDeAlerta($this->validador->validar($estado)))
        ->not->toContain(TipoAlerta::RutaIncompleta->value);
});

it('avisa de la ubicación que no reconoció, sin descartar la fila', function (): void {
    $estado = EstadoUnidad::factory()->conUbicacionSinResolver('Grifo km 48')->create();

    $alerta = alertaDe($this->validador->validar($estado), TipoAlerta::UbicacionSinResolver);

    expect($alerta)->not->toBeNull()
        ->and($alerta->nivel())->toBe(NivelAlerta::Improbable)
        ->and($alerta->detalle)->toContain('Grifo km 48')
        // La fila se guardó igual: la alerta señala, no descarta.
        ->and($estado->exists)->toBeTrue();
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
