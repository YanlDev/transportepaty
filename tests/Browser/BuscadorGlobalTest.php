<?php

use App\Models\Vehiculo;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El buscador de la barra: desde cualquier pantalla se llega a una placa sin
 * pasar por el menú.
 */
it('encuentra una unidad por su placa desde el encabezado y la abre', function (): void {
    Role::findOrCreate('admin', 'web');
    $unidad = Vehiculo::factory()->create(['placa' => 'CCB-888']);
    actingAs(actorConRol('admin'));

    visit('/tractos')
        ->on()->desktop()
        ->click('button:has-text("Ctrl K")')
        ->type('[cmdk-input]', 'ccb888')
        ->assertSee('Unidades')
        ->click('[cmdk-item]:has-text("CCB-888")')
        ->assertPathIs("/vehiculos/{$unidad->id}")
        ->assertNoJavaScriptErrors();
});
