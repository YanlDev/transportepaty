<?php

use App\Enums\Cliente;
use App\Enums\EstadoCarga;
use App\Enums\FaseCiclo;
use App\Enums\OrigenDato;
use App\Enums\TipoCarga;
use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use Illuminate\Database\UniqueConstraintViolationException;

it('deduce estado de carga, cliente y fase a partir del tipo de carga', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => TipoCarga::Concentrado]);

    expect($estado->estado_carga)->toBe(EstadoCarga::Cargado)
        ->and($estado->cliente)->toBe(Cliente::Minsur)
        ->and($estado->fase)->toBe(FaseCiclo::MinaPisco);
});

it('anota como deducido lo que calculó solo', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => TipoCarga::Particular]);

    expect($estado->origenDe('estado_carga'))->toBe(OrigenDato::Deducido)
        ->and($estado->origenDe('cliente'))->toBe(OrigenDato::Deducido)
        ->and($estado->origenDe('fase'))->toBe(OrigenDato::Deducido);
});

it('rehace la deducción cuando cambia el tipo de carga', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => TipoCarga::Vacio]);

    expect($estado->estado_carga)->toBe(EstadoCarga::Vacio);

    $estado->update(['tipo_carga' => TipoCarga::Escoria]);

    expect($estado->fresh()->estado_carga)->toBe(EstadoCarga::Cargado)
        ->and($estado->fresh()->fase)->toBe(FaseCiclo::RetornoPisco);
});

it('no pisa lo que se confirmó a mano', function (): void {
    // El cliente lo corrigió una persona; cambiar la carga después no debe
    // devolverlo a lo que el sistema supondría.
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => TipoCarga::Concentrado]);

    $estado->cliente = Cliente::Particular;
    $estado->confirmar(['cliente'])->save();

    $estado->update(['tipo_carga' => TipoCarga::Metalico]);

    expect($estado->fresh()->cliente)->toBe(Cliente::Particular)
        ->and($estado->fresh()->origenDe('cliente'))->toBe(OrigenDato::Manual)
        // Lo que no se confirmó sí se recalcula.
        ->and($estado->fresh()->fase)->toBe(FaseCiclo::RetornoPisco);
});

it('no deja que una unidad figure vacía y cargada a la vez', function (): void {
    // Vacía y descargada son la misma cosa dicha de dos maneras. Lo normal es
    // que la unidad suba así de Juliaca a mina, y ninguna corrección a mano
    // puede hacer que ese mismo registro diga que va cargada.
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => TipoCarga::Vacio]);

    $estado->estado_carga = EstadoCarga::Cargado;
    $estado->confirmar(['estado_carga'])->save();

    expect($estado->fresh()->estado_carga)->toBe(EstadoCarga::Vacio)
        ->and($estado->fresh()->estado_carga->estaDescargada())->toBeTrue();
});

it('no deja que la fase contradiga a la carga', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => TipoCarga::Vacio]);

    $estado->fase = FaseCiclo::LimaJuliaca;
    $estado->confirmar(['fase'])->save();

    expect($estado->fresh()->fase)->toBe(FaseCiclo::SubidaMina);
});

it('distingue qué campos admiten sobrescritura al reimportar', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => TipoCarga::Concentrado]);
    $estado->confirmar(['ubicacion_id'])->save();

    expect($estado->admiteSobrescritura('ubicacion_id'))->toBeFalse()
        ->and($estado->admiteSobrescritura('cliente'))->toBeTrue()
        // Un campo del que nunca se anotó procedencia se puede escribir.
        ->and($estado->admiteSobrescritura('observaciones'))->toBeTrue();
});

it('no deduce nada mientras no haya tipo de carga', function (): void {
    $estado = EstadoUnidad::factory()->create(['tipo_carga' => null]);

    expect($estado->estado_carga)->toBeNull()
        ->and($estado->cliente)->toBeNull()
        ->and($estado->fase)->toBeNull();
});

it('guarda un solo estado por unidad y por día', function (): void {
    $tracto = Vehiculo::factory()->create();

    EstadoUnidad::factory()->create(['tracto_id' => $tracto->id, 'fecha' => '2026-07-20']);

    expect(fn () => EstadoUnidad::factory()->create([
        'tracto_id' => $tracto->id,
        'fecha' => '2026-07-20',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('encuentra el estado anterior aunque haya días sin reporte', function (): void {
    $tracto = Vehiculo::factory()->create();

    EstadoUnidad::factory()->create(['tracto_id' => $tracto->id, 'fecha' => '2026-07-17']);
    $viernes = EstadoUnidad::factory()->create(['tracto_id' => $tracto->id, 'fecha' => '2026-07-18']);
    $lunes = EstadoUnidad::factory()->create(['tracto_id' => $tracto->id, 'fecha' => '2026-07-21']);

    // Compara contra el reporte anterior que exista, no contra «ayer»: entre
    // viernes y lunes pasaron tres días y los saltos se juzgan con esos tres.
    expect($lunes->anterior()->id)->toBe($viernes->id)
        ->and($lunes->diasDesde($viernes))->toBe(3);
});

it('ignora los estados de otras unidades al buscar el anterior', function (): void {
    $tracto = Vehiculo::factory()->create();
    $otro = Vehiculo::factory()->create();

    EstadoUnidad::factory()->create(['tracto_id' => $otro->id, 'fecha' => '2026-07-20']);
    $estado = EstadoUnidad::factory()->create(['tracto_id' => $tracto->id, 'fecha' => '2026-07-21']);

    expect($estado->anterior())->toBeNull();
});

it('lista los estados con la ubicación todavía sin resolver', function (): void {
    EstadoUnidad::factory()->en('juliaca')->create();
    $pendiente = EstadoUnidad::factory()->conUbicacionSinResolver()->create();

    expect(EstadoUnidad::query()->sinUbicacionResuelta()->pluck('id')->all())
        ->toBe([$pendiente->id]);
});
