<?php

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Los controladores avisan con `->with('toast', [...])`: el aviso tiene que
 * llegar al cliente como flash de Inertia, que es lo que muestra el toast.
 */
it('turns the toast a controller flashes into Inertia flash data on the next page', function (): void {
    $admin = actorConRol('admin');

    actingAs($admin)
        ->from(route('rutas.tramos'))
        ->put(route('rutas.tramos.guardar'), ['filas' => [
            ['zona_origen' => 'lima', 'zona_destino' => 'arequipa', 'galones_cargado' => 150],
        ]])
        ->assertRedirect(route('rutas.tramos'))
        ->assertSessionHas('toast');

    actingAs($admin)
        ->get(route('rutas.tramos'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rutas/tramos')
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Galones por tramo guardados. Los tickets con rutas por tramos ya usan los números nuevos.'));
});

it('shows the toast only once', function (): void {
    $admin = actorConRol('admin');

    actingAs($admin)
        ->from(route('rutas.tramos'))
        ->put(route('rutas.tramos.guardar'), ['filas' => [
            ['zona_origen' => 'lima', 'zona_destino' => 'arequipa', 'galones_cargado' => 150],
        ]]);

    actingAs($admin)
        ->get(route('rutas.tramos'))
        ->assertInertia(fn (Assert $page) => $page->hasFlash('toast.type', 'success'));

    actingAs($admin)
        ->get(route('rutas.tramos'))
        ->assertInertia(fn (Assert $page) => $page->missingFlash('toast'));
});
