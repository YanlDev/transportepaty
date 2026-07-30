<?php

use App\Enums\FaseCiclo;

it('permite que la unidad siga en la misma fase entre un reporte y el siguiente', function (FaseCiclo $fase): void {
    // Un tramo dura días: lo normal es que dos reportes seguidos encuentren a
    // la unidad en la misma etapa.
    expect($fase->puedeTransicionarA($fase))->toBeTrue();
})->with(FaseCiclo::cases());

it('permite las transiciones que sigue el circuito', function (FaseCiclo $desde, FaseCiclo $hacia): void {
    expect($desde->puedeTransicionarA($hacia))->toBeTrue();
})->with([
    'sube vacía y baja con concentrado' => [FaseCiclo::SubidaMina, FaseCiclo::MinaPisco],
    'llega a Pisco y se define el retorno' => [FaseCiclo::MinaPisco, FaseCiclo::RetornoPisco],
    'vuelve cargada a mina y encadena otra bajada' => [FaseCiclo::RetornoPisco, FaseCiclo::MinaPisco],
    'descarga en el puerto y busca carga en Lima' => [FaseCiclo::RetornoPisco, FaseCiclo::LimaJuliaca],
    'llega a base y vuelve a subir' => [FaseCiclo::LimaJuliaca, FaseCiclo::SubidaMina],
]);

it('rechaza pasar de bajar con concentrado a andar con carga particular', function (): void {
    // El salto que motivó esta validación: una unidad no puede pasar de llevar
    // concentrado hacia Pisco a estar con carga particular rumbo a Juliaca sin
    // haber pasado nunca por el retorno, donde se decide qué hace en Pisco.
    expect(FaseCiclo::MinaPisco->puedeTransicionarA(FaseCiclo::LimaJuliaca))->toBeFalse();
});

it('rechaza los saltos que se comen una etapa entera', function (FaseCiclo $desde, FaseCiclo $hacia): void {
    expect($desde->puedeTransicionarA($hacia))->toBeFalse();
})->with([
    'sube a mina y aparece retornando de Pisco' => [FaseCiclo::SubidaMina, FaseCiclo::RetornoPisco],
    'sube a mina y aparece con particular' => [FaseCiclo::SubidaMina, FaseCiclo::LimaJuliaca],
    'baja con concentrado y aparece subiendo vacía' => [FaseCiclo::MinaPisco, FaseCiclo::SubidaMina],
    'retorna de Pisco y aparece subiendo vacía' => [FaseCiclo::RetornoPisco, FaseCiclo::SubidaMina],
    'vuelve con particular y aparece bajando concentrado' => [FaseCiclo::LimaJuliaca, FaseCiclo::MinaPisco],
    'vuelve con particular y aparece retornando de Pisco' => [FaseCiclo::LimaJuliaca, FaseCiclo::RetornoPisco],
]);

it('no deja ninguna fase sin salida ni ninguna fase inalcanzable', function (): void {
    // Lo que hace que el circuito sea cerrado y no una escalera con final: de
    // toda fase se sale, y a toda fase se llega. Si alguna quedara suelta, una
    // unidad podría atascarse ahí y no volver nunca a la subida a mina.
    $alcanzables = [];

    foreach (FaseCiclo::cases() as $fase) {
        expect($fase->transicionesValidas())->not->toBeEmpty();

        $alcanzables = [...$alcanzables, ...$fase->transicionesValidas()];
    }

    expect(array_unique($alcanzables, SORT_REGULAR))
        ->toHaveCount(count(FaseCiclo::cases()));
});
