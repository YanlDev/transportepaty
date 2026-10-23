<?php

use Inertia\Testing\AssertableInertia as Assert;

/**
 * La franja de «AMBIENTE DE PRUEBAS» sale solo en trpatytest: es el único que
 * corre con APP_ENV=staging, y en producción no debe aparecer nunca.
 */
it('marks the staging environment as a test environment', function (): void {
    app()['env'] = 'staging';

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('esAmbientePrueba', true));
});

it('does not mark production as a test environment', function (): void {
    app()['env'] = 'production';

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('esAmbientePrueba', false));
});
