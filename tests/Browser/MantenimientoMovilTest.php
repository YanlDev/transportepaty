<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\Vehiculo;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Mantenimiento se usa en la base, con el celular o la tablet en la mano:
 * cada pantalla carga sin errores ni scroll horizontal, y el km se registra
 * desde el botón flotante.
 */
beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');

    $this->unidad = Vehiculo::factory()->create(['placa' => 'TCD-928', 'marca' => 'SCANIA']);

    OrdenTrabajo::factory()->create([
        'vehiculo_id' => $this->unidad->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => PlanMantenimiento::deMarca('SCANIA')->servicios()->where('codigo', 'S')->value('id'),
        'estado' => EstadoOrdenTrabajo::Finalizada,
        'kilometraje' => 300000,
        'trabajo' => 'Servicio S',
    ]);
    LecturaKilometraje::factory()->create(['vehiculo_id' => $this->unidad->id, 'kilometraje' => 318500]);
});

it('muestra el semáforo, la ficha y la orden en celular sin desbordarse', function (): void {
    actingAs(actorConRol('admin'));

    visit('/mantenimiento')
        ->on()->mobile()
        ->assertSee('TCD928')
        ->assertSee('Faltan 1,500 km')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mantenimiento');

    visit("/mantenimiento/unidades/{$this->unidad->id}")
        ->on()->mobile()
        ->assertSee('Para el Servicio X')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mantenimiento-ficha');

    visit("/mantenimiento/ordenes/crear?vehiculo={$this->unidad->id}")
        ->on()->mobile()
        ->assertSee('Servicio del plan')
        ->assertSee('Tomar foto')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mantenimiento-orden');
});

it('registra el km desde el botón flotante del celular', function (): void {
    actingAs(actorConRol('admin'));

    visit("/mantenimiento/unidades/{$this->unidad->id}")
        ->on()->mobile()
        ->click('button.fixed')
        ->assertSee('El kilometraje del tablero')
        ->type('#km-valor', '319200')
        ->assertSee('Recorrió 700 km desde el último registro.')
        ->screenshot(filename: 'movil-mantenimiento-km')
        ->click('Guardar km')
        ->assertSee('Faltan 800 km')
        ->assertSee('319,200 km')
        ->assertNoJavaScriptErrors();

    expect(LecturaKilometraje::query()->max('kilometraje'))->toBe(319200);
});
