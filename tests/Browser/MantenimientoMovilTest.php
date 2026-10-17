<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\LecturaKilometraje;
use App\Models\Llanta;
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
        ->assertSee('Accesos rápidos')
        ->assertSee('Llantas')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mantenimiento-tablero');

    visit('/mantenimiento/preventivo')
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

it('filtra por marca desde su tarjeta y configura su plan', function (): void {
    actingAs(actorConRol('admin'));

    Vehiculo::factory()->create(['placa' => 'BUK-717', 'marca' => 'VOLVO']);

    visit('/mantenimiento')
        ->assertSee('MERCEDES BENZ')
        ->assertSee('X 25 mil · S 75 mil · M 150 mil')
        ->assertDontSee('TCD928')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'mantenimiento-tablero')
        ->click('SCANIA')
        ->assertPathIs('/mantenimiento/preventivo')
        ->assertSee('TCD928')
        ->assertDontSee('BUK717')
        ->assertSee('Configurar plan')
        ->screenshot(filename: 'mantenimiento-tablero-scania')
        ->click('Configurar plan')
        ->assertSee('Plan preventivo SCANIA')
        ->assertSee('Cómo queda el calendario')
        ->screenshot(filename: 'mantenimiento-plan')
        ->clear('#km-X')
        ->type('#km-X', '15000')
        ->click('Guardar plan')
        ->assertSee('X 15 mil · S 40 mil')
        ->assertNoJavaScriptErrors();

    expect(PlanMantenimiento::deMarca('SCANIA')->servicios()->where('codigo', 'X')->value('intervalo_km'))->toBe(15000);
});

it('muestra el plan de una marca en celular sin desbordarse', function (): void {
    actingAs(actorConRol('admin'));

    visit('/mantenimiento/planes/'.PlanMantenimiento::deMarca('INTERNATIONAL')->id.'/editar')
        ->on()->mobile()
        ->assertSee('Plan preventivo INTERNATIONAL')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mantenimiento-plan');
});

it('instala y mide una llanta desde el esquema en el celular', function (): void {
    actingAs(actorConRol('admin'));

    visit('/mantenimiento/llantas/'.$this->unidad->id)
        ->on()->mobile()
        ->assertSee('0 de 10 llantas')
        ->click('[aria-label^="Posición 3:"]')
        ->assertSee('Eje 2, exterior izquierda')
        ->click('Instalar llanta')
        ->type('#llanta-marca', 'Michelin')
        ->type('#llanta-mm', '15')
        ->screenshot(filename: 'movil-llantas-instalar')
        ->click('Guardar')
        ->assertSee('1 de 10 llantas')
        ->click('Medir llantas')
        ->type('#mm-3', '2.5')
        ->click('Guardar medición')
        ->assertSee('Cambiar · 2.5 mm')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-llantas-unidad');

    expect(Llanta::query()->sole()->posicion)->toBe('3');

    visit('/mantenimiento')
        ->on()->mobile()
        ->click('a[href$="/mantenimiento/llantas"]')
        ->assertSee('1 para cambiar')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-llantas');
});
