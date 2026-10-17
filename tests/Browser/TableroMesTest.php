<?php

use App\Enums\TipoCarga;
use App\Models\Viaje;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El tablero se lee por mes: «Este mes» o un mes anterior del selector, y la
 * meta sigue al mes elegido.
 */
it('muestra la meta del mes elegido en el tablero', function (): void {
    Role::findOrCreate('admin', 'web');
    actingAs(actorConRol('admin'));

    $mesPasado = now()->subMonthNoOverflow()->startOfMonth();
    Viaje::factory()->deMinsur()->tipoCarga(TipoCarga::Concentrado)->count(3)->sequence(
        fn ($secuencia) => ['fecha_traslado' => $mesPasado->copy()->addDays($secuencia->index * 5)->toDateString()],
    )->create();

    visit('/dashboard?mes='.$mesPasado->format('Y-m'))
        ->on()->desktop()
        ->assertSee('Este mes')
        ->assertDontSee('Últimos 3 meses')
        ->assertSee('Mes cerrado')
        ->assertSee('Faltaron 117 viajes para la meta.')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'tablero-mes-cerrado');
});
