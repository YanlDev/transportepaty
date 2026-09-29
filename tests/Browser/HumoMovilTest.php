<?php

use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Pruebas de humo en celular (Pest 4 + Playwright): que cada pantalla carga
 * sin errores de JavaScript y que la página no se desborda a lo ancho.
 */

/** El ancho del documento no puede pasar el del viewport: scroll horizontal de página prohibido. */
const SIN_DESBORDE = 'document.documentElement.scrollWidth <= window.innerWidth';

beforeEach(function (): void {
    foreach (['admin', 'visor', 'contador'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

it('muestra el login en celular sin desbordarse', function (): void {
    visit('/login')
        ->on()->mobile()
        ->assertSee('Iniciar sesión')
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors();
});

it('muestra el tablero en celular con la navegación de pulgar', function (): void {
    actingAs(actorConRol('admin'));

    visit('/dashboard')
        ->on()->mobile()
        ->assertVisible('nav[aria-label="Navegación principal"]')
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors();
});

it('muestra Viajes en celular como tarjetas, con «Emitir GR» flotando al alcance del pulgar', function (): void {
    actingAs(actorConRol('admin'));
    Viaje::factory()->count(3)->create();

    visit('/viajes')
        ->on()->mobile()
        ->assertVisible('a[href$="/viajes/emitir"].fixed')
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-viajes');
});

it('muestra Emitir GR en celular con la acción fija abajo', function (): void {
    actingAs(actorConRol('admin'));

    visit('/viajes/emitir')
        ->on()->mobile()
        ->assertSee('GR-remitente')
        ->assertVisible('div.fixed button')
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-emitir');
});

/**
 * Las pantallas del día a día, con datos, en celular: cargan sin errores de
 * JavaScript y sin scroll horizontal de página.
 */
it('muestra sin desbordarse en celular la pantalla', function (string $ruta): void {
    actingAs(actorConRol('admin'));
    $tracto = Vehiculo::factory()->create();
    $conductor = Conductor::factory()->create();
    Viaje::factory()->count(3)->create(['tracto_id' => $tracto->id, 'conductor_id' => $conductor->id]);

    $ruta = str_replace(['{tracto}', '{conductor}'], [$tracto->id, $conductor->id], $ruta);

    visit($ruta)
        ->on()->mobile()
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil'.str_replace('/', '-', $ruta));
})->with([
    'tablero' => '/dashboard',
    'tractos' => '/tractos',
    'ficha de tracto' => '/vehiculos/{tracto}',
    'conductores' => '/conductores',
    'ficha de conductor' => '/conductores/{conductor}',
    'asistencia' => '/asistencia',
    'cobranza' => '/contabilidad',
]);

it('en tablet vertical muestra la tabla con el sidebar colapsado a íconos', function (string $ruta): void {
    actingAs(actorConRol('admin'));
    Viaje::factory()->count(3)->create();

    visit($ruta)
        ->resize(768, 1024)
        ->assertPresent('[data-slot="sidebar"][data-state="collapsed"]')
        ->assertScript("document.querySelector('table')?.offsetParent !== null", true)
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'tablet'.str_replace('/', '-', $ruta));
})->with(['viajes' => '/viajes', 'cobranza' => '/contabilidad']);

it('abre la lista de remitentes sin desbordar el celular', function (): void {
    actingAs(actorConRol('admin'));
    Viaje::factory()->count(3)->create([
        'cliente' => 'CRISAR LOGISTICA S.A.C.',
        'cliente_ruc' => '20603930844',
        'guias_remitente' => [['numero' => 'T930 - 49433', 'ruc' => '20331061655']],
    ]);

    visit('/viajes/emitir')
        ->on()->mobile()
        ->click('input[placeholder="RUC o nombre del remitente"]')
        ->assertSee('20331061655')
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'movil-emitir-remitentes');
});
