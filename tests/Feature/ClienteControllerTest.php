<?php

use App\Enums\TipoCarga;
use App\Models\Cliente;
use App\Models\Viaje;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosCliente(array $overrides = []): array
{
    return array_merge([
        'ruc' => '20100136741',
        'razon_social' => 'MINSUR S.A.',
        'alias' => 'Minsur',
        'contacto' => 'Ana Torres',
        'telefono' => '999888777',
        'email' => 'despachos@minsur.com',
        'recurrente' => true,
        'activo' => true,
        'notas' => null,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('clientes.index'))->assertRedirect(route('login'));
});

it('lets admins and viewers see the list', function (): void {
    $cliente = Cliente::factory()->create();

    actingAs(actorConRol('visor'))
        ->get(route('clientes.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clientes/index')
            ->has('clientes.data', 1)
            ->where('clientes.data.0.alias', $cliente->alias)
        );
});

it('forbids drivers from the list', function (): void {
    actingAs(actorConRol('conductor'))
        ->get(route('clientes.index'))
        ->assertForbidden();
});

it('orders the list by how much each client moves', function (): void {
    $poco = Cliente::factory()->create(['alias' => 'Poco']);
    $mucho = Cliente::factory()->create(['alias' => 'Mucho']);

    Viaje::factory()->count(3)->create(['cliente_id' => $mucho->id]);
    Viaje::factory()->create(['cliente_id' => $poco->id]);

    actingAs(actorConRol('admin'))
        ->get(route('clientes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clientes.data.0.alias', 'Mucho')
            ->where('clientes.data.0.viajes_count', 3)
            ->where('clientes.data.1.alias', 'Poco')
        );
});

/**
 * La escala de la barra de proporción es el tope global y no el de la página,
 * para que una barra llena signifique lo mismo en la página 1 que en la 2.
 */
it('scales the bar against the busiest client of the whole padrón', function (): void {
    $mucho = Cliente::factory()->create(['alias' => 'Mucho']);
    $poco = Cliente::factory()->create(['alias' => 'Poco']);

    Viaje::factory()->count(4)->create(['cliente_id' => $mucho->id]);
    Viaje::factory()->create(['cliente_id' => $poco->id]);

    actingAs(actorConRol('admin'))
        ->get(route('clientes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('maxViajes', 4));
});

it('leaves the bar scale at zero when no trip is linked to the padrón', function (): void {
    Cliente::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('clientes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('maxViajes', 0));
});

it('finds a client by alias, razón social or RUC', function (): void {
    Cliente::factory()->create([
        'alias' => 'Minsur',
        'razon_social' => 'MINSUR S.A.',
        'ruc' => '20100136741',
    ]);
    Cliente::factory()->create(['alias' => 'Otro', 'ruc' => '20999999999']);

    foreach (['Minsur', 'MINSUR S.A.', '20100136741'] as $termino) {
        actingAs(actorConRol('admin'))
            ->get(route('clientes.index', ['buscar' => $termino]))
            ->assertInertia(fn (Assert $page) => $page->has('clientes.data', 1));
    }
});

it('lets an admin register a client', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('clientes.store'), datosCliente())
        ->assertRedirect(route('clientes.index'));

    $this->assertDatabaseHas('clientes', ['ruc' => '20100136741', 'alias' => 'Minsur']);
});

it('links the trips that were waiting for that RUC when the client is registered', function (): void {
    // La GR casi siempre entra antes de que alguien dé de alta al cliente.
    $esperando = Viaje::factory()->create([
        'cliente' => 'MINSUR S.A.',
        'cliente_ruc' => '20100136741',
        'cliente_id' => null,
    ]);
    $ajeno = Viaje::factory()->create([
        'cliente_ruc' => '20999999999',
        'cliente_id' => null,
    ]);

    actingAs(actorConRol('admin'))
        ->post(route('clientes.store'), datosCliente())
        ->assertSessionHasNoErrors();

    $cliente = Cliente::query()->sole();

    expect($esperando->fresh()->cliente_id)->toBe($cliente->id)
        ->and($ajeno->fresh()->cliente_id)->toBeNull();
});

it('forbids a viewer from registering a client', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('clientes.store'), datosCliente())
        ->assertForbidden();

    $this->assertDatabaseCount('clientes', 0);
});

it('rejects a duplicate RUC and a malformed one', function (): void {
    Cliente::factory()->create(['ruc' => '20100136741']);

    actingAs(actorConRol('admin'))
        ->post(route('clientes.store'), datosCliente())
        ->assertSessionHasErrors('ruc');

    actingAs(actorConRol('admin'))
        ->post(route('clientes.store'), datosCliente(['ruc' => '123']))
        ->assertSessionHasErrors('ruc');
});

it('keeps its own RUC when updating', function (): void {
    $cliente = Cliente::factory()->create(['ruc' => '20100136741']);

    actingAs(actorConRol('admin'))
        ->put(route('clientes.update', $cliente), datosCliente(['alias' => 'Minsur Perú']))
        ->assertSessionHasNoErrors();

    expect($cliente->fresh()->alias)->toBe('Minsur Perú');
});

it('shows the client ficha with its recent trips', function (): void {
    $cliente = Cliente::factory()->create();

    $viejo = Viaje::factory()->create([
        'cliente_id' => $cliente->id,
        'fecha_traslado' => '2026-07-01',
    ]);
    $nuevo = Viaje::factory()->create([
        'cliente_id' => $cliente->id,
        'fecha_traslado' => '2026-08-20',
    ]);
    Viaje::factory()->create(['cliente_id' => null]);

    actingAs(actorConRol('admin'))
        ->get(route('clientes.show', $cliente))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clientes/show')
            ->where('estadisticas.viajes_totales', 2)
            ->where('estadisticas.ultimo_viaje', '2026-08-20')
            ->where('estadisticas.primer_viaje', '2026-07-01')
            ->has('viajes', 2)
            ->where('viajes.0.id', $nuevo->id)
            ->where('viajes.1.id', $viejo->id)
        );
});

it('compares this month against the previous one in the ficha', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    $cliente = Cliente::factory()->create();

    // 2 en julio, 3 en agosto: +50%.
    Viaje::factory()->count(2)->create([
        'cliente_id' => $cliente->id,
        'fecha_traslado' => '2026-07-10',
    ]);
    Viaje::factory()->count(3)->create([
        'cliente_id' => $cliente->id,
        'fecha_traslado' => '2026-08-10',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('estadisticas.viajes_mes', 3)
            ->where('estadisticas.variacion_mes', 50)
        );
});

it('leaves the variation null when there is no previous month to compare against', function (): void {
    $this->travelTo('2026-08-20 12:00:00');

    $cliente = Cliente::factory()->create();
    Viaje::factory()->create([
        'cliente_id' => $cliente->id,
        'fecha_traslado' => '2026-08-10',
    ]);

    // Sin viajes en julio no hay contra qué comparar: «+100%» desde cero
    // diría más de lo que se sabe.
    actingAs(actorConRol('admin'))
        ->get(route('clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('estadisticas.variacion_mes', null)
            ->where('estadisticas.viajes_mes', 1)
        );
});

it('reports the most frequent route and the cargo mix in the ficha', function (): void {
    $cliente = Cliente::factory()->create();

    Viaje::factory()->count(2)->create([
        'cliente_id' => $cliente->id,
        'origen' => 'AV. INDUSTRIAL 100 - ANTAUTA - MELGAR - PUNO',
        'destino' => 'MUELLE 5 - PARACAS - PISCO - ICA',
        'tipo_carga' => TipoCarga::Concentrado,
    ]);
    Viaje::factory()->create([
        'cliente_id' => $cliente->id,
        'origen' => 'AV. LOS ALAMOS 20 - CALLAO - CALLAO - CALLAO',
        'destino' => 'JR. UNION 300 - JULIACA - SAN ROMAN - PUNO',
        'tipo_carga' => TipoCarga::Materiales,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('estadisticas.ruta_frecuente.origen', 'ANTAUTA')
            ->where('estadisticas.ruta_frecuente.destino', 'PARACAS')
            ->where('estadisticas.ruta_frecuente.viajes', 2)
            ->where('estadisticas.tipos_carga', 2)
            ->where('estadisticas.carga_principal', 'Concentrado')
        );
});

it('keeps the trips history when a client is deleted, only dropping the link', function (): void {
    $cliente = Cliente::factory()->create();
    $viaje = Viaje::factory()->create([
        'cliente' => 'MINSUR S.A.',
        'cliente_id' => $cliente->id,
    ]);

    actingAs(actorConRol('admin'))
        ->delete(route('clientes.destroy', $cliente))
        ->assertRedirect(route('clientes.index'));

    $viaje->refresh();

    expect($viaje->exists)->toBeTrue()
        ->and($viaje->cliente)->toBe('MINSUR S.A.')
        ->and($viaje->cliente_id)->toBeNull();
});

it('suggests a short alias by dropping the forma societaria', function (): void {
    expect(Cliente::aliasSugerido('PORCELANATO LATINO SOCIEDAD ANONIMA CERRADA'))->toBe('Porcelanato Latino')
        ->and(Cliente::aliasSugerido('MINSUR S.A.'))->toBe('Minsur')
        // Encadena dos formas: hay que recortar hasta que no quede ninguna.
        ->and(Cliente::aliasSugerido('GRUPO KAT MERCANTIL EMPRESA INDIVIDUAL DE RESPONSABILIDAD LTDA.'))->toBe('Grupo Kat Mercantil')
        // Persona natural: no hay forma societaria que quitar.
        ->and(Cliente::aliasSugerido('GUZMAN REVILLA CHRISTOPHER CHRISTIAN'))->toBe('Guzman Revilla Christopher Christian');
});
