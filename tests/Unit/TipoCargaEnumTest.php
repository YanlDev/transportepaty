<?php

use App\Enums\TipoCarga;

it('deriva el tipo de carga desde la serie de una guía remitente de Minsur', function (string $numero, TipoCarga $esperado): void {
    expect(TipoCarga::desdeGuiaRemitenteMinsur($numero))->toBe($esperado);
})->with([
    ['T007 - 9609', TipoCarga::Concentrado],
    ['T007-9609', TipoCarga::Concentrado],
    ['T004 - 3548', TipoCarga::Metalico],
    ['T005 - 305', TipoCarga::Escoria],
    ['T012 - 745', TipoCarga::Materiales],
    ['T008 - 513', TipoCarga::Materiales],
]);

it('devuelve null para una serie que no reconoce, sin asumir un default', function (): void {
    expect(TipoCarga::desdeGuiaRemitenteMinsur('T104 - 2132'))->toBeNull();
});
