<?php

use App\Enums\Cliente;
use App\Enums\EstadoCarga;
use App\Enums\FaseCiclo;
use App\Enums\TipoCarga;

it('acepta la ruta declarada de cada tipo de carga', function (TipoCarga $carga, string $origen, string $destino): void {
    expect($carga->permiteRuta($origen, $destino))->toBeTrue();
})->with([
    'concentrado baja de mina a Pisco' => [TipoCarga::Concentrado, 'san_rafael', 'pisco'],
    'metálico va de Pisco al puerto' => [TipoCarga::Metalico, 'pisco', 'callao'],
    'escoria vuelve de Pisco a mina' => [TipoCarga::Escoria, 'pisco', 'san_rafael'],
    'sacos vuelven de Pisco a mina' => [TipoCarga::Sacos, 'pisco', 'san_rafael'],
    'metálico se libera directo desde San Rafael' => [TipoCarga::Metalico, 'san_rafael', 'callao'],
    'materiales suben desde Lima' => [TipoCarga::Materiales, 'lima', 'san_rafael'],
    'materiales suben desde Pisco' => [TipoCarga::Materiales, 'pisco', 'san_rafael'],
    'vacío sube a cargar' => [TipoCarga::Vacio, 'juliaca', 'san_rafael'],
]);

it('acepta cualquier ruta para la carga particular, porque varía de viaje en viaje', function (): void {
    expect(TipoCarga::Particular->tieneRutasDefinidas())->toBeFalse()
        ->and(TipoCarga::Particular->permiteRuta('lima', 'juliaca'))->toBeTrue()
        ->and(TipoCarga::Particular->permiteRuta('arequipa', 'tacna'))->toBeTrue();
});

it('rechaza anotar la carga futura en vez de vacío cuando la unidad sube a mina', function (): void {
    // El error más común del Excel: la unidad sube vacía a cargar, pero alguien
    // anota ya el concentrado que va a traer de bajada.
    expect(TipoCarga::Concentrado->permiteRuta('juliaca', 'san_rafael'))->toBeFalse();
});

it('rechaza arrastrar la ruta del viaje anterior junto con la carga nueva', function (): void {
    expect(TipoCarga::Concentrado->permiteRuta('lima', 'san_rafael'))->toBeFalse();
});

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
    // comparten ruta, tramo y cliente.
    expect(TipoCarga::Sacos->rutasValidas())->toBe(TipoCarga::Escoria->rutasValidas())
        ->and(TipoCarga::Sacos->permiteRuta('pisco', 'san_rafael'))->toBeTrue()
        ->and(TipoCarga::Sacos->permiteRuta('lima', 'juliaca'))->toBeFalse();
});

it('declara rutas para todos los tipos de carga salvo el particular', function (TipoCarga $carga): void {
    expect($carga->tieneRutasDefinidas())->toBe($carga !== TipoCarga::Particular);
})->with(TipoCarga::cases());
