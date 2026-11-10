<?php

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\ConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\LecturaKilometraje;
use App\Models\Llanta;
use App\Models\MovimientoEquipamiento;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\Vehiculo;
use App\Models\VencimientoEquipamiento;
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
        // Sin vale de almacén para la unidad, el formulario lo dice; el admin
        // puede cargar una que ya estaba puesta.
        ->assertSee('No hay llantas por montar en esta unidad')
        ->click('Ya estaba puesta')
        ->type('#llanta-marca', 'Michelin')
        ->type('#llanta-mm', '15')
        ->screenshot(filename: 'movil-llantas-instalar')
        ->click('Guardar')
        ->assertSee('1 de 10 llantas')
        ->click('Medir llantas')
        ->type('#mm-3', '2.5')
        ->click('Guardar medición')
        ->assertSee('Cambiar · 2.5 mm')
        ->click('[aria-label="Corregir medición"]')
        ->clear('#corregir-mm')
        ->type('#corregir-mm', '9')
        ->click('Guardar')
        ->assertSee('Bien · 9 mm')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-llantas-unidad');

    expect(Llanta::query()->sole()->posicion)->toBe('3');

    visit('/mantenimiento')
        ->on()->mobile()
        ->click('a[href$="/mantenimiento/llantas"]')
        ->assertSee('Ajustar cortes')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-llantas');
});

it('cuenta las fajas de una carreta desde el celular', function (): void {
    actingAs(actorConRol('admin'));

    ItemEquipamiento::query()->where('nombre', 'Faja de 4" con chicharra')->update(['minimo' => 2]);
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'TAB-123']);

    visit('/mantenimiento/equipamiento')
        ->on()->mobile()
        ->assertSee('TAB123')
        ->assertSee('Nunca se contó')
        ->click('TAB123')
        ->click('Contar equipamiento')
        ->click('[aria-label="Buenas de Faja de 4\" con chicharra: una más"]')
        ->screenshot(filename: 'movil-equipamiento-conteo')
        ->click('Guardar conteo')
        ->assertSee('Le falta')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-equipamiento-carreta');

    expect(ConteoEquipamiento::query()->where('vehiculo_id', $carreta->id)->count())->toBe(1);
});

it('achica en el navegador la foto elegida y muestra el peso antes y después', function (): void {
    actingAs(actorConRol('admin'));

    // Una foto grande armada en la propia página (3000 px, con ruido para que pese)
    // y puesta en el campo como si se hubiera elegido de la galería.
    $elegirFotoGrande = <<<'JS'
        new Promise((listo) => {
            const lienzo = document.createElement('canvas');
            lienzo.width = 3000;
            lienzo.height = 2250;
            const pincel = lienzo.getContext('2d');

            for (let i = 0; i < 40000; i++) {
                pincel.fillStyle = `rgb(${Math.random() * 255}, ${Math.random() * 255}, ${Math.random() * 255})`;
                pincel.fillRect(Math.random() * 3000, Math.random() * 2250, 40, 40);
            }

            lienzo.toBlob((blob) => {
                const carga = new DataTransfer();
                carga.items.add(new File([blob], 'tablero.png', { type: 'image/png' }));
                const campo = document.querySelector('input[type="file"][multiple]');
                campo.files = carga.files;
                campo.dispatchEvent(new Event('change', { bubbles: true }));

                // Resuelve con el texto de la fila cuando la foto ya está
                // comprimida y sumada al formulario (o tras 15 s, vacío).
                const inicio = Date.now();
                const espera = setInterval(() => {
                    const fila = Array.from(document.querySelectorAll('li')).find((li) => li.innerText.includes('tablero'));

                    if (fila || Date.now() - inicio > 15000) {
                        clearInterval(espera);
                        listo(fila ? fila.innerText : '');
                    }
                }, 200);
            }, 'image/png');
        })
    JS;

    $pagina = visit("/mantenimiento/ordenes/crear?vehiculo={$this->unidad->id}")->on()->mobile();
    $fila = $pagina->script($elegirFotoGrande);

    // Entró como PNG y quedó en JPEG, con el peso de antes y el de después.
    expect($fila)->toContain('tablero.jpg')->toContain(' → ');

    $pagina->assertNoJavaScriptErrors();
});

it('muestra en la orden lo que salió del almacén y lo que todavía le falta', function (): void {
    actingAs(actorConRol('admin'));

    // El plan Scania todavía no está ligado al catálogo: todo figura sin ligar.
    $orden = OrdenTrabajo::factory()->create([
        'vehiculo_id' => $this->unidad->id,
        'tipo' => TipoOrdenTrabajo::Preventiva,
        'servicio_mantenimiento_id' => PlanMantenimiento::deMarca('SCANIA')->servicios()->where('codigo', 'X')->value('id'),
        'estado' => EstadoOrdenTrabajo::EnProceso,
        'kilometraje' => 320000,
        'trabajo' => 'Servicio X',
    ]);

    visit("/mantenimiento/ordenes/{$orden->id}/editar")
        ->on()->mobile()
        ->assertSee($orden->numero())
        ->assertSee('Repuestos del almacén')
        ->assertSee('Sin artículo de almacén ligado')
        ->assertSee('Sacar repuestos del almacén')
        ->assertSee('Anular')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-mantenimiento-orden-materiales');
});

it('muestra el equipamiento de un tracto con su extintor vencido y deja pasarlo a otra unidad', function (): void {
    actingAs(actorConRol('admin'));

    $extintor = ItemEquipamiento::query()->where('nombre', 'Extintor')->sole();
    MovimientoEquipamiento::create([
        'vehiculo_id' => $this->unidad->id,
        'item_equipamiento_id' => $extintor->id,
        'tipo' => 'saldo_inicial',
        'cantidad' => 1,
        'fecha' => now()->toDateString(),
    ]);
    VencimientoEquipamiento::create(['vehiculo_id' => $this->unidad->id, 'item_equipamiento_id' => $extintor->id, 'vence_el' => now()->subMonth()->toDateString()]);

    visit("/mantenimiento/equipamiento/{$this->unidad->id}")
        ->on()->mobile()
        ->assertSee('Extintor')
        ->assertSee('Venció el')
        ->assertSee('Le falta')
        ->click('Pasar a otra unidad')
        ->assertSee('A qué unidad pasa')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-equipamiento-tracto');
});
