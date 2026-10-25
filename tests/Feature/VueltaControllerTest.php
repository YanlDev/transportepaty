<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\TipoVuelta;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
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
    // Sin tracto para que no se asigne sola: se engancha a mano.
    $guia = Viaje::factory()->create(['tracto_id' => null]);
    $guia->forceFill(['parada_vuelta_id' => $parada->id])->save();

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.unidad', $parada->vuelta->tracto_id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/unidad')
            ->has('vueltas', 1)
            ->where('vueltas.0.regreso.0.guias.0.numero_gr', $guia->numero_gr));
});

/**
 * Lo que manda el formulario para la plantilla de escoria: sube con escoria
 * desde Pisco y baja con concentrado.
 *
 * @return array<string, mixed>
 */
function vueltaDelFormulario(int $tractoId, array $cambios = []): array
{
    return [
        'tracto_id' => $tractoId,
        'carreta_id' => null,
        'conductor_id' => null,
        'inicio' => '2026-10-28',
        'paradas' => [
            // El regreso viene primero a propósito: se guarda igual en orden.
            ['sentido' => 'regreso', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
            ['sentido' => 'ida', 'zona_origen' => 'pisco', 'zona_destino' => 'mina', 'tipo_carga' => 'escoria'],
        ],
        ...$cambios,
    ];
}

it('programs a trip with its stops, outbound leg first', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $anterior = Vuelta::factory()->create(['tracto_id' => $tracto->id, 'inicio' => '2026-10-10']);

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.store'), vueltaDelFormulario($tracto->id))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    $vuelta = Vuelta::query()->where('origen', OrigenVuelta::Programacion)->sole();

    expect($vuelta->estado)->toBe(EstadoVuelta::Programada)
        ->and($vuelta->tipo)->toBe(TipoVuelta::Mina)
        ->and($vuelta->inicio->toDateString())->toBe('2026-10-28')
        ->and($vuelta->paradas->pluck('tipo_carga')->all())->toBe([TipoCarga::Escoria, TipoCarga::Concentrado])
        // Programar la siguiente da por terminada la que estaba andando.
        ->and($anterior->fresh()->estado)->toBe(EstadoVuelta::Cerrada);
});

it('checks the stops of a programmed trip', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.store'), vueltaDelFormulario($tracto->id, [
            'paradas' => [['sentido' => 'ida', 'zona_origen' => 'marte', 'zona_destino' => 'mina', 'tipo_carga' => 'escoria']],
        ]))
        ->assertSessionHasErrors(['paradas.0.zona_origen']);

    actingAs(actorConRol('admin'))
        ->post(route('vueltas.store'), vueltaDelFormulario($tracto->id, ['paradas' => []]))
        ->assertSessionHasErrors(['paradas']);
});

it('only lets whoever programs create trips', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);

    actingAs(actorConRol('contador')->givePermissionTo('programacion.ver'))
        ->post(route('vueltas.store'), vueltaDelFormulario($tracto->id))
        ->assertForbidden();
});

it('edits what has not happened yet and keeps the stops with GR', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    actingAs(actorConRol('admin'))->post(route('vueltas.store'), vueltaDelFormulario($tracto->id));
    $vuelta = Vuelta::query()->where('origen', OrigenVuelta::Programacion)->sole();
    [$escoria, $concentrado] = $vuelta->paradas->all();

    // Llega la GR de la escoria: esa parada ya pasó.
    Viaje::factory()->create([
        'tracto_id' => $tracto->id, 'fecha_traslado' => '2026-10-28', 'fecha_emision' => '2026-10-28',
        'origen' => 'KM 245 - PARACAS - PISCO - ICA', 'destino' => 'SAN RAFAEL - ANTAUTA - MELGAR - PUNO',
        'tipo_carga' => TipoCarga::Escoria,
    ]);

    // En el formulario se cambia el regreso: concentrado y metálico. La
    // escoria viaja con su id e intenta cambiarse: no debe tocarse.
    actingAs(actorConRol('admin'))
        ->put(route('vueltas.update', $vuelta), vueltaDelFormulario($tracto->id, ['paradas' => [
            ['id' => $escoria->id, 'sentido' => 'ida', 'zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'materiales'],
            ['id' => $concentrado->id, 'sentido' => 'regreso', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
            ['sentido' => 'regreso', 'zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'metalico'],
        ]]))
        ->assertRedirect();

    $paradas = $vuelta->fresh()->paradas;

    expect($paradas->pluck('tipo_carga')->all())->toBe([TipoCarga::Escoria, TipoCarga::Concentrado, TipoCarga::Metalico])
        ->and($paradas->first()->zona_origen)->toBe(ZonaRuta::Pisco)
        ->and($vuelta->fresh()->estado)->toBe(EstadoVuelta::EnCurso);
});

it('removes a programmed trip only while it has no GR', function (): void {
    $sinGuias = Vuelta::factory()->programada()->create();
    ParadaVuelta::factory()->create(['vuelta_id' => $sinGuias->id]);

    actingAs(actorConRol('admin'))->delete(route('vueltas.destroy', $sinGuias))->assertRedirect();
    expect($sinGuias->fresh())->toBeNull();

    $conGuias = Vuelta::factory()->programada()->create();
    $parada = ParadaVuelta::factory()->create(['vuelta_id' => $conGuias->id]);
    Viaje::factory()->create(['tracto_id' => null])->forceFill(['parada_vuelta_id' => $parada->id])->save();

    actingAs(actorConRol('admin'))
        ->delete(route('vueltas.destroy', $conGuias))
        ->assertSessionHas('toast.type', 'error');
    expect($conGuias->fresh())->not->toBeNull();
});

it('does not edit the trips rebuilt from the GR', function (): void {
    $historial = Vuelta::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('vueltas.update', $historial), vueltaDelFormulario($historial->tracto_id))
        ->assertForbidden();

    actingAs(actorConRol('admin'))
        ->delete(route('vueltas.destroy', $historial))
        ->assertForbidden();
});
