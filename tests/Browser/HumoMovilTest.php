<?php

/**
 * Pruebas de humo en celular (Pest 4 + Playwright): que cada pantalla carga
 * sin errores de JavaScript y que la página no se desborda a lo ancho.
 */

/** El ancho del documento no puede pasar el del viewport: scroll horizontal de página prohibido. */
const SIN_DESBORDE = 'document.documentElement.scrollWidth <= window.innerWidth';

it('muestra el login en celular sin desbordarse', function (): void {
    visit('/login')
        ->on()->mobile()
        ->assertSee('Iniciar sesión')
        ->assertScript(SIN_DESBORDE, true)
        ->assertNoJavaScriptErrors();
});
