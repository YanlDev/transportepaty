<?php

use App\Enums\Cliente;
use App\Enums\EstadoCarga;
use App\Enums\FaseCiclo;
use App\Enums\TipoCarga;

it('deduce el estado de carga del tipo de carga', function (): void {
    expect(TipoCarga::Vacio->estadoCarga())->toBe(EstadoCarga::Vacio)
        ->and(TipoCarga::Vacio->estadoCarga()->estaDescargada())->toBeTrue()
        ->and(TipoCarga::Concentrado->estadoCarga())->toBe(EstadoCarga::Cargado)
        ->and(TipoCarga::Particular->estadoCarga()->estaDescargada())->toBeFalse();
});

it('deduce el cliente del tipo de carga', function (): void {
    expect(TipoCarga::Concentrado->cliente())->toBe(Cliente::Minsur)
        ->and(TipoCarga::Metalico->cliente())->toBe(Cliente::Minsur)
        ->and(TipoCarga::Escoria->cliente())->toBe(Cliente::Minsur)
        ->and(TipoCarga::Materiales->cliente())->toBe(Cliente::Minsur)
        ->and(TipoCarga::Sacos->cliente())->toBe(Cliente::Minsur)
        ->and(TipoCarga::Particular->cliente())->toBe(Cliente::Particular);
});

it('no le inventa cliente a la unidad vacía', function (): void {
    expect(TipoCarga::Vacio->cliente())->toBeNull();
});

it('ubica cada carga en su tramo del circuito', function (): void {
    expect(TipoCarga::Vacio->fase())->toBe(FaseCiclo::SubidaMina)
        ->and(TipoCarga::Concentrado->fase())->toBe(FaseCiclo::MinaPisco)
        ->and(TipoCarga::Metalico->fase())->toBe(FaseCiclo::RetornoPisco)
        ->and(TipoCarga::Escoria->fase())->toBe(FaseCiclo::RetornoPisco)
        ->and(TipoCarga::Materiales->fase())->toBe(FaseCiclo::RetornoPisco)
        ->and(TipoCarga::Sacos->fase())->toBe(FaseCiclo::RetornoPisco)
        ->and(TipoCarga::Particular->fase())->toBe(FaseCiclo::LimaJuliaca);
});

it('trata los sacos como el otro retorno cargado a mina', function (): void {
    // Los sacos suben de Pisco a San Rafael igual que la escoria, así que
    // comparten tramo y cliente.
    expect(TipoCarga::Sacos->fase())->toBe(TipoCarga::Escoria->fase())
        ->and(TipoCarga::Sacos->cliente())->toBe(TipoCarga::Escoria->cliente());
});
