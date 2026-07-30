<?php

use App\Enums\TipoCarga;
use App\Enums\TipoNovedad;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Novedad;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use App\Services\FilaProgramacion;
use App\Services\MotorProgramacion;
use Database\Seeders\UbicacionSeeder;

beforeEach(function (): void {
    $this->seed(UbicacionSeeder::class);
    $this->motor = new MotorProgramacion;
});

/**
 * Una unidad lista para subir: descargada, en Juliaca y con conductor.
 */
function unidadEnBase(string $placa, string $fecha, ?Conductor $conductor = null): EstadoUnidad
{
    return EstadoUnidad::factory()
        ->en('juliaca')
        ->create([
            'tracto_id' => Vehiculo::factory()->create(['placa' => $placa])->id,
            'conductor_id' => ($conductor ?? Conductor::factory()->create())->id,
            'tipo_carga' => TipoCarga::Vacio,
            'fecha' => $fecha,
        ]);
}

/**
 * Placas de los tractos, descartando la carreta que engancha el factory.
 *
 * @param  list<FilaProgramacion>  $filas
 * @return list<string>
 */
function placasDe(array $filas): array
{
    return array_map(
        fn (FilaProgramacion $fila): string => explode('/', $fila->vehiculo)[0],
        $filas,
    );
}

it('programa las unidades descargadas que están en base con conductor', function (): void {
    unidadEnBase('AAA-111', '2026-07-21');
    unidadEnBase('BBB-222', '2026-07-21');

    $resultado = $this->motor->proponer('2026-07-21', 5);

    expect($resultado->titulares)->toHaveCount(2)
        ->and($resultado->noProgramables)->toHaveCount(0)
        ->and($resultado->cuposLibres())->toBe(3);
});

it('numera las vacías desde uno y les reparte las horas de salida', function (): void {
    foreach (['AAA-111', 'BBB-222', 'CCC-333'] as $placa) {
        unidadEnBase($placa, '2026-07-21');
    }

    $resultado = $this->motor->proponer('2026-07-21', 3);

    expect(array_map(fn (FilaProgramacion $f): ?int => $f->numero, $resultado->titulares))
        ->toBe([1, 2, 3])
        ->and(array_map(fn (FilaProgramacion $f): ?string => $f->hora, $resultado->titulares))
        ->toBe(MotorProgramacion::HORAS);
});

it('da prioridad a la unidad que lleva más tiempo esperando en base', function (): void {
    $vieja = Vehiculo::factory()->create(['placa' => 'VIE-111']);
    $nueva = Vehiculo::factory()->create(['placa' => 'NUE-222']);

    // La vieja llegó el 18 y sigue ahí; la nueva llegó recién el 21.
    foreach (['2026-07-18', '2026-07-19', '2026-07-20', '2026-07-21'] as $dia) {
        EstadoUnidad::factory()->en('juliaca')->create([
            'tracto_id' => $vieja->id,
            'conductor_id' => Conductor::factory()->create()->id,
            'tipo_carga' => TipoCarga::Vacio,
            'fecha' => $dia,
        ]);
    }

    EstadoUnidad::factory()->en('nazca')->create([
        'tracto_id' => $nueva->id,
        'tipo_carga' => TipoCarga::Concentrado,
        'fecha' => '2026-07-20',
    ]);
    EstadoUnidad::factory()->en('juliaca')->create([
        'tracto_id' => $nueva->id,
        'conductor_id' => Conductor::factory()->create()->id,
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => '2026-07-21',
    ]);

    $resultado = $this->motor->proponer('2026-07-21', 1);

    expect(placasDe($resultado->titulares))->toBe(['VIE-111'])
        ->and(placasDe($resultado->reservas))->toBe(['NUE-222']);
});

it('manda a reserva lo que no entra en los cupos', function (): void {
    foreach (['AAA-111', 'BBB-222', 'CCC-333'] as $placa) {
        unidadEnBase($placa, '2026-07-21');
    }

    $resultado = $this->motor->proponer('2026-07-21', 2);

    expect($resultado->titulares)->toHaveCount(2)
        ->and($resultado->reservas)->toHaveCount(1)
        ->and($resultado->cuposLibres())->toBe(0);
});

it('agrega sin número las cargadas que ya van subiendo a mina', function (): void {
    $estado = EstadoUnidad::factory()
        ->conCarga(TipoCarga::Escoria)
        ->en('azangaro')
        ->create(['fecha' => '2026-07-21']);

    $resultado = $this->motor->proponer('2026-07-21', 5);

    expect($resultado->enTransito)->toHaveCount(1)
        ->and($resultado->enTransito[0]->numero)->toBeNull()
        ->and($resultado->enTransito[0]->hora)->toBeNull()
        ->and($resultado->enTransito[0]->observaciones)->toBe('Inicio de tránsito desde Azángaro')
        // No consumen cupo: los cinco siguen libres.
        ->and($resultado->cuposLibres())->toBe(5)
        ->and($estado->tipo_carga)->toBe(TipoCarga::Escoria);
});

it('pone las cargadas primero en la tabla que se envía', function (): void {
    unidadEnBase('AAA-111', '2026-07-21');
    EstadoUnidad::factory()->conCarga(TipoCarga::Escoria)->en('azangaro')
        ->create(['fecha' => '2026-07-21']);

    $tabla = $this->motor->proponer('2026-07-21', 5)->filasDeLaTabla();

    expect($tabla[0]->numero)->toBeNull()
        ->and($tabla[1]->numero)->toBe(1);
});

it('deja fuera a la unidad que sigue en ruta con carga', function (): void {
    EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('nazca')
        ->create(['fecha' => '2026-07-21']);

    $resultado = $this->motor->proponer('2026-07-21', 5);

    expect($resultado->titulares)->toHaveCount(0)
        ->and($resultado->noProgramables[0]->motivo)->toBe('En ruta con Concentrado');
});

it('deja fuera a la unidad descargada que no está en zona base', function (): void {
    EstadoUnidad::factory()->en('nazca')->create([
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => '2026-07-21',
    ]);

    $resultado = $this->motor->proponer('2026-07-21', 5);

    expect($resultado->noProgramables[0]->motivo)
        ->toBe('Está en Nazca, fuera de zona base');
});

it('deja fuera a la unidad sin conductor', function (): void {
    EstadoUnidad::factory()->en('juliaca')->sinConductor()->create([
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => '2026-07-21',
    ]);

    $resultado = $this->motor->proponer('2026-07-21', 5);

    expect($resultado->noProgramables[0]->motivo)->toBe('Sin conductor asignado');
});

it('deja fuera a la unidad con una novedad vigente, con su motivo', function (TipoNovedad $tipo, string $motivo): void {
    $estado = unidadEnBase('AAA-111', '2026-07-21');

    Novedad::factory()->de($tipo)->create([
        'tracto_id' => $estado->tracto_id,
        'desde' => '2026-07-20',
    ]);

    $resultado = $this->motor->proponer('2026-07-21', 5);

    expect($resultado->titulares)->toHaveCount(0)
        ->and($resultado->noProgramables[0]->motivo)->toBe($motivo);
})->with([
    [TipoNovedad::NoHabido, 'No habido'],
    [TipoNovedad::InfraccionMina, 'Infracción vigente en mina'],
    [TipoNovedad::EnMina, 'Ya está en mina; la cargan allá'],
    [TipoNovedad::AdicionalFueraPrograma, 'Subió como adicional; ya está en ciclo'],
    [TipoNovedad::Taller, 'En taller'],
]);

it('vuelve a programar la unidad cuando la novedad ya se levantó', function (): void {
    $estado = unidadEnBase('AAA-111', '2026-07-21');

    Novedad::factory()->levantada('2026-07-19')->create([
        'tracto_id' => $estado->tracto_id,
        'desde' => '2026-07-15',
    ]);

    expect($this->motor->proponer('2026-07-21', 5)->titulares)->toHaveCount(1);
});

it('libera la unidad el mismo día en que se levanta la novedad', function (): void {
    // Si apareció esta mañana tiene que poder programarse hoy, no mañana.
    $estado = unidadEnBase('AAA-111', '2026-07-21');

    Novedad::factory()->levantada('2026-07-21')->create([
        'tracto_id' => $estado->tracto_id,
        'desde' => '2026-07-18',
    ]);

    expect($this->motor->proponer('2026-07-21', 5)->titulares)->toHaveCount(1);
});

it('respeta la novedad tal como pesaba el día que se programa', function (): void {
    // Rehacer la programación del 21 tiene que dar lo mismo que dio ese día,
    // aunque la novedad se haya levantado después.
    $estado = unidadEnBase('AAA-111', '2026-07-21');

    Novedad::factory()->levantada('2026-07-25')->create([
        'tracto_id' => $estado->tracto_id,
        'desde' => '2026-07-20',
    ]);

    expect($this->motor->proponer('2026-07-21', 5)->noProgramables)->toHaveCount(1);
});

it('escribe la unidad como tracto y carreta, y el conductor con su nombre', function (): void {
    $conductor = Conductor::factory()->create([
        'nombres' => 'Juan Carlos',
        'apellidos' => 'Quispe Mamani',
    ]);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'BWC-987']);

    EstadoUnidad::factory()->en('juliaca')->create([
        'tracto_id' => Vehiculo::factory()->create(['placa' => 'VEP-856'])->id,
        'carreta_id' => $carreta->id,
        'conductor_id' => $conductor->id,
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => '2026-07-21',
    ]);

    $fila = $this->motor->proponer('2026-07-21', 5)->titulares[0];

    expect($fila->vehiculo)->toBe('VEP-856/BWC-987')
        ->and($fila->conductor)->toBe($conductor->nombre_completo)
        ->and($fila->empresa)->toBe(MotorProgramacion::EMPRESA)
        ->and($fila->estadoUnidad)->toBe('Vacío');
});

it('usa el último estado conocido cuando el día todavía no se cargó', function (): void {
    unidadEnBase('AAA-111', '2026-07-19');

    // Se programa el 21 sin que exista reporte de ese día ni del 20.
    expect($this->motor->proponer('2026-07-21', 5)->titulares)->toHaveCount(1);
});

it('no mira estados posteriores al día que se programa', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'AAA-111']);

    EstadoUnidad::factory()->conCarga(TipoCarga::Concentrado)->en('nazca')->create([
        'tracto_id' => $tracto->id,
        'fecha' => '2026-07-21',
    ]);
    EstadoUnidad::factory()->en('juliaca')->create([
        'tracto_id' => $tracto->id,
        'conductor_id' => Conductor::factory()->create()->id,
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => '2026-07-25',
    ]);

    expect($this->motor->proponer('2026-07-21', 5)->titulares)->toHaveCount(0);
});

it('no rompe cuando no hay cupos ni unidades', function (): void {
    $resultado = $this->motor->proponer('2026-07-21', 0);

    expect($resultado->titulares)->toBe([])
        ->and($resultado->filasDeLaTabla())->toBe([])
        ->and($resultado->cuposLibres())->toBe(0);
});

it('manda todo a reserva cuando no hay cupos', function (): void {
    unidadEnBase('AAA-111', '2026-07-21');

    $resultado = $this->motor->proponer('2026-07-21', 0);

    expect($resultado->titulares)->toHaveCount(0)
        ->and($resultado->reservas)->toHaveCount(1);
});

it('no confunde una zona que no es base con Juliaca', function (): void {
    // Solo Juliaca despacha; Azángaro es punto de paso del corredor.
    Ubicacion::query()->where('codigo', 'azangaro')->update(['es_zona_base' => false]);

    EstadoUnidad::factory()->en('azangaro')->create([
        'conductor_id' => Conductor::factory()->create()->id,
        'tipo_carga' => TipoCarga::Vacio,
        'fecha' => '2026-07-21',
    ]);

    expect($this->motor->proponer('2026-07-21', 5)->titulares)->toHaveCount(0);
});
