<?php

use App\Models\PlanMantenimiento;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * La puerta única de la configuración: todos la abren (al menos tienen su
 * cuenta) y cada quien ve solo lo que puede tocar.
 */
beforeEach(function (): void {
    foreach (['admin', 'visor'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

it('opens for anyone logged in', function (): void {
    actingAs(actorConRol('visor'))
        ->get(route('configuracion.index'))
        ->assertInertia(fn (Assert $page) => $page->component('configuracion/index')->where('planes', []));
});

it('lists the maintenance plans only to whoever configures them', function (): void {
    $plan = PlanMantenimiento::query()->where('marca', 'SCANIA')->sole();

    actingAs(actorConRol('admin'))
        ->get(route('configuracion.index'))
        ->assertInertia(fn (Assert $page) => $page->where('planes', fn ($planes): bool => collect($planes)->contains('id', $plan->id)));
});

it('sends guests to log in', function (): void {
    $this->get(route('configuracion.index'))->assertRedirect(route('login'));
});
