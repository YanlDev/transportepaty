<?php

use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * La lista va por ticket, del más nuevo al más antiguo, no por fecha ni por
 * placa: el último programado queda arriba aunque salga después.
 */
it('ordena la programación del ticket más nuevo al más antiguo', function (): void {
    Vuelta::factory()->programada()->create([
        'tracto_id' => Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'AAA-111'])->id,
        'inicio' => now()->addDay()->toDateString(),
    ]);
    Vuelta::factory()->programada()->create([
        'tracto_id' => Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'ZZZ-111'])->id,
        'inicio' => now()->addDays(2)->toDateString(),
    ]);

    actingAs(actorConRol('admin'));

    visit('/vueltas')
        ->on()->desktop()
        ->assertSee('AAA111')
        ->assertSee('ZZZ111')
        ->assertScript('document.body.innerText.indexOf("ZZZ111") < document.body.innerText.indexOf("AAA111")')
        ->assertNoJavaScriptErrors();
});
