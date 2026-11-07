<?php

use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\Vuelta;

use function Pest\Laravel\actingAs;

/**
 * El seguimiento va por fecha, no por placa: lo que sale antes, arriba.
 */
it('ordena el seguimiento por fecha de salida', function (): void {
    Vuelta::factory()->programada()->create([
        'tracto_id' => Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'AAA-111'])->id,
        'inicio' => now()->addDays(2)->toDateString(),
    ]);
    Vuelta::factory()->programada()->create([
        'tracto_id' => Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'ZZZ-111'])->id,
        'inicio' => now()->addDay()->toDateString(),
    ]);

    actingAs(actorConRol('admin'));

    visit('/vueltas')
        ->on()->desktop()
        ->assertSee('AAA111')
        ->assertSee('ZZZ111')
        ->assertScript('document.body.innerText.indexOf("ZZZ111") < document.body.innerText.indexOf("AAA111")')
        ->assertNoJavaScriptErrors();
});
