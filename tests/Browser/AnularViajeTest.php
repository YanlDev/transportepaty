<?php

use App\Models\Viaje;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
});

/**
 * El diálogo de anular se abre desde una fila que, al tocarla, abre el detalle
 * del viaje. Los clics dentro del diálogo no deben llegar a la fila.
 */
it('deja marcar la baja en SUNAT sin abrir el detalle del viaje', function (): void {
    actingAs(actorConRol('admin'));
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00012631']);

    visit('/viajes')
        ->on()->desktop()
        ->click("[aria-label=\"Anular la GR {$viaje->numero_gr}\"]")
        ->assertSee('Anular GR EG03-00012631')
        ->click('Darla de baja también en SUNAT')
        ->assertSee('Antes de iniciar el traslado')
        ->assertSee('Dar de baja y anular')
        ->assertDontSee('Viaje del')
        ->assertNoJavaScriptErrors();
});
