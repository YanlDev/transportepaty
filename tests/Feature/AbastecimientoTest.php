<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\LecturaKilometraje;
use App\Models\ParadaVuelta;
use App\Models\PuntoAbastecimiento;
use App\Models\RevisionTaller;
use App\Models\RutaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\DotacionVuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El área de abastecimiento: lo autorizado a cada ticket según sus rutas, el
 * combustible que se autoriza y el despacho real, y los puntos de carga.
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
    RutaVuelta::factory()->for($vuelta)->create();

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

it('keeps what a ticket was programmed with even if the catalog changes later', function (): void {
    $vuelta = ticketApto($this->tracto);
    $ruta = rutaDelCatalogo('Concentrado a Pisco')->load(['lineas.punto', 'variantes']);
    $vuelta->rutas()->create([...RutaVuelta::copiaDe($ruta, null), 'sentido' => SentidoTramo::Ida, 'orden' => 1]);

    $ruta->lineas()->where('tipo', TipoLineaAbastecimiento::Combustible)->update(['galones' => 10]);

    expect($vuelta->fresh()->topeDe(SentidoTramo::Ida))->toBe(220.0);
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

it('does not authorize fuel for a leg without a known cap', function (): void {
    $vuelta = ticketApto($this->tracto);

    // Sin ruta en el tramo.
    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta))
        ->assertSessionHasErrors('sentido');

    // Con una ruta que tiene variantes por peso y todavía no se eligió.
    $ransa = rutaDelCatalogo('Materiales a mina (Ransa)')->load(['lineas.punto', 'variantes']);
    $copia = $vuelta->rutas()->create([...RutaVuelta::copiaDe($ransa, null), 'sentido' => SentidoTramo::Ida, 'orden' => 1]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta))
        ->assertSessionHasErrors('sentido');

    // Ni con galones por definir.
    $copia->update(['variante_pendiente' => false, 'lineas' => [
        ['linea_id' => null, 'tipo' => 'combustible', 'concepto' => 'Combustible', 'punto_id' => null, 'punto' => 'Kio', 'galones' => null, 'monto' => null],
    ]]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.store'), autorizacion($vuelta))
        ->assertSessionHasErrors('sentido');
});

it('needs the permission and a reason to go over the cap of the routes', function (): void {
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

it('chooses the weight variant of a route, suggested by the weight of the GR', function (): void {
    $vuelta = ticketApto($this->tracto);
    $parada = ParadaVuelta::factory()->create([
        'vuelta_id' => $vuelta->id, 'sentido' => SentidoTramo::Regreso, 'orden' => 1,
        'zona_origen' => ZonaRuta::Lima, 'zona_destino' => ZonaRuta::Mina, 'tipo_carga' => TipoCarga::Materiales,
    ]);
    Viaje::factory()->create(['tracto_id' => null, 'peso' => 12000, 'unidad_peso' => 'KGM'])
        ->forceFill(['parada_vuelta_id' => $parada->id])->save();
    $ransa = rutaDelCatalogo('Materiales a mina (Ransa)')->load(['lineas.punto', 'variantes']);
    $copia = $vuelta->rutas()->create([...RutaVuelta::copiaDe($ransa, null), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);

    $sugerida = app(DotacionVuelta::class)->varianteSugerida($vuelta->load('paradas.viajes'), $copia->load('ruta.variantes'));
    expect($sugerida->nombre)->toBe('hasta 15 t')
        ->and($vuelta->topeDe(SentidoTramo::Regreso))->toBeNull();

    actingAs(actorConRol('visor'))
        ->post(route('abastecimiento.variante', $copia), ['variante_ruta_id' => $sugerida->id])
        ->assertForbidden();
    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.variante', $copia), ['variante_ruta_id' => $sugerida->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');

    expect($copia->fresh()->variante)->toBe('hasta 15 t')
        ->and($copia->fresh()->variante_pendiente)->toBeFalse()
        ->and($vuelta->fresh()->topeDe(SentidoTramo::Regreso))->toBe(130.0);
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

it('lists the points alphabetically', function (): void {
    PuntoAbastecimiento::query()->create(['nombre' => 'Aplao', 'zona' => $this->kio->zona, 'orden' => 99]);

    actingAs(actorConRol('abastecimiento'))
        ->get(route('abastecimiento.configuracion'))
        ->assertInertia(fn (Assert $page) => $page->where('puntos', function ($puntos): bool {
            $nombres = collect($puntos)->pluck('nombre')->all();
            $ordenados = $nombres;
            sort($ordenados, SORT_STRING | SORT_FLAG_CASE);

            return $nombres[0] === 'Aplao' && $nombres === $ordenados;
        }));
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
 * El total de las rutas es el tope del tramo: cómo se reparte entre puntos
 * varía (150 en Juliaca y 70 en Arequipa), así que cualquier punto cuenta.
 * Cada registro cuenta en el tramo que se eligió, no se reparte solo.
 */
it('counts each fuel in the leg that was chosen, against the cap of its routes', function (): void {
    $vuelta = ticketListo($this->tracto);
    RutaVuelta::factory()->for($vuelta)->regreso()->create(['nombre' => 'Escoria a mina', 'lineas' => [
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

it('corrects a mistyped dispatch odometer instead of keeping the wrong reading', function (): void {
    $vuelta = ticketListo($this->tracto);
    $registro = Abastecimiento::factory()->paraVuelta($vuelta)->create(['galones' => 50]);
    $voucher = ['galones_despachados' => 50, 'precio_galon' => 16, 'despachado_el' => now()->toDateString()];

    actingAs(actorConRol('abastecimiento'))
        ->put(route('abastecimiento.despacho', $registro), [...$voucher, 'kilometraje' => 2503000])
        ->assertSessionHasNoErrors();

    actingAs(actorConRol('abastecimiento'))
        ->put(route('abastecimiento.despacho', $registro), [...$voucher, 'kilometraje' => 250300])
        ->assertSessionHasNoErrors();

    expect(LecturaKilometraje::query()->where('vehiculo_id', $this->tracto->id)->pluck('kilometraje')->all())->toBe([250300]);

    // Si se borra el km, se va también la lectura.
    actingAs(actorConRol('abastecimiento'))
        ->put(route('abastecimiento.despacho', $registro), $voucher)
        ->assertSessionHasNoErrors();

    expect(LecturaKilometraje::query()->where('vehiculo_id', $this->tracto->id)->exists())->toBeFalse();
});

it('does not change the chosen variant once the leg has fuel', function (): void {
    $vuelta = ticketApto($this->tracto);
    $ransa = rutaDelCatalogo('Materiales a mina (Ransa)')->load(['lineas.punto', 'variantes']);
    [$primera, $segunda] = $ransa->variantes->take(2)->all();
    $copia = $vuelta->rutas()->create([...RutaVuelta::copiaDe($ransa, $primera), 'sentido' => SentidoTramo::Regreso, 'orden' => 1]);

    // Sin combustible, la variante todavía se puede cambiar.
    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.variante', $copia), ['variante_ruta_id' => $segunda->id])
        ->assertSessionHasNoErrors();

    Abastecimiento::factory()->paraVuelta($vuelta)->create(['galones' => 50, 'sentido' => SentidoTramo::Regreso]);

    actingAs(actorConRol('abastecimiento'))
        ->post(route('abastecimiento.variante', $copia->fresh()), ['variante_ruta_id' => $primera->id])
        ->assertSessionHasErrors('variante_ruta_id');

    expect($copia->fresh()->variante_ruta_id)->toBe($segunda->id);
});
