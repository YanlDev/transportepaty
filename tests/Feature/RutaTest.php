<?php

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\DestinoParticular;
use App\Models\LineaRuta;
use App\Models\PalabraZona;
use App\Models\PuntoAbastecimiento;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * El catálogo de rutas: un solo lugar para el recorrido y la dotación de
 * cada tramo estándar. Viene cargado con las opciones de tramo y la pizarra
 * de la oficina; se elige al programar y queda copiado en el ticket.
 */
beforeEach(function (): void {
    foreach (['admin', 'contador', 'visor'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }

    $this->tracto = Vehiculo::factory()->create(['tipo' => TipoVehiculo::Tracto]);
});

/**
 * @param  list<array{sentido: string, ruta_id: int, variante_ruta_id: int|null}>  $rutas
 * @return array<string, mixed>
 */
function programar(Vehiculo $tracto, array $rutas, array $paradas): array
{
    return [
        'tracto_id' => $tracto->id,
        'carreta_id' => null,
        'conductor_id' => null,
        'inicio' => now()->toDateString(),
        'paradas' => $paradas,
        'rutas' => $rutas,
    ];
}

it('comes loaded with the options and the office board, together', function (): void {
    $concentrado = rutaDelCatalogo('Concentrado a Pisco')->load('lineas.punto');

    expect(Ruta::query()->where('sentido', SentidoTramo::Ida)->where('es_complemento', false)->pluck('nombre')->all())
        ->toContain('Concentrado a Pisco', 'Particular a Lima', 'Vacía a Lima')
        ->and($concentrado->paradas)->toBe([['zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado']])
        ->and($concentrado->topePara(null))->toBe(220.0)
        ->and($concentrado->lineas->where('tipo', TipoLineaAbastecimiento::Viatico)->sum('monto'))->toEqual(843)
        // El metálico va incluido en el concentrado: es un complemento sin dotación propia.
        ->and(rutaDelCatalogo('Metálico al Callao')->es_complemento)->toBeTrue()
        ->and(rutaDelCatalogo('Metálico al Callao')->lineas)->toHaveCount(0)
        ->and(rutaDelCatalogo('Particular a otro destino')->pide_destino)->toBeTrue()
        ->and(DestinoParticular::query()->pluck('lugar')->all())->toContain('Tacna', 'Ilo', 'Cusco')
        ->and(PalabraZona::query()->where('palabra', 'ANTAUTA')->sole()->zona)->toBe(ZonaRuta::Mina);
});

it('turns the Ransa cards into weight variants of one route', function (): void {
    $ransa = rutaDelCatalogo('Materiales a mina (Ransa)')->load(['lineas.punto', 'variantes']);

    expect($ransa->variantes->pluck('nombre')->all())->toBe(['menos de 5 t', 'hasta 8 t', 'hasta 15 t', 'hasta 20 t', 'hasta 25 t', 'más de 25 t'])
        ->and($ransa->topePara(null))->toBeNull()
        ->and($ransa->varianteParaPeso(12)->nombre)->toBe('hasta 15 t')
        ->and($ransa->topePara($ransa->varianteParaPeso(12)))->toBe(130.0)
        ->and($ransa->varianteParaPeso(30)->nombre)->toBe('más de 25 t');
});

it('keeps every board card: the full tank at Kio goes apart from the cap', function (): void {
    $cusco = rutaDelCatalogo('Particular a Cusco')->load(['lineas.punto', 'variantes']);
    $kio = $cusco->lineas->first(fn (LineaRuta $linea): bool => $linea->punto?->nombre === 'Kio');

    // Sin galones fijos: lo dice el voucher. El tope es lo de Nazca.
    expect($kio->galones)->toBeNull()
        ->and($kio->tanque_lleno)->toBeTrue()
        ->and($cusco->topePara(null))->toBe(200.0)
        ->and(rutaDelCatalogo('Camaná (arroz)')->es_complemento)->toBeTrue();
});

it('copies the chosen routes into the ticket when programming, with their complements', function (): void {
    $paradas = [
        ['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
        ['sentido' => 'ida', 'zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'metalico'],
    ];

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null],
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Metálico al Callao')->id, 'variante_ruta_id' => null],
    ], $paradas))->assertSessionHasNoErrors();

    $vuelta = Vuelta::query()->sole();

    expect($vuelta->rutas->pluck('nombre')->all())->toBe(['Concentrado a Pisco', 'Metálico al Callao'])
        ->and($vuelta->rutas[1]->es_complemento)->toBeTrue()
        ->and($vuelta->topeDe(SentidoTramo::Ida))->toBe(220.0);
});

it('asks for one main route per leg that has stops, valid for that leg', function (): void {
    $ida = [['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado']];

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [], $ida))
        ->assertSessionHasErrors('rutas');
    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Metálico al Callao')->id, 'variante_ruta_id' => null],
    ], $ida))->assertSessionHasErrors('rutas');
    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Escoria a mina')->id, 'variante_ruta_id' => null],
    ], $ida))->assertSessionHasErrors('rutas.0.ruta_id');
    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => rutaDelCatalogo('Materiales a mina (Ransa)')->variantes()->value('id')],
    ], $ida))->assertSessionHasErrors('rutas.0.variante_ruta_id');

    expect(Vuelta::query()->count())->toBe(0);
});

it('leaves the weight variant pending until someone chooses it', function (): void {
    $vuelta = [['sentido' => 'regreso', 'zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'materiales']];
    $ransa = rutaDelCatalogo('Materiales a mina (Ransa)');

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [
        ['sentido' => 'regreso', 'ruta_id' => $ransa->id, 'variante_ruta_id' => null],
    ], $vuelta))->assertSessionHasNoErrors();

    $ticket = Vuelta::query()->sole();
    expect($ticket->rutas->sole()->variante_pendiente)->toBeTrue()
        ->and($ticket->topeDe(SentidoTramo::Regreso))->toBeNull();

    // Al editar, elegir la variante la copia.
    actingAs(actorConRol('admin'))->put(route('vueltas.update', $ticket), programar($this->tracto, [
        ['sentido' => 'regreso', 'ruta_id' => $ransa->id, 'variante_ruta_id' => $ransa->variantes()->where('nombre', 'hasta 8 t')->value('id')],
    ], $vuelta))->assertSessionHasNoErrors();

    expect($ticket->fresh()->topeDe(SentidoTramo::Regreso))->toBe(120.0);
});

it('keeps the copy of a ticket when editing it, even if the catalog changed', function (): void {
    $paradas = [['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado']];
    $rutas = rutasParaProgramar($paradas);

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, $rutas, $paradas));
    $vuelta = Vuelta::query()->sole();
    rutaDelCatalogo('Concentrado a Pisco')->lineas()->where('tipo', TipoLineaAbastecimiento::Combustible)->update(['galones' => 10]);

    actingAs(actorConRol('admin'))->put(route('vueltas.update', $vuelta), programar($this->tracto, $rutas, $paradas))
        ->assertSessionHasNoErrors();

    expect($vuelta->fresh()->topeDe(SentidoTramo::Ida))->toBe(220.0);
});

it('is configured by whoever configures programming or fuel', function (): void {
    actingAs(actorConRol('contador'))->get(route('rutas.index'))->assertForbidden();
    actingAs(actorConRol('abastecimiento'))
        ->get(route('rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rutas/index')
            ->has('rutas', Ruta::query()->count())
            ->where('rutas', fn ($rutas): bool => collect($rutas)->firstWhere('nombre', 'Materiales a mina (Ransa)')['lineas'][0]['variante'] === 0));
});

it('edits a route keeping the ids of the rows that stay', function (): void {
    $escoria = rutaDelCatalogo('Escoria a mina')->load('lineas');
    $combustible = $escoria->lineas->firstWhere('tipo', TipoLineaAbastecimiento::Combustible);
    $kio = PuntoAbastecimiento::query()->where('nombre', 'Kio')->sole();

    actingAs(actorConRol('admin'))->put(route('rutas.update', $escoria), [
        'nombre' => 'Escoria a mina',
        'sentido' => 'regreso',
        'es_complemento' => false,
        'dias_viaje' => 3,
        'km_estandar' => 1200,
        'activa' => true,
        'paradas' => [['zona_origen' => 'pisco', 'zona_destino' => 'mina', 'tipo_carga' => 'escoria']],
        'variantes' => [],
        'lineas' => [
            ['id' => $combustible->id, 'variante' => null, 'tipo' => 'combustible', 'concepto' => 'Escoria', 'punto_abastecimiento_id' => $kio->id, 'galones' => 140],
            ['id' => null, 'variante' => null, 'tipo' => 'peaje', 'concepto' => 'Peajes', 'monto' => 90],
        ],
    ])->assertSessionHasNoErrors()->assertSessionHas('toast.type', 'success');

    $escoria->refresh()->load('lineas');
    expect($escoria->lineas->pluck('id')->first())->toBe($combustible->id)
        ->and((float) $escoria->lineas->first()->galones)->toBe(140.0)
        ->and($escoria->lineas)->toHaveCount(2)
        ->and($escoria->dias_viaje)->toBe(3);
});

it('creates a route with weight variants', function (): void {
    actingAs(actorConRol('admin'))->post(route('rutas.store'), [
        'nombre' => 'Arena a mina',
        'sentido' => 'regreso',
        'es_complemento' => false,
        'activa' => true,
        'paradas' => [['zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'materiales']],
        'variantes' => [
            ['id' => null, 'nombre' => 'hasta 10 t', 'peso_desde' => null, 'peso_hasta' => 10],
            ['id' => null, 'nombre' => 'más de 10 t', 'peso_desde' => 10, 'peso_hasta' => null],
        ],
        'lineas' => [
            ['id' => null, 'variante' => 0, 'tipo' => 'combustible', 'concepto' => 'Total', 'galones' => 120],
            ['id' => null, 'variante' => 1, 'tipo' => 'combustible', 'concepto' => 'Total', 'galones' => 160],
            ['id' => null, 'variante' => null, 'tipo' => 'viatico', 'concepto' => 'Viáticos', 'monto' => 200],
        ],
    ])->assertSessionHasNoErrors();

    $ruta = rutaDelCatalogo('Arena a mina')->load(['lineas.punto', 'variantes']);
    expect($ruta->topePara($ruta->varianteParaPeso(14)))->toBe(160.0)
        ->and(collect($ruta->lineasPara($ruta->varianteParaPeso(4)))->pluck('concepto')->all())->toBe(['Total', 'Viáticos']);
});

it('rejects a main route without stops and a row of a variant that does not exist', function (): void {
    actingAs(actorConRol('admin'))->post(route('rutas.store'), [
        'nombre' => 'Sin recorrido',
        'sentido' => 'ida',
        'es_complemento' => false,
        'paradas' => [],
        'variantes' => [],
        'lineas' => [['id' => null, 'variante' => 3, 'tipo' => 'combustible', 'concepto' => 'Total', 'galones' => 10]],
    ])->assertSessionHasErrors(['paradas', 'lineas.0.variante']);
});

it('deactivates a route already used by a ticket instead of deleting it', function (): void {
    $sacos = rutaDelCatalogo('Sacos a mina');
    RutaVuelta::factory()->create(['ruta_id' => $sacos->id]);

    actingAs(actorConRol('admin'))->delete(route('rutas.destroy', $sacos));
    expect($sacos->fresh()->activa)->toBeFalse();

    $nueva = Ruta::factory()->create();
    actingAs(actorConRol('admin'))->delete(route('rutas.destroy', $nueva));
    expect(Ruta::query()->whereKey($nueva->id)->exists())->toBeFalse();
});

it('offers the active routes and the destinations when programming', function (): void {
    rutaDelCatalogo('Vacía a Lima')->update(['activa' => false]);
    actingAs(actorConRol('admin'))->post(route('programacion.destinos.store'), ['lugar' => 'Camaná', 'zona' => 'arequipa']);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tramos.regreso', fn ($opciones): bool => collect($opciones)->contains('label', 'Sacos a mina')
                // Los complementos de cualquier tramo se ofrecen en los dos.
                && collect($opciones)->contains('label', 'Camaná (arroz)'))
            ->where('tramos.ida', fn ($opciones): bool => ! collect($opciones)->contains('label', 'Vacía a Lima')
                && (float) collect($opciones)->firstWhere('label', 'Concentrado a Pisco')['tope'] === 220.0)
            ->where('tramos.destinos', fn ($destinos): bool => collect($destinos)->contains('lugar', 'Camaná')));
});

it('keeps zones and destinations in the programming configuration', function (): void {
    actingAs(actorConRol('contador'))->get(route('programacion.configuracion'))->assertForbidden();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.configuracion'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/configuracion')
            ->missing('opciones')
            ->has('zonas', 8));
});

it('teaches a new place to the zones', function (): void {
    expect(ZonaRuta::desdeDireccion('AV. PRINCIPAL 100 - MAJES - CAYLLOMA - AREQUIPA'))->toBe(ZonaRuta::Arequipa)
        ->and(ZonaRuta::desdeDireccion('PLANTA ATICO - ATICO - CARAVELI - XX'))->toBeNull();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.palabras.store'), ['zona' => 'arequipa', 'palabra' => ' caraveli '])
        ->assertSessionHas('toast.type', 'success');

    expect(PalabraZona::query()->where('palabra', 'CARAVELI')->exists())->toBeTrue()
        ->and(ZonaRuta::desdeDireccion('PLANTA ATICO - ATICO - CARAVELI - XX'))->toBe(ZonaRuta::Arequipa);

    // Una palabra no puede reconocer dos zonas.
    actingAs(actorConRol('admin'))
        ->post(route('programacion.palabras.store'), ['zona' => 'lima', 'palabra' => 'CARAVELI'])
        ->assertSessionHasErrors('palabra');
});

it('loads the mine circuit from Juliaca: 220 split as the driver likes, 230 going up with slag', function (): void {
    $concentrado = rutaDelCatalogo('Concentrado a Pisco')->load('lineas.punto');
    $combustible = $concentrado->lineas->where('tipo', TipoLineaAbastecimiento::Combustible);
    $escoria = Ruta::query()->with('lineas.punto')->where('sube_con', TipoCarga::Escoria)->sole();

    expect($combustible)->toHaveCount(1)
        ->and($combustible->first()->punto_abastecimiento_id)->toBeNull()
        ->and($concentrado->topePara(null))->toBe(220.0)
        ->and($escoria->es_complemento)->toBeTrue()
        ->and($escoria->sentido)->toBe(SentidoTramo::Ida)
        ->and($escoria->topePara(null))->toBe(10.0)
        ->and(rutaDelCatalogo('Escoria a mina')->nota)->toContain('hasta Juliaca');
});

it('goes up to the mine with the slag of the previous ticket: same GR and 230 gallons', function (): void {
    $guiaDeEscoria = Viaje::factory()->create([
        'tracto_id' => $this->tracto->id,
        'tipo_carga' => TipoCarga::Escoria,
        'destino' => 'SAN RAFAEL - ANTAUTA - MELGAR - PUNO',
    ]);
    $ida = [
        ['sentido' => 'ida', 'zona_origen' => 'juliaca', 'zona_destino' => 'mina', 'tipo_carga' => 'escoria', 'viaje_continuado_id' => $guiaDeEscoria->id],
        ['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
    ];

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null],
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Sube con escoria')->id, 'variante_ruta_id' => null],
    ], $ida))->assertSessionHasNoErrors();

    $ticket = Vuelta::query()->sole();
    $subida = $ticket->paradasDe(SentidoTramo::Ida)->first();

    expect($ticket->topeDe(SentidoTramo::Ida))->toBe(230.0)
        ->and($subida->viaje_continuado_id)->toBe($guiaDeEscoria->id);

    // No espera una GR propia: muestra la que sigue.
    actingAs(actorConRol('admin'))
        ->get(route('vueltas.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.ida.0.continua.numero_gr', $guiaDeEscoria->numero_gr)
            ->where('ticket.ida.0.pendiente', false));
});

it('does not continue the GR of another unit', function (): void {
    $deOtra = Viaje::factory()->create(['tipo_carga' => TipoCarga::Escoria]);

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null],
    ], [
        ['sentido' => 'ida', 'zona_origen' => 'juliaca', 'zona_destino' => 'mina', 'tipo_carga' => 'escoria', 'viaje_continuado_id' => $deOtra->id],
        ['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
    ]))->assertSessionHasErrors('paradas.0.viaje_continuado_id');

    expect(Vuelta::query()->count())->toBe(0);
});

it('adds a far destination complement to a leg that already has fuel, without changing what it had', function (): void {
    $ida = [
        ['sentido' => 'ida', 'zona_origen' => 'juliaca', 'zona_destino' => 'mina', 'tipo_carga' => 'vacio'],
        ['sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
    ];
    $concentrado = ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Concentrado a Pisco')->id, 'variante_ruta_id' => null];
    $huaral = ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Ida y vuelta Huaral')->id, 'variante_ruta_id' => null];

    actingAs(actorConRol('admin'))->post(route('vueltas.store'), programar($this->tracto, [$concentrado], $ida))->assertSessionHasNoErrors();
    $ticket = Vuelta::query()->sole();
    Abastecimiento::factory()->paraVuelta($ticket)->create(['galones' => 150]);

    // Se supo después que va a Huaral: el complemento suma 20.
    actingAs(actorConRol('admin'))->put(route('vueltas.update', $ticket), programar($this->tracto, [$concentrado, $huaral], $ida))
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->topeDe(SentidoTramo::Ida))->toBe(240.0);

    // Lo que ya tenía no se quita ni se cambia.
    actingAs(actorConRol('admin'))->put(route('vueltas.update', $ticket), programar($this->tracto, [$concentrado], $ida))
        ->assertSessionHasErrors('rutas');
    actingAs(actorConRol('admin'))->put(route('vueltas.update', $ticket), programar($this->tracto, [
        ['sentido' => 'ida', 'ruta_id' => rutaDelCatalogo('Vacía a Lima')->id, 'variante_ruta_id' => null], $huaral,
    ], $ida))->assertSessionHasErrors('rutas');

    expect($ticket->fresh()->topeDe(SentidoTramo::Ida))->toBe(240.0);
});

it('marks a fuel line as full tank from the route editor', function (): void {
    $kio = PuntoAbastecimiento::query()->where('nombre', 'Kio')->sole();
    $datos = fn (bool $lleno, ?float $galones): array => [
        'nombre' => 'Particular a Puno',
        'sentido' => 'regreso',
        'es_complemento' => false,
        'activa' => true,
        'paradas' => [['zona_origen' => 'lima', 'zona_destino' => 'juliaca', 'tipo_carga' => 'particular']],
        'variantes' => [],
        'lineas' => [
            ['id' => null, 'variante' => null, 'tipo' => 'combustible', 'concepto' => 'Kio', 'punto_abastecimiento_id' => $kio->id, 'galones' => $galones, 'tanque_lleno' => $lleno],
            ['id' => null, 'variante' => null, 'tipo' => 'combustible', 'concepto' => 'Arequipa', 'galones' => 150],
        ],
    ];

    actingAs(actorConRol('admin'))->post(route('rutas.store'), $datos(true, 80))->assertSessionHasNoErrors();
    $ruta = rutaDelCatalogo('Particular a Puno')->load(['lineas.punto', 'variantes']);
    $full = $ruta->lineas->firstWhere('concepto', 'Kio');

    // El full no lleva galones y no suma: el tope es lo de Arequipa.
    expect($full->tanque_lleno)->toBeTrue()
        ->and($full->galones)->toBeNull()
        ->and($ruta->topePara(null))->toBe(150.0);
});
