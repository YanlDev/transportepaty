<?php

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
