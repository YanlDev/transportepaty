<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\DotacionTramo;
use App\Models\LecturaKilometraje;
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
 * Un ticket listo para abastecer la ida: OK de taller y la tarjeta de 220
 * galones (150 Juliaca + 70 Arequipa) confirmada.
 */
function ticketListo(Vehiculo $tracto): Vuelta
{
    $vuelta = ticketApto($tracto);
    DotacionTramo::factory()->for($vuelta)->create();

    return $vuelta;
}

/**
 * @param  array<string, mixed>  $datos
 * @return array<string, mixed>
 */
function autorizacion(Vuelta $vuelta, array $datos = []): array
{
    return [
        'vehiculo_id' => $vuelta->tracto_id,
        'vuelta_id' => $vuelta->id,
        'sentido' => 'ida',
        'fecha' => now()->toDateString(),
        'galones' => 50,
        ...$datos,
    ];
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

it('suggests the descent with concentrate its card, and only the confirmed one counts', function (): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Ida, 'zona_origen' => ZonaRuta::Mina, 'zona_destino' => ZonaRuta::Pisco, 'tipo_carga' => TipoCarga::Concentrado,
    ]);
    $dotacion = app(DotacionVuelta::class);

    $sinConfirmar = $dotacion->de($vuelta, new Collection);
    $ida = collect($sinConfirmar['tramos'])->firstWhere('sentido', 'ida');
    expect($ida['confirmada'])->toBeFalse()
        ->and($ida['sugerida']['nombre'])->toBe('Juliaca – Mina – Lima')
        ->and($ida['previsto'])->toBeNull()
        ->and($sinConfirmar['viaticos'])->toBe([]);

    $dotacion->confirmar($vuelta, SentidoTramo::Ida, ReglaAbastecimiento::query()->where('nombre', 'Juliaca – Mina – Lima')->sole(), actorConRol('admin'));
    Abastecimiento::factory()->paraVuelta($vuelta)->create(['punto_abastecimiento_id' => $this->mina->id, 'galones' => 100]);

    $confirmada = $dotacion->de($vuelta->fresh('paradas.viajes'), $vuelta->abastecimientos()->with('punto')->get());

    expect($confirmada['tarjetas'][0]['nombre'])->toBe('Juliaca – Mina – Lima')
        ->and(collect($confirmada['combustible'])->keyBy('punto')->map(fn (array $fila): array => [$fila['previsto'], $fila['registrado'], $fila['falta']])->all())
        ->toBe(['Mina' => [100.0, 100.0, 0.0], 'Arequipa' => [120.0, 0.0, 120.0]])
        ->and($confirmada['total_previsto'])->toBe(220.0)
        ->and(collect($confirmada['viaticos'])->sum('monto'))->toBe(843.0);
});

it('keeps what was confirmed for a ticket even if the board changes later', function (): void {
    $vuelta = ticketListo($this->tracto);
    $tarjeta = ReglaAbastecimiento::query()->where('nombre', 'Juliaca – Mina – Lima')->sole();
    app(DotacionVuelta::class)->confirmar($vuelta, SentidoTramo::Ida, $tarjeta, actorConRol('admin'));

    $tarjeta->lineas()->where('tipo', TipoLineaAbastecimiento::Combustible)->update(['galones' => 10]);

    expect($vuelta->fresh()->dotacionDe(SentidoTramo::Ida)->tope())->toBe(220.0);
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

it('uses the Ransa cards for private cargo going up to the mine', function (): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Regreso, 'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Particular,
    ], ['peso' => 12, 'unidad_peso' => 'TNE']);

    expect(app(DotacionVuelta::class)->reglaDe($vuelta->paradas->first())->nombre)->toBe('Materiales Ransa a mina · hasta 15 t');
});

it('keeps the private return to Juliaca on its own card', function (): void {
    $vuelta = vueltaConParada($this->tracto, [
        'sentido' => SentidoTramo::Regreso, 'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Juliaca, 'tipo_carga' => TipoCarga::Particular,
    ], ['peso' => 12, 'unidad_peso' => 'TNE']);

    expect(app(DotacionVuelta::class)->reglaDe($vuelta->paradas->first())->nombre)->toBe('Retorno con material particular');
});

it('turns the old full-tank lines into lines whose gallons are still to be set', function (): void {
    $tarjeta = ReglaAbastecimiento::query()->where('nombre', 'Retorno por Cusco')->with('lineas.punto')->sole();
    $kio = $tarjeta->lineas->first(fn ($linea): bool => $linea->punto?->nombre === 'Kio');

    expect($kio->galones)->toBeNull()
        ->and($tarjeta->nota)->toContain('falta poner los galones');
});

it('registers the authorized fuel on the ticket and leg the person chose', function (): void {
    $vuelta = ticketListo($this->tracto);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta, ['punto_abastecimiento_id' => $this->arequipa->id, 'galones' => 120]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');

    $registro = Abastecimiento::query()->sole();
    expect($registro->vuelta_id)->toBe($vuelta->id)
        ->and($registro->sentido)->toBe(SentidoTramo::Ida)
        ->and((float) $registro->galones)->toBe(120.0)
        ->and($registro->importe)->toBeNull();
});

it('asks for the ticket and the leg instead of guessing them', function (): void {
    ticketListo($this->tracto);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), ['vehiculo_id' => $this->tracto->id, 'fecha' => now()->toDateString(), 'galones' => 50])
        ->assertSessionHasErrors(['vuelta_id', 'sentido']);

    expect(Abastecimiento::query()->count())->toBe(0);
});

it('does not put fuel of one unit on the ticket of another', function (): void {
    $ajeno = ticketListo(Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]));

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), [...autorizacion($ajeno), 'vehiculo_id' => $this->tracto->id])
        ->assertSessionHasErrors('vuelta_id');

    expect(Abastecimiento::query()->count())->toBe(0);
});

it('does not authorize fuel for a leg without its confirmed card', function (): void {
    $vuelta = ticketApto($this->tracto);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta))
        ->assertSessionHasErrors('sentido');

    // Ni con una tarjeta que dejó galones por definir.
    DotacionTramo::factory()->for($vuelta)->create(['lineas' => [
        ['linea_id' => null, 'tipo' => 'combustible', 'concepto' => 'Combustible', 'punto_id' => null, 'punto' => 'Kio', 'galones' => null, 'monto' => null],
    ]]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta))
        ->assertSessionHasErrors('sentido');
});

it('needs the permission and a reason to go over the cap of the card', function (): void {
    $vuelta = ticketListo($this->tracto);
    Abastecimiento::factory()->paraVuelta($vuelta)->create(['galones' => 200]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta, ['galones' => 30, 'motivo_exceso' => 'Desvío por huaico']))
        ->assertSessionHasErrors('galones');

    actingAs(actorConRol('admin'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta, ['galones' => 30]))
        ->assertSessionHasErrors('motivo_exceso');

    actingAs(actorConRol('admin'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta, ['galones' => 30, 'motivo_exceso' => 'Desvío por huaico']))
        ->assertSessionHasNoErrors();

    // Hasta el tope no pide nada.
    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion(ticketListo(Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto])), ['galones' => 220]))
        ->assertSessionHasNoErrors();

    expect(Abastecimiento::query()->where('motivo_exceso', 'Desvío por huaico')->count())->toBe(1);
});

it('voids fuel with its reason instead of deleting it, and it stops counting', function (): void {
    $vuelta = ticketListo($this->tracto);
    $registro = Abastecimiento::factory()->paraVuelta($vuelta)->create(['galones' => 120]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.anular', $registro), ['motivo' => ''])
        ->assertSessionHasErrors('motivo');
    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.anular', $registro), ['motivo' => 'Se registró dos veces'])
        ->assertSessionHas('toast.type', 'success');

    $registro->refresh();
    expect($registro->estaAnulado())->toBeTrue()
        ->and($registro->motivo_anulacion)->toBe('Se registró dos veces');

    $dotacion = app(DotacionVuelta::class)->de($vuelta->fresh('paradas.viajes'), $vuelta->abastecimientos()->with('punto')->get());
    expect($dotacion['total_registrado'])->toBe(0.0);
});

it('registers the real dispatch from the voucher with its price and odometer', function (): void {
    $vuelta = ticketListo($this->tracto);
    $registro = Abastecimiento::factory()->paraVuelta($vuelta)->create(['galones' => 120, 'punto_abastecimiento_id' => $this->arequipa->id]);

    actingAs(actorConRol('abastecimiento'))
        ->put(route('abastecimiento.despacho', $registro), [
            'galones_despachados' => 118.5,
            'precio_galon' => 16.4,
            'kilometraje' => 250300,
            'despachado_el' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    $registro->refresh();
    expect((float) $registro->galones_despachados)->toBe(118.5)
        ->and((float) $registro->importe)->toBe(1943.4)
        ->and(LecturaKilometraje::query()->where('vehiculo_id', $this->tracto->id)->value('kilometraje'))->toBe(250300);

    // El odómetro no retrocede.
    $otro = Abastecimiento::factory()->paraVuelta($vuelta)->create(['galones' => 50]);
    actingAs(actorConRol('abastecimiento'))
        ->put(route('abastecimiento.despacho', $otro), [
            'galones_despachados' => 50, 'precio_galon' => 16.4, 'kilometraje' => 249000, 'despachado_el' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('kilometraje');

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('puntos', fn ($puntos) => collect($puntos)->firstWhere('id', $this->arequipa->id)['ultimo_precio'] === 16.4));
});

it('registers fuel at a point that is not on the list, for exceptional cases', function (): void {
    $vuelta = ticketListo($this->tracto);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta, ['punto_libre' => '  Primax   Camaná ', 'galones' => 40]))
        ->assertSessionHasNoErrors();

    $registro = Abastecimiento::query()->with('punto')->sole();
    expect($registro->punto_abastecimiento_id)->toBeNull()
        ->and($registro->punto_libre)->toBe('Primax Camaná')
        ->and($registro->nombrePunto())->toBe('Primax Camaná');

    $dotacion = app(DotacionVuelta::class)->de($vuelta->load('paradas.viajes'), $vuelta->abastecimientos()->with('punto')->get());

    expect(collect($dotacion['combustible'])->firstWhere('punto', 'Primax Camaná')['registrado'])->toBe(40.0)
        ->and($dotacion['total_registrado'])->toBe(40.0);
});

it('does not take a listed point and a written one at the same time', function (): void {
    $vuelta = ticketListo($this->tracto);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta, ['punto_abastecimiento_id' => $this->kio->id, 'punto_libre' => 'Primax Camaná']))
        ->assertSessionHasErrors('punto_libre');
});

it('confirms the card of a leg from the board', function (): void {
    $vuelta = ticketApto($this->tracto);
    $tarjeta = ReglaAbastecimiento::query()->where('nombre', 'Juliaca – Mina – Lima')->sole();

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.dotacion', $vuelta), ['sentido' => 'ida', 'regla_abastecimiento_id' => $tarjeta->id])
        ->assertSessionHas('toast.type', 'success');

    $dotacion = $vuelta->fresh()->dotacionDe(SentidoTramo::Ida);
    expect($dotacion->nombre)->toBe('Juliaca – Mina – Lima')
        ->and($dotacion->tope())->toBe(220.0);

    actingAs(actorConRol('visor'))
        ->post(route('abastecimiento.dotacion', $vuelta), ['sentido' => 'regreso', 'regla_abastecimiento_id' => $tarjeta->id])
        ->assertForbidden();
});

it('shows for each point what was loaded this month and the last price paid', function (): void {
    Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'punto_abastecimiento_id' => $this->arequipa->id, 'galones' => 120, 'precio_galon' => 16.2, 'fecha' => now()->subDays(40)->toDateString()]);
    Abastecimiento::factory()->create(['vehiculo_id' => $this->tracto->id, 'punto_abastecimiento_id' => $this->arequipa->id, 'galones' => 70, 'precio_galon' => 16.5, 'fecha' => now()->toDateString()]);

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.configuracion'))
        ->assertInertia(fn (Assert $page) => $page->where('puntos', function ($puntos): bool {
            $arequipa = collect($puntos)->firstWhere('nombre', 'Arequipa');

            return $arequipa['galones_mes'] === 70 && $arequipa['ultimo_precio'] === 16.5;
        }));
});

it('lets a card speak for the whole outbound leg, from Juliaca up empty and down loaded', function (): void {
    $tarjeta = ReglaAbastecimiento::query()->where('nombre', 'Juliaca – Mina – Lima')->sole();
    $tarjeta->update(['zona_origen' => ZonaRuta::Juliaca]);

    $deJuliaca = Vuelta::factory()->create(['tracto_id' => $this->tracto->id]);
    ParadaVuelta::factory()->create([
        'vuelta_id' => $deJuliaca->id, 'sentido' => SentidoTramo::Ida, 'orden' => 1,
        'zona_origen' => ZonaRuta::Juliaca, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Vacio,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);
    ParadaVuelta::factory()->create([
        'vuelta_id' => $deJuliaca->id, 'sentido' => SentidoTramo::Ida, 'orden' => 2,
        'zona_origen' => ZonaRuta::Mina, 'zona_destino' => $tarjeta->zona_destino, 'tipo_carga' => $tarjeta->tipo_carga,
        'lugar_origen' => null, 'lugar_destino' => null,
    ]);

    expect(app(DotacionVuelta::class)->sugerida($deJuliaca->load('paradas.viajes'), SentidoTramo::Ida)?->nombre)->toBe('Juliaca – Mina – Lima');

    // La que arranca en la mina no hizo la subida desde Juliaca: no la toma.
    $deMina = vueltaConParada(Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]), [
        'sentido' => SentidoTramo::Ida, 'zona_origen' => ZonaRuta::Mina,
        'zona_destino' => $tarjeta->zona_destino, 'tipo_carga' => $tarjeta->tipo_carga,
    ]);

    expect(app(DotacionVuelta::class)->reglaDe($deMina->paradas->first(), ZonaRuta::Mina))->toBeNull();
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

it('asks for the gallons of every fuel row of a card', function (): void {
    $regla = ReglaAbastecimiento::query()->where('nombre', 'Retorno Pisco · escoria')->sole();

    actingAs(actorConRol('abastecimiento'))
        ->put(route('abastecimiento.reglas.update', $regla), [
            'nombre' => 'Retorno Pisco · escoria', 'activa' => true,
            'lineas' => [['tipo' => 'combustible', 'concepto' => 'Escoria', 'punto_abastecimiento_id' => $this->kio->id, 'galones' => null]],
        ])
        ->assertSessionHasErrors('lineas.0.galones');
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
 * Cada registro cuenta en el tramo que se eligió, no se reparte solo.
 */
it('counts each fuel in the leg that was chosen, against the cap of its card', function (): void {
    $vuelta = ticketListo($this->tracto);
    DotacionTramo::factory()->for($vuelta)->regreso()->create(['nombre' => 'Retorno Pisco · escoria', 'lineas' => [
        ['linea_id' => null, 'tipo' => 'combustible', 'concepto' => 'Escoria', 'punto_id' => $this->kio->id, 'punto' => 'Kio', 'galones' => 130.0, 'monto' => null],
    ]]);
    $juliaca = PuntoAbastecimiento::query()->where('nombre', 'Juliaca')->sole();
    Abastecimiento::factory()->paraVuelta($vuelta)->create(['punto_abastecimiento_id' => $juliaca->id, 'galones' => 150]);
    Abastecimiento::factory()->paraVuelta($vuelta)->create(['punto_abastecimiento_id' => $this->arequipa->id, 'galones' => 70]);
    Abastecimiento::factory()->paraVuelta($vuelta, SentidoTramo::Regreso)->create(['punto_abastecimiento_id' => $this->kio->id, 'galones' => 30]);
    Abastecimiento::factory()->paraVuelta($vuelta, SentidoTramo::Regreso)->anulado()->create(['galones' => 500]);

    $tramos = collect(app(DotacionVuelta::class)->de($vuelta->fresh('paradas.viajes'), $vuelta->abastecimientos()->with('punto')->get())['tramos'])->keyBy('sentido');

    expect($tramos['ida']['previsto'])->toBe(220.0)
        ->and($tramos['ida']['registrado'])->toBe(220.0)
        ->and($tramos['ida']['falta'])->toBe(0.0)
        ->and(collect($tramos['ida']['puntos'])->pluck('punto')->all())->toBe(['Juliaca', 'Arequipa'])
        ->and($tramos['regreso']['previsto'])->toBe(130.0)
        ->and($tramos['regreso']['registrado'])->toBe(30.0)
        ->and($tramos['regreso']['falta'])->toBe(100.0);
});

it('shows the month reconciliation by fuel station', function (): void {
    $vuelta = ticketListo($this->tracto);
    Abastecimiento::factory()->paraVuelta($vuelta)->create([
        'punto_abastecimiento_id' => $this->arequipa->id, 'galones' => 120,
        'galones_despachados' => 118, 'precio_galon' => 16, 'importe' => 1888, 'despachado_el' => now()->toDateString(),
    ]);
    Abastecimiento::factory()->paraVuelta($vuelta)->create(['punto_abastecimiento_id' => $this->arequipa->id, 'galones' => 50]);
    Abastecimiento::factory()->paraVuelta($vuelta)->anulado()->create(['galones' => 99]);

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.conciliacion'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('abastecimiento/conciliacion')
            ->where('totales.autorizado', 170)
            ->where('totales.despachado', 118)
            ->where('totales.sin_voucher', 1)
            ->where('totales.anulados', 1)
            ->where('porGrifo.0.punto', 'Arequipa')
            ->where('porGrifo.0.diferencia', -2)
            ->has('sinVoucher', 1));
});
