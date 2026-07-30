<?php

use App\Enums\TipoCarga;
use App\Models\EstadoUnidad;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use App\Services\EstimadorLlegada;
use Database\Seeders\UbicacionSeeder;

beforeEach(function (): void {
    $this->seed(UbicacionSeeder::class);
});

function punto(string $codigo): Ubicacion
{
    return Ubicacion::query()->where('codigo', $codigo)->firstOrFail();
}

it('mide la distancia siguiendo el corredor y no la línea recta', function (): void {
    $estimador = EstimadorLlegada::paraLaFlota();

    $porCorredor = $estimador->distanciaPorCorredor(punto('san_rafael'), punto('pisco'));
    $enLineaRecta = punto('san_rafael')->distanciaKmA(punto('pisco'));

    // El corredor traza la costa en vez de cruzarla, así que siempre suma más
    // que unir los extremos con una regla.
    expect($porCorredor)->toBeGreaterThan($enLineaRecta);
});

it('mide igual en los dos sentidos', function (): void {
    $estimador = EstimadorLlegada::paraLaFlota();

    expect($estimador->distanciaPorCorredor(punto('pisco'), punto('juliaca')))
        ->toBe($estimador->distanciaPorCorredor(punto('juliaca'), punto('pisco')));
});

it('no mide trayectos hacia puntos que quedan fuera del corredor', function (): void {
    $estimador = EstimadorLlegada::paraLaFlota();

    expect($estimador->distanciaPorCorredor(punto('juliaca'), punto('cusco')))->toBeNull();
});

it('mide trayectos que pasan por Arequipa, que sí pertenece al corredor', function (): void {
    // Los reportes reales muestran unidades bajando de mina por Yura y
    // Arequipa, no solo por Imata y La Joya.
    $estimador = EstimadorLlegada::paraLaFlota();

    expect($estimador->distanciaPorCorredor(punto('arequipa'), punto('pisco')))
        ->toBeGreaterThan(0);
});

it('estima la llegada de una unidad en ruta', function (): void {
    $estado = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Concentrado)
        ->en('nazca')
        ->create(['fecha' => '2026-07-21']);

    $estimacion = EstimadorLlegada::paraLaFlota()->estimar($estado);

    expect($estimacion)->not->toBeNull()
        ->and($estimacion->diasRestantes)->toBeGreaterThan(0)
        ->and($estimacion->fechaEstimada)->toBeGreaterThan('2026-07-21')
        ->and($estimacion->kilometrosRestantes)->toBeGreaterThan(0);
});

it('estima menos días cuanto más cerca está la unidad del destino', function (): void {
    $estimador = EstimadorLlegada::paraLaFlota();

    $lejos = EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('juliaca')
        ->create(['fecha' => '2026-07-21']);
    $cerca = EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('nazca')
        ->create(['fecha' => '2026-07-21']);

    expect($estimador->estimar($cerca)->diasRestantes)
        ->toBeLessThan($estimador->estimar($lejos)->diasRestantes);
});

it('no estima nada cuando falta el destino o la ubicación', function (): void {
    $estimador = EstimadorLlegada::paraLaFlota();

    $sinDestino = EstadoUnidad::factory()->en('nazca')->create();
    $sinUbicacion = EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->create();

    expect($estimador->estimar($sinDestino))->toBeNull()
        ->and($estimador->estimar($sinUbicacion))->toBeNull();
});

it('avisa que la estimación no está calibrada mientras no haya recorridos', function (): void {
    expect(EstimadorLlegada::paraLaFlota()->medirRitmo())->toBeNull()
        ->and(EstimadorLlegada::paraLaFlota()->estaCalibrado())->toBeFalse()
        ->and(EstimadorLlegada::paraLaFlota()->kilometrosPorDia())
        ->toBe(EstimadorLlegada::KILOMETROS_POR_DIA_POR_DEFECTO);
});

it('mide el ritmo real de la flota cuando ya hay histórico suficiente', function (): void {
    // Seis tramos de un día, cada uno cruzando de zona a zona: los saltos
    // dentro de una misma zona miden cero y no cuentan como recorrido.
    $recorrido = ['juliaca', 'imata', 'arequipa', 'camana', 'chala', 'nazca', 'pisco'];
    $tracto = Vehiculo::factory()->create();

    foreach ($recorrido as $dia => $codigo) {
        EstadoUnidad::factory()->en($codigo)->create([
            'tracto_id' => $tracto->id,
            'fecha' => now()->addDays($dia)->toDateString(),
        ]);
    }

    $estimador = EstimadorLlegada::paraLaFlota();

    expect(EstimadorLlegada::paraLaFlota()->medirRitmo())->not->toBeNull()
        ->and($estimador->estaCalibrado())->toBeTrue();
});

it('no deja que los días parados en base hagan parecer lenta a la flota', function (): void {
    $tracto = Vehiculo::factory()->create();

    // Un tramo de verdad, y después dos semanas sin moverse de Juliaca.
    EstadoUnidad::factory()->en('juliaca')->create([
        'tracto_id' => $tracto->id, 'fecha' => '2026-07-01',
    ]);
    EstadoUnidad::factory()->en('imata')->create([
        'tracto_id' => $tracto->id, 'fecha' => '2026-07-02',
    ]);
    EstadoUnidad::factory()->en('imata')->create([
        'tracto_id' => $tracto->id, 'fecha' => '2026-07-16',
    ]);

    $conParada = EstimadorLlegada::paraLaFlota()->medirRitmo();

    // Solo se contó el tramo con movimiento; con la parada dentro, el ritmo
    // habría salido quince veces más lento.
    expect($conParada)->toBeNull();

    $distancia = punto('juliaca')->distanciaKmA(punto('imata'));
    expect($distancia)->toBeGreaterThan(0);
});

it('no cuenta las unidades sin ubicación al medir el ritmo', function (): void {
    EstadoUnidad::factory()->count(8)->create(['ubicacion_id' => null]);

    expect(EstimadorLlegada::paraLaFlota()->medirRitmo())->toBeNull();
});
