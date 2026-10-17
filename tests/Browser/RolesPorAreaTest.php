<?php

use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El menú y el panel de permisos se agrupan por las mismas áreas.
 */
it('agrupa el menú y el panel de roles por área', function (): void {
    Role::findOrCreate('admin', 'web');
    actingAs(actorConRol('admin'));

    visit('/settings/roles')
        ->on()->desktop()
        ->assertSee('FLOTA')
        ->assertSee('RRHH')
        ->assertSee('PROGRAMACIÓN')
        ->assertSee('CONTABILIDAD')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'roles-por-area');
});
