<?php

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\TipoVuelta;
use App\Enums\ZonaRuta;
use App\Models\Conductor;
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

/**
 * El kanban: cada vuelta en su etapa. Una en curso está «bajando» hasta que
 * su regreso tiene GR, «subiendo» hasta que esa GR llega a la base (Juliaca
 * o la mina), y ahí queda «en base», disponible para la siguiente.
 */
it('places each trip in its stage on the board', function (): void {
    $guiaEn = function (Vuelta $vuelta, SentidoTramo $sentido, ZonaRuta $origen, ZonaRuta $destino, string $fecha): void {
        $parada = ParadaVuelta::factory()->create([
            'vuelta_id' => $vuelta->id, 'sentido' => $sentido, 'orden' => $vuelta->paradas()->count() + 1,
            'zona_origen' => $origen, 'zona_destino' => $destino,
            'lugar_origen' => $origen->label(), 'lugar_destino' => $destino->label(),
        ]);
        Viaje::factory()->create(['tracto_id' => null, 'fecha_traslado' => $fecha])
            ->forceFill(['parada_vuelta_id' => $parada->id])->save();
    };

    $programada = Vuelta::factory()->programada()->create(['inicio' => now()->addDay()->toDateString()]);
    $bajando = Vuelta::factory()->create(['inicio' => now()->subDays(2)->toDateString()]);
    $guiaEn($bajando, SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, now()->subDays(2)->toDateString());
    $subiendo = Vuelta::factory()->create(['inicio' => now()->subDays(8)->toDateString()]);
    $guiaEn($subiendo, SentidoTramo::Regreso, ZonaRuta::Lima, ZonaRuta::Sur, now()->subDays(1)->toDateString());
    $llego = Vuelta::factory()->create(['inicio' => now()->subDays(12)->toDateString()]);
    $guiaEn($llego, SentidoTramo::Regreso, ZonaRuta::Lima, ZonaRuta::Juliaca, now()->subDays(4)->toDateString());
    $cerrada = Vuelta::factory()->create(['estado' => EstadoVuelta::Cerrada, 'inicio' => now()->subDays(15)->toDateString(), 'fin' => now()->subDay()->toDateString()]);
    $parada = Vuelta::factory()->create(['estado' => EstadoVuelta::Cerrada, 'inicio' => now()->subDays(60)->toDateString(), 'fin' => now()->subDays(45)->toDateString()]);
    // Llegó a la base pero ya tiene la siguiente programada: se ve una vez.
    $conSiguiente = Vuelta::factory()->create(['inicio' => now()->subDays(12)->toDateString()]);
    $guiaEn($conSiguiente, SentidoTramo::Regreso, ZonaRuta::Pisco, ZonaRuta::Mina, now()->subDays(3)->toDateString());
    $siguiente = Vuelta::factory()->programada()->create(['tracto_id' => $conSiguiente->tracto_id, 'inicio' => now()->addDays(2)->toDateString()]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/index')
            ->where('tablero', function ($tablero) use ($programada, $bajando, $subiendo, $llego, $cerrada, $parada, $conSiguiente, $siguiente): bool {
                $etapas = collect($tablero)->pluck('etapa', 'id');

                return $etapas->get($programada->id) === 'programada'
                    && $etapas->get($bajando->id) === 'en_ida'
                    && $etapas->get($subiendo->id) === 'en_vuelta'
                    && $etapas->get($llego->id) === 'en_base'
                    && $etapas->get($cerrada->id) === 'en_base'
                    && $etapas->get($siguiente->id) === 'programada'
                    && ! $etapas->has($parada->id)
                    && ! $etapas->has($conSiguiente->id)
                    && collect($tablero)->firstWhere('id', $llego->id)['dias_en_base'] === 4
                    && collect($tablero)->firstWhere('id', $bajando->id)['dias_en_ruta'] === 3
                    && collect($tablero)->firstWhere('id', $bajando->id)['actual']['texto'] === 'Mina San Rafael → Pisco';
            }));
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
 * Lo que manda el formulario para la plantilla de escoria: baja con
 * concentrado y sube con escoria desde Pisco.
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
            ['sentido' => 'regreso', 'zona_origen' => 'pisco', 'zona_destino' => 'mina', 'tipo_carga' => 'escoria'],
            ['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
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
        ->and($vuelta->paradas->pluck('tipo_carga')->all())->toBe([TipoCarga::Concentrado, TipoCarga::Escoria])
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
    [$concentrado, $escoria] = $vuelta->paradas->all();

    // Llega la GR de la escoria: esa parada ya pasó.
    Viaje::factory()->create([
        'tracto_id' => $tracto->id, 'fecha_traslado' => '2026-10-28', 'fecha_emision' => '2026-10-28',
        'origen' => 'KM 245 - PARACAS - PISCO - ICA', 'destino' => 'SAN RAFAEL - ANTAUTA - MELGAR - PUNO',
        'tipo_carga' => TipoCarga::Escoria,
    ]);

    // En el formulario se suma el metálico a la ida. La escoria viaja con
    // su id e intenta cambiarse: no debe tocarse.
    actingAs(actorConRol('admin'))
        ->put(route('vueltas.update', $vuelta), vueltaDelFormulario($tracto->id, ['paradas' => [
            ['id' => $concentrado->id, 'sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
            ['sentido' => 'ida', 'zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'metalico'],
            ['id' => $escoria->id, 'sentido' => 'regreso', 'zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'materiales'],
        ]]))
        ->assertRedirect();

    $paradas = $vuelta->fresh()->paradas;

    expect($paradas->pluck('tipo_carga')->all())->toBe([TipoCarga::Concentrado, TipoCarga::Metalico, TipoCarga::Escoria])
        ->and($paradas->last()->zona_origen)->toBe(ZonaRuta::Pisco)
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

it('programs the outbound leg first and the return when it is known', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);

    // Al salir solo se sabe la ida.
    actingAs(actorConRol('admin'))->post(route('vueltas.store'), vueltaDelFormulario($tracto->id, ['paradas' => [
        ['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
    ]]));
    $vuelta = Vuelta::query()->where('origen', OrigenVuelta::Programacion)->sole();
    expect($vuelta->paradas()->where('sentido', SentidoTramo::Regreso)->count())->toBe(0);

    // En Pisco se sabe: particular a Tacna, y de ahí sigue a Juliaca.
    $ida = $vuelta->paradas()->sole();
    actingAs(actorConRol('admin'))->put(route('vueltas.update', $vuelta), vueltaDelFormulario($tracto->id, ['paradas' => [
        ['id' => $ida->id, 'sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
        ['sentido' => 'ida', 'zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'vacio'],
        ['sentido' => 'regreso', 'zona_origen' => 'lima', 'zona_destino' => 'sur', 'lugar_destino' => 'Tacna', 'tipo_carga' => 'particular'],
        ['sentido' => 'regreso', 'zona_origen' => 'sur', 'lugar_origen' => 'Tacna', 'zona_destino' => 'juliaca', 'tipo_carga' => 'vacio'],
    ]]))->assertRedirect();

    $regreso = $vuelta->fresh()->paradas->where('sentido', SentidoTramo::Regreso)->values();
    expect($regreso->pluck('lugar_destino')->all())->toBe(['Tacna', null])
        ->and($regreso[1]->lugar_origen)->toBe('Tacna')
        ->and($regreso[1]->zona_destino)->toBe(ZonaRuta::Juliaca);
});

it('fills the trailer and driver from the latest GR by date', function (): void {
    $tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto, 'placa' => 'BYM-861']);
    $carretaNueva = Vehiculo::factory()->carreta()->create(['placa' => 'VDI-980']);
    $carretaVieja = Vehiculo::factory()->carreta()->create(['placa' => 'VDI-111']);

    // La más reciente por traslado se registró primero; la vieja, después.
    $conductor = Conductor::factory()->create();
    Viaje::factory()->create([
        'tracto_id' => $tracto->id, 'carreta_id' => $carretaNueva->id, 'placa_carreta' => 'VDI-980',
        'numero_gr' => 'EG03-00000020', 'fecha_traslado' => '2026-10-20', 'conductor_nombre' => 'MACHACA ALBERTO',
        'conductor_id' => $conductor->id,
    ]);
    Viaje::factory()->create([
        'tracto_id' => $tracto->id, 'carreta_id' => $carretaVieja->id, 'placa_carreta' => 'VDI-111',
        'numero_gr' => 'EG03-00000010', 'fecha_traslado' => '2026-10-01',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page->where('tractos', function ($tractos) use ($tracto, $carretaNueva): bool {
            $unidad = collect($tractos)->firstWhere('id', $tracto->id);

            return $unidad['carreta_id'] === $carretaNueva->id
                && $unidad['ultima_gr']['numero'] === 'EG03-00000020'
                && $unidad['ultima_gr']['placa_carreta'] === 'VDI-980'
                && $unidad['ultima_gr']['conductor'] === 'MACHACA ALBERTO';
        })
            // Y al revés: elegir primero al conductor deja su unidad y carreta.
            ->where('conductores', function ($conductores) use ($conductor, $tracto, $carretaNueva): bool {
                $elegido = collect($conductores)->firstWhere('id', $conductor->id);

                return $elegido['tracto_id'] === $tracto->id && $elegido['carreta_id'] === $carretaNueva->id;
            }));
});
