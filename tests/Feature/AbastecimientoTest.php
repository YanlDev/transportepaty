<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\Ajuste;
use App\Models\ParadaVuelta;
use App\Models\PuntoAbastecimiento;
use App\Models\ReglaAbastecimiento;
use App\Models\RevisionTaller;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\DotacionVuelta;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El área de abastecimiento: la pizarra de dotaciones (cargada por
 * migración desde la foto de la oficina) y el registro de lo autorizado.
 */
beforeEach(function (): void {
    foreach (['admin', 'contador', 'visor'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
    $this->kio = PuntoAbastecimiento::query()->where('nombre', 'Kio')->sole();
    $this->arequipa = PuntoAbastecimiento::query()->where('nombre', 'Arequipa')->sole();
    $this->mina = PuntoAbastecimiento::query()->where('nombre', 'Mina')->sole();
});

/**
 * Un ticket abierto de la unidad que ya tiene el OK de taller: sin él no se
 * registra combustible.
 */
function ticketApto(Vehiculo $tracto, array $atributos = []): Vuelta
{
    $vuelta = Vuelta::factory()->create(['tracto_id' => $tracto->id, 'inicio' => now()->subDay()->toDateString(), ...$atributos]);
    RevisionTaller::factory()->create(['vuelta_id' => $vuelta->id]);

    return $vuelta;
}

/**
 * Una vuelta abierta con una parada (y su GR, si se pasa la dirección).
 */
function vueltaConParada(Vehiculo $tracto, array $parada, ?array $guia = null): Vuelta
{
    $vuelta = Vuelta::factory()->create(['tracto_id' => $tracto->id, 'inicio' => now()->subDays(2)->toDateString()]);
    $creada = ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'orden' => 1, 'lugar_origen' => null, 'lugar_destino' => null, ...$parada,
    ]);

    if ($guia !== null) {
        Viaje::factory()->create(['tracto_id' => null, ...$guia])
            ->forceFill(['parada_vuelta_id' => $creada->id])->save();
    }

    return $vuelta->load('paradas.viajes');
}

it('loads the office board', function (): void {
    expect(PuntoAbastecimiento::query()->pluck('nombre')->all())
        ->toContain('Kio', 'Mina', 'Arequipa', 'Nazca', 'Mellizas')
        ->and(ReglaAbastecimiento::query()->where('nombre', 'Juliaca – Mina – Lima')->sole()->lineas)->toHaveCount(4)
        ->and(ReglaAbastecimiento::query()->where('nombre', 'like', '%Desaguadero%')->exists())->toBeFalse();
});

it('gives the descent with concentrate its board allowance', function (): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Ida, 'zona_origen' => ZonaRuta::Mina, 'zona_destino' => ZonaRuta::Pisco, 'tipo_carga' => TipoCarga::Concentrado,
    ]);
    Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'punto_abastecimiento_id' => $this->mina->id, 'galones' => 100]);

    $dotacion = app(DotacionVuelta::class)->de($vuelta, $vuelta->abastecimientos()->with('punto')->get());

    expect($dotacion['tarjetas'][0]['nombre'])->toBe('Juliaca – Mina – Lima')
        ->and(collect($dotacion['combustible'])->keyBy('punto')->map(fn (array $fila): array => [$fila['previsto'], $fila['registrado'], $fila['falta']])->all())
        ->toBe(['Mina' => [100.0, 100.0, 0.0], 'Arequipa' => [120.0, 0.0, 120.0]])
        ->and($dotacion['total_previsto'])->toBe(220.0)
        ->and(collect($dotacion['viaticos'])->sum('monto'))->toBe(843.0);
});

it('prefers the most specific card: Chilca over the general private return', function (): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Regreso, 'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Juliaca, 'tipo_carga' => TipoCarga::Particular,
        'lugar_origen' => 'Chilca',
    ]);

    expect(app(DotacionVuelta::class)->reglaDe($vuelta->paradas->first())->nombre)->toBe('Retorno de Chilca – Juliaca');
});

it('picks the Ransa card by the weight of the GR', function (float $peso, string $unidad, string $tarjeta): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Regreso, 'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Materiales,
    ], ['peso' => $peso, 'unidad_peso' => $unidad]);

    expect(app(DotacionVuelta::class)->reglaDe($vuelta->paradas->first())->nombre)->toBe($tarjeta);
})->with([
    '4 t' => [4, 'TNE', 'Materiales Ransa a mina · menos de 5 t'],
    '12 000 kg' => [12000, 'KGM', 'Materiales Ransa a mina · hasta 15 t'],
    '28 t' => [28, 'TNE', 'Materiales Ransa a mina · más de 25 t'],
]);

it('counts a full tank only once its gallons are configured', function (): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Regreso, 'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Cusco, 'tipo_carga' => TipoCarga::Particular,
    ]);

    $sinConfigurar = app(DotacionVuelta::class)->de($vuelta, new Collection);
    expect($sinConfigurar['total_previsto'])->toBeNull()
        ->and(collect($sinConfigurar['combustible'])->firstWhere('punto', 'Kio')['lleno'])->toBeTrue();

    Ajuste::guardar(Ajuste::GALONES_TANQUE_LLENO, '150');

    expect(app(DotacionVuelta::class)->de($vuelta, new Collection)['total_previsto'])->toBe(350.0);
});

it('registers the authorized fuel on the open trip of the unit', function (): void {
    $vuelta = ticketApto($this->tracto);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), [
            'vehiculo_id' => $this->tracto->id,
            'punto_abastecimiento_id' => $this->arequipa->id,
            'fecha' => now()->toDateString(),
            'galones' => 120,
        ])
        ->assertSessionHas('toast.type', 'success');

    $registro = Abastecimiento::query()->sole();
    expect($registro->vuelta_id)->toBe($vuelta->id)
        ->and((float) $registro->galones)->toBe(120.0);
});

it('keeps the price per gallon and the amount, and proposes the last price of the point', function (): void {
    ticketApto($this->tracto);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), [
            'vehiculo_id' => $this->tracto->id,
            'punto_abastecimiento_id' => $this->arequipa->id,
            'fecha' => now()->toDateString(),
            'galones' => 120,
            'precio_galon' => 16.45,
        ])
        ->assertSessionHas('toast.type', 'success');

    $registro = Abastecimiento::query()->sole();
    expect((float) $registro->precio_galon)->toBe(16.45)
        ->and((float) $registro->importe)->toBe(1974.0);

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recientes.0.importe', 1974)
            ->where('puntos', fn ($puntos) => collect($puntos)->firstWhere('id', $this->arequipa->id)['ultimo_precio'] === 16.45));
});

it('registers without price when it is not known', function (): void {
    ticketApto($this->tracto);

    actingAs(actorConRol('abastecimiento'))->post(route('abastecimiento.store'), [
        'vehiculo_id' => $this->tracto->id,
        'fecha' => now()->toDateString(),
        'galones' => 50,
    ]);

    expect(Abastecimiento::query()->sole()->importe)->toBeNull();
});

it('uses the configured full tank when the gallons are left empty', function (): void {
    ticketApto($this->tracto);

    $datos = [
        'vehiculo_id' => $this->tracto->id,
        'punto_abastecimiento_id' => $this->kio->id,
        'fecha' => now()->toDateString(),
        'tanque_lleno' => true,
    ];

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), $datos)
        ->assertSessionHasErrors('galones');

    Ajuste::guardar(Ajuste::GALONES_TANQUE_LLENO, '150');

    actingAs(actorConRol('abastecimiento'))->post(route('abastecimiento.store'), $datos);

    expect((float) Abastecimiento::query()->sole()->galones)->toBe(150.0);
});

it('keeps the area to its role', function (): void {
    // Facturación entra por los viáticos, pero no toca la pizarra.
    actingAs(actorConRol('contador'))->get(route('abastecimiento.index'))->assertOk();
    actingAs(actorConRol('contador'))->get(route('abastecimiento.configuracion'))->assertForbidden();
    actingAs(actorConRol('visor'))->get(route('abastecimiento.index'))->assertForbidden();

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page->component('abastecimiento/index')->has('vueltas'));
});

it('edits a board card with its rows', function (): void {
    $regla = ReglaAbastecimiento::query()->where('nombre', 'Retorno Pisco · escoria')->sole();

    actingAs(actorConRol('abastecimiento'))
        ->put(route('abastecimiento.reglas.update', $regla), [
            'nombre' => 'Retorno Pisco · escoria',
            'color' => 'rosa',
            'sentido' => 'regreso',
            'zona_origen' => 'pisco',
            'zona_destino' => 'mina',
            'tipo_carga' => 'escoria',
            'activa' => true,
            'lineas' => [
                ['tipo' => 'combustible', 'concepto' => 'Escoria', 'punto_abastecimiento_id' => $this->kio->id, 'galones' => 140],
                ['tipo' => 'viatico', 'concepto' => 'Viáticos', 'monto' => 200],
            ],
        ])
        ->assertSessionHas('toast.type', 'success');

    $lineas = $regla->fresh()->lineas;
    expect($lineas)->toHaveCount(2)
        ->and((float) $lineas[0]->galones)->toBe(140.0)
        ->and($lineas[1]->tipo)->toBe(TipoLineaAbastecimiento::Viatico)
        ->and((float) $lineas[1]->monto)->toBe(200.0);
});

it('deactivates a point already used instead of deleting it', function (): void {
    actingAs(actorConRol('abastecimiento'))->delete(route('abastecimiento.puntos.destroy', $this->kio));

    expect($this->kio->fresh()->activo)->toBeFalse();

    // Y se puede volver a activar desde su edición.
    actingAs(actorConRol('abastecimiento'))->put(route('abastecimiento.puntos.update', $this->kio), [
        'nombre' => 'Kio', 'zona' => 'lima', 'detalle' => null, 'activo' => true,
    ]);

    expect($this->kio->fresh()->activo)->toBeTrue();
});

/**
 * El total de la tarjeta es el tope del tramo: cómo se reparte entre puntos
 * varía (150 en Juliaca y 70 en Arequipa), así que cualquier punto cuenta.
 */
it('splits the allowance into one bar for the outbound leg and one for the return', function (): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Ida, 'zona_origen' => ZonaRuta::Mina, 'zona_destino' => ZonaRuta::Pisco, 'tipo_carga' => TipoCarga::Concentrado,
    ]);
    ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'orden' => 2, 'sentido' => SentidoTramo::Regreso,
        'zona_origen' => ZonaRuta::Pisco, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Escoria,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);
    $juliaca = PuntoAbastecimiento::query()->where('nombre', 'Juliaca')->sole();
    Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'punto_abastecimiento_id' => $juliaca->id, 'galones' => 150]);
    Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'punto_abastecimiento_id' => $this->arequipa->id, 'galones' => 70]);
    Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'vuelta_id' => $vuelta->id, 'punto_abastecimiento_id' => $this->kio->id, 'galones' => 30]);
    $vuelta->load('paradas.viajes');

    $tramos = collect(app(DotacionVuelta::class)->de($vuelta, $vuelta->abastecimientos()->with('punto')->get())['tramos'])->keyBy('sentido');

    // 150 en Juliaca + 70 en Arequipa completan los 220 de la ida, aunque la
    // pizarra sugiera Mina 100 + Arequipa 120; lo que sobra va a la vuelta.
    expect($tramos['ida']['previsto'])->toBe(220.0)
        ->and($tramos['ida']['registrado'])->toBe(220.0)
        ->and($tramos['ida']['falta'])->toBe(0.0)
        ->and(collect($tramos['ida']['puntos'])->pluck('punto')->all())->toBe(['Mina', 'Arequipa'])
        ->and($tramos['regreso']['previsto'])->toBe(130.0)
        ->and($tramos['regreso']['registrado'])->toBe(30.0)
        ->and($tramos['regreso']['falta'])->toBe(100.0);
});
