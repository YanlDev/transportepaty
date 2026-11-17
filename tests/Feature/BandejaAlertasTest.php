<?php

use App\Enums\TipoAlerta;
use App\Notifications\Alerta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
});

/**
 * La bandeja devuelve su propio prop `alertas` (el paginador) y, a la vez, el
 * prop compartido `campanita`. Son dos cosas distintas con nombres distintos:
 * si alguien los vuelve a nombrar igual, el paginador pisa a la campanita y la
 * campanita —que va en el encabezado de todas las páginas— revienta la página
 * entera (pantalla en blanco). Este test cuida justo eso.
 */
it('entrega la bandeja y la campanita como props distintos, sin pisarse', function (): void {
    $admin = actorConRol('admin');

    $admin->notify(new Alerta(
        TipoAlerta::DocumentosPorVencer,
        'SOAT por vencer',
        'El SOAT de la placa ABC-123 vence en 5 días.',
        '/unidades',
    ));

    actingAs($admin)
        ->get(route('alertas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('alertas/index')
            // El paginador de la bandeja.
            ->has('alertas.data', 1)
            ->where('alertas.data.0.titulo', 'SOAT por vencer')
            ->where('sinLeer', 1)
            // El prop compartido de la campanita, con su forma propia.
            ->has('campanita.recientes', 1)
            ->where('campanita.sin_leer', 1));
});
