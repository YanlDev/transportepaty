<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('redirects guests to login', function (): void {
    $this->get(route('vueltas.index'))->assertRedirect(route('login'));
});

it('is read by whoever reads the programación', function (): void {
    actingAs(actorConRol('contador'))
        ->get(route('vueltas.index'))
        ->assertForbidden();

    actingAs(actorConRol('contador')->givePermissionTo('programacion.ver'))
        ->get(route('vueltas.index'))
        ->assertSuccessful();
});

it('shows each unit with its latest trip split into legs', function (): void {
    $vieja = Vuelta::factory()->create(['inicio' => '2026-09-01']);
    $actual = Vuelta::factory()->create(['tracto_id' => $vieja->tracto_id, 'inicio' => '2026-09-15']);
    ParadaVuelta::factory()->create([
        'vuelta_id' => $actual->id, 'sentido' => SentidoTramo::Ida, 'orden' => 1,
        'tipo_carga' => TipoCarga::Vacio, 'lugar_origen' => 'Juliaca', 'lugar_destino' => 'Antauta',
    ]);
    ParadaVuelta::factory()->create(['vuelta_id' => $actual->id, 'orden' => 2]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/index')
            ->has('unidades', 1)
            ->where('unidades.0.vueltas', 2)
            ->where('unidades.0.vuelta.codigo', $actual->fresh()->codigo)
            ->where('unidades.0.vuelta.ida.0.tipo_carga', 'vacio')
            ->where('unidades.0.vuelta.ida.0.origen', 'Juliaca')
            ->where('unidades.0.vuelta.regreso.0.tipo_carga', 'concentrado'));
});

it('lists the history of a unit with the GR of each stop', function (): void {
    $parada = ParadaVuelta::factory()->create();
    $guia = Viaje::factory()->create(['tracto_id' => $parada->vuelta->tracto_id]);
    $guia->forceFill(['parada_vuelta_id' => $parada->id])->save();

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.unidad', $parada->vuelta->tracto_id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/unidad')
            ->has('vueltas', 1)
            ->where('vueltas.0.regreso.0.guias.0.numero_gr', $guia->numero_gr));
});
