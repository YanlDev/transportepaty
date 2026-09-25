<?php

use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Programacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * Los cuatro campos de una programación, ya persistidos, listos para mandar
 * en un POST o un PUT.
 *
 * @return array<string, mixed>
 */
function datosDeProgramacion(array $extra = []): array
{
    return array_merge([
        'fecha' => '2026-09-15',
        'vehiculo_id' => Vehiculo::factory()->create()->id,
        'conductor_id' => Conductor::factory()->create()->id,
        'cliente_id' => Cliente::factory()->create()->id,
        'destino' => 'JULIACA',
    ], $extra);
}

it('redirects guests to login', function (): void {
    $this->get(route('programacion.index'))->assertRedirect(route('login'));
});

it('lets the admin and the visor read it, and keeps the rest out', function (): void {
    // Abastecimiento lee con rol visor: necesita ver el plan del día, no armarlo.
    actingAs(actorConRol('admin'))->get(route('programacion.index'))->assertSuccessful();
    actingAs(actorConRol('visor'))->get(route('programacion.index'))->assertSuccessful();

    actingAs(actorConRol('contador'))->get(route('programacion.index'))->assertForbidden();
    actingAs(actorConRol('conductor'))->get(route('programacion.index'))->assertForbidden();
});

it('only lets the admin program, edit and remove', function (): void {
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('visor'))
        ->post(route('programacion.store'), datosDeProgramacion())
        ->assertForbidden();

    actingAs(actorConRol('visor'))
        ->put(route('programacion.update', $programacion), datosDeProgramacion())
        ->assertForbidden();

    actingAs(actorConRol('visor'))
        ->delete(route('programacion.destroy', $programacion))
        ->assertForbidden();

    expect(Programacion::query()->count())->toBe(1);
});

it('shows today when no date is given', function (): void {
    $hoy = RelojOperativo::fechaDeHoy()->toDateString();

    Programacion::factory()->elDia($hoy)->create();
    Programacion::factory()->elDia('2020-01-01')->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where('fecha', $hoy)
            ->has('programaciones', 1)
        );
});

it('falls back to today when the date in the URL is garbage', function (): void {
    // Llega por query string: cualquier texto tiene que dar el día de hoy, no un 500.
    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => 'no-es-una-fecha']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('fecha', RelojOperativo::fechaDeHoy()->toDateString())
        );
});

it('only brings the units programmed for the day it is showing', function (): void {
    Programacion::factory()->elDia('2026-09-15')->count(3)->create();
    Programacion::factory()->elDia('2026-09-16')->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page->has('programaciones', 3));
});

it('groups the cards by client so the same client reads together', function (): void {
    $zeta = Cliente::factory()->create(['alias' => 'Zeta']);
    $alfa = Cliente::factory()->create(['alias' => 'Alfa']);

    Programacion::factory()->elDia('2026-09-15')->create(['cliente_id' => $zeta->id]);
    Programacion::factory()->elDia('2026-09-15')->create(['cliente_id' => $alfa->id]);
    Programacion::factory()->elDia('2026-09-15')->create(['cliente_id' => $zeta->id]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page
            ->where('programaciones.0.cliente', 'Alfa')
            ->where('programaciones.1.cliente', 'Zeta')
            ->where('programaciones.2.cliente', 'Zeta')
        );
});

it('counts the programmed units of each day of the week', function (): void {
    // 2026-09-15 es martes; la semana va del lunes 14 al domingo 20.
    Programacion::factory()->elDia('2026-09-14')->count(2)->create();
    Programacion::factory()->elDia('2026-09-15')->create();
    // Fuera de la semana: no debe contarse en ningún día de la tira.
    Programacion::factory()->elDia('2026-09-21')->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page
            ->has('semana', 7)
            ->where('semana.0.fecha', '2026-09-14')
            ->where('semana.0.programadas', 2)
            ->where('semana.1.programadas', 1)
            ->where('semana.2.programadas', 0)
            ->where('semana.6.fecha', '2026-09-20')
        );
});

it('programs a unit', function (): void {
    $datos = datosDeProgramacion();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), $datos)
        ->assertRedirect();

    $this->assertDatabaseHas('programaciones', [
        'fecha' => '2026-09-15',
        'vehiculo_id' => $datos['vehiculo_id'],
        'destino' => 'JULIACA',
    ]);
});

it('refuses a carreta as the unit: particular cargo moves on the tracto', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion(['vehiculo_id' => $carreta->id]))
        ->assertSessionHasErrors('vehiculo_id');

    expect(Programacion::query()->count())->toBe(0);
});

it('requires every field', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), [])
        ->assertSessionHasErrors(['fecha', 'vehiculo_id', 'conductor_id', 'cliente_id', 'destino']);
});

it('corrects a programmed unit', function (): void {
    $programacion = Programacion::factory()->create(['destino' => 'JULIACA']);
    $otroConductor = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('programacion.update', $programacion), datosDeProgramacion([
            'vehiculo_id' => $programacion->vehiculo_id,
            'conductor_id' => $otroConductor->id,
            'cliente_id' => $programacion->cliente_id,
            'destino' => 'ILAVE',
        ]))
        ->assertRedirect();

    expect($programacion->refresh()->destino)->toBe('ILAVE')
        ->and($programacion->conductor_id)->toBe($otroConductor->id);
});

it('removes a programmed unit', function (): void {
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('admin'))
        ->delete(route('programacion.destroy', $programacion))
        ->assertRedirect();

    $this->assertDatabaseMissing('programaciones', ['id' => $programacion->id]);
});

it('only offers active tractos and active conductores to program', function (): void {
    Vehiculo::factory()->create(['placa' => 'AAA111']);
    Vehiculo::factory()->carreta()->create();
    Vehiculo::factory()->enMantenimiento()->create();

    Conductor::factory()->create(['activo' => true]);
    Conductor::factory()->create(['activo' => false]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->has('unidades', 1)
            ->where('unidades.0.placa', 'AAA111')
            ->has('conductores', 1)
        );
});

it('offers the destinations already typed, so the same place is not written twice', function (): void {
    Programacion::factory()->create(['destino' => 'ILAVE']);
    Programacion::factory()->create(['destino' => 'ILAVE']);
    Programacion::factory()->create(['destino' => 'JULIACA']);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->has('destinosUsados', 2)
            ->where('destinosUsados.0', 'ILAVE')
            ->where('destinosUsados.1', 'JULIACA')
        );
});

/**
 * Lo que hace rápida la carga: elegir el conductor deja puesta la unidad con
 * la que salió la última vez, porque un chofer maneja casi siempre el mismo
 * tracto. El dato sale de las guías, no de programaciones anteriores.
 */
it('sends the unit of each conductor last GR, to prefill the form', function (): void {
    $conductor = Conductor::factory()->create();
    $viejo = Vehiculo::factory()->create(['placa' => 'VIEJO11']);
    $reciente = Vehiculo::factory()->create(['placa' => 'NUEVO22']);

    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => $viejo->id,
        'fecha_traslado' => '2026-08-01',
    ]);

    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => $reciente->id,
        'fecha_traslado' => '2026-09-05',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where("ultimoViajePorConductor.{$conductor->id}.vehiculo_id", $reciente->id)
            ->where("ultimoViajePorConductor.{$conductor->id}.placa", 'NUEVO22')
            ->where("ultimoViajePorConductor.{$conductor->id}.fecha", '2026-09-05')
        );
});

it('leaves out conductores with no GR to prefill from', function (): void {
    // Un chofer nuevo no tiene con qué prellenar: mejor nada que una unidad
    // inventada.
    $sinViajes = Conductor::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->missing("ultimoViajePorConductor.{$sinViajes->id}")
        );
});

it('ignores trips whose tracto could not be resolved', function (): void {
    $conductor = Conductor::factory()->create();
    $tracto = Vehiculo::factory()->create(['placa' => 'SIRVE11']);

    // La GR más reciente quedó sin tracto resuelto: debe caer a la anterior
    // que sí lo tiene, en vez de dejar al conductor sin prellenado.
    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => null,
        'fecha_traslado' => '2026-09-10',
    ]);

    Viaje::factory()->create([
        'conductor_id' => $conductor->id,
        'tracto_id' => $tracto->id,
        'fecha_traslado' => '2026-09-01',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where("ultimoViajePorConductor.{$conductor->id}.placa", 'SIRVE11')
        );
});

it('programs for a day other than the one being shown', function (): void {
    // El día de carga es un campo del formulario: se programa mirando hoy
    // para cargar mañana.
    actingAs(actorConRol('admin'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']));

    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion(['fecha' => '2026-09-20']))
        ->assertRedirect();

    $this->assertDatabaseHas('programaciones', ['fecha' => '2026-09-20']);
});

it('sends each client RUC, which is how the express form finds the one it just created', function (): void {
    Cliente::factory()->create(['alias' => 'Minsur', 'ruc' => '20100136741', 'activo' => true]);
    Cliente::factory()->create(['activo' => false]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->has('clientes', 1)
            ->where('clientes.0.alias', 'Minsur')
            ->where('clientes.0.ruc', '20100136741')
        );
});

/**
 * Como una pantalla de salidas: la unidad con GR de ese día ya salió; la que
 * no la tiene sigue programada mientras el día no pase, y después queda
 * marcada para revisar.
 */
it('tells each programmed unit whether it already left, from the GRs of the day', function (): void {
    $this->travelTo(Carbon::parse('2026-09-16 15:00:00', 'America/Lima'));

    $salio = Vehiculo::factory()->create(['placa' => 'SALIO11']);
    $noSalio = Vehiculo::factory()->create(['placa' => 'NOSAL22']);

    Programacion::factory()->elDia('2026-09-15')->create(['vehiculo_id' => $salio->id]);
    Programacion::factory()->elDia('2026-09-15')->create(['vehiculo_id' => $noSalio->id]);
    Programacion::factory()->elDia('2026-09-16')->create(['vehiculo_id' => $noSalio->id]);

    Viaje::factory()->create([
        'tracto_id' => $salio->id,
        'fecha_traslado' => '2026-09-15',
        'numero_gr' => 'T001-000123',
    ]);
    // La GR de otro día no cuenta para la programación del 15.
    Viaje::factory()->create([
        'tracto_id' => $noSalio->id,
        'fecha_traslado' => '2026-09-14',
    ]);

    actingAs(actorConRol('visor'))
        ->get(route('programacion.index', ['fecha' => '2026-09-15']))
        ->assertInertia(fn ($page) => $page
            ->where('programaciones', fn ($tarjetas): bool => collect($tarjetas)->pluck('estado', 'placa')->sortKeys()->all() === [
                'NOSAL22' => 'sin_gr',
                'SALIO11' => 'despachado',
            ])
            ->where('programaciones', fn ($tarjetas): bool => collect($tarjetas)->firstWhere('placa', 'SALIO11')['numero_gr'] === 'T001-000123')
        );

    actingAs(actorConRol('visor'))
        ->get(route('programacion.index', ['fecha' => '2026-09-16']))
        ->assertInertia(fn ($page) => $page
            ->where('programaciones.0.estado', 'programado')
            ->where('programaciones.0.numero_gr', null)
        );
});

/**
 * El preaviso es la constancia de que se le dijo al conductor que no avance
 * sin guía: una unidad que sale sin GR es multa de hasta 4 UIT.
 */
it('records who sent the preaviso and when', function (): void {
    $programacion = Programacion::factory()->create();
    $admin = actorConRol('admin');

    actingAs($admin)
        ->post(route('programacion.aviso', $programacion))
        ->assertRedirect();

    $programacion->refresh();

    expect($programacion->aviso_enviado_at)->not->toBeNull()
        ->and($programacion->aviso_enviado_por)->toBe($admin->id);
});

/**
 * El visor lee el tablero pero no arma el plan; avisar es parte de armarlo.
 */
it('only lets the admin send the preaviso', function (): void {
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('visor'))
        ->post(route('programacion.aviso', $programacion))
        ->assertForbidden();

    expect($programacion->fresh()->aviso_enviado_at)->toBeNull();
});

it('sends each card with its aviso, ready to open WhatsApp', function (): void {
    $conductor = Conductor::factory()->create([
        'nombres' => 'Juan',
        'apellidos' => 'Pérez',
        'telefono' => '963325022',
    ]);
    Programacion::factory()->create([
        'fecha' => RelojOperativo::hoy(),
        'conductor_id' => $conductor->id,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page->has('programaciones.0', fn ($tarjeta) => $tarjeta
            ->where('destinatarios', [['etiqueta' => 'Conductor', 'numero' => '51963325022']])
            ->where('aviso_enviado_at', null)
            ->where('aviso_enviado_por', null)
            ->where('mensaje_aviso', fn (string $mensaje): bool => str_contains($mensaje, 'No inicies el viaje sin documentación validada'))
            ->etc()
        ));
});

it('leaves the destinatarios empty for a conductor with no phone', function (): void {
    Programacion::factory()->create([
        'fecha' => RelojOperativo::hoy(),
        'conductor_id' => Conductor::factory()->create([
            'telefono' => null,
            'telefono_alterno' => null,
        ])->id,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page->where('programaciones.0.destinatarios', []));
});

/** El otro número al que avisar de esa salida, cargado al programar. */
it('saves the whatsapp adicional of a salida', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion([
            'whatsapp_adicional' => '944556677',
        ]))
        ->assertRedirect();

    expect(Programacion::query()->value('whatsapp_adicional'))->toBe('944556677');
});

it('programs a unit without a whatsapp adicional', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Programacion::query()->value('whatsapp_adicional'))->toBeNull();
});

it('sends the day summary for the operaciones number', function (): void {
    config(['transpaty.operaciones.whatsapp' => '998877665']);

    Programacion::factory()->create(['fecha' => RelojOperativo::hoy()]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where('avisoOperaciones.whatsapp', '51998877665')
            ->where('avisoOperaciones.mensaje', fn (string $mensaje): bool => str_contains($mensaje, 'SIN GR'))
            ->etc()
        );
});

it('has no operaciones number until one is configured', function (): void {
    config(['transpaty.operaciones.whatsapp' => null]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page->where('avisoOperaciones.whatsapp', null)->etc());
});

/** La advertencia es la misma para todas las salidas: viaja una sola vez. */
it('sends the advertencia once, not repeated in every card', function (): void {
    Programacion::factory()->create(['fecha' => RelojOperativo::hoy()]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page
            ->where('advertencia', fn (string $texto): bool => str_contains($texto, 'PROHIBIDO INICIAR EL VIAJE SIN DOCUMENTACIÓN VALIDADA'))
            ->missing('programaciones.0.advertencia')
            ->etc()
        );
});

/**
 * El celular equivocado se descubre justo cuando hay que mandar el aviso, así
 * que se corrige desde la misma fila.
 */
it('corrects the numbers from the programacion, each one where it belongs', function (): void {
    $conductor = Conductor::factory()->create([
        'telefono' => '963325022',
        'telefono_alterno' => null,
    ]);
    $programacion = Programacion::factory()->create(['conductor_id' => $conductor->id]);

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.numeros', $programacion), [
            'telefono' => '951112233',
            'telefono_alterno' => '944556677',
            'whatsapp_adicional' => '933445566',
        ])
        ->assertRedirect();

    expect($conductor->fresh())
        ->telefono->toBe('951112233')
        ->telefono_alterno->toBe('944556677')
        ->and($programacion->fresh()->whatsapp_adicional)->toBe('933445566');
});

it('removes a number when the field comes empty', function (): void {
    $conductor = Conductor::factory()->create(['telefono_alterno' => '944556677']);
    $programacion = Programacion::factory()->create([
        'conductor_id' => $conductor->id,
        'whatsapp_adicional' => '933445566',
    ]);

    actingAs(actorConRol('admin'))
        ->patch(route('programacion.numeros', $programacion), [
            'telefono' => $conductor->telefono,
            'telefono_alterno' => null,
            'whatsapp_adicional' => null,
        ]);

    expect($conductor->fresh()->telefono_alterno)->toBeNull()
        ->and($programacion->fresh()->whatsapp_adicional)->toBeNull();
});

it('only lets the admin correct the numbers', function (): void {
    $programacion = Programacion::factory()->create();

    actingAs(actorConRol('visor'))
        ->patch(route('programacion.numeros', $programacion), ['telefono' => '951112233'])
        ->assertForbidden();
});

it('sends each card with the numbers as they are stored, ready to edit', function (): void {
    $conductor = Conductor::factory()->create([
        'telefono' => '963325022',
        'telefono_alterno' => '951112233',
    ]);
    Programacion::factory()->create([
        'fecha' => RelojOperativo::hoy(),
        'conductor_id' => $conductor->id,
        'whatsapp_adicional' => '944556677',
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page->has('programaciones.0', fn ($tarjeta) => $tarjeta
            ->where('telefono', '963325022')
            ->where('telefono_alterno', '951112233')
            ->where('whatsapp_adicional', '944556677')
            ->etc()
        ));
});

/** El flete acordado: opcional, porque no siempre hay precio al programar. */
it('saves the precio del flete when there is one', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion([
            'precio_flete' => '1850.50',
            'precio_incluye_igv' => true,
        ]))
        ->assertRedirect();

    $programacion = Programacion::query()->firstOrFail();

    expect((float) $programacion->precio_flete)->toBe(1850.50)
        ->and($programacion->precio_incluye_igv)->toBeTrue();
});

it('takes the flete as agreed without IGV unless it is told otherwise', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion(['precio_flete' => '1850']))
        ->assertRedirect();

    expect(Programacion::query()->firstOrFail()->precio_incluye_igv)->toBeFalse();
});

it('programs a unit without a precio', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Programacion::query()->value('precio_flete'))->toBeNull();
});

it('refuses a negative precio', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('programacion.store'), datosDeProgramacion(['precio_flete' => '-100']))
        ->assertSessionHasErrors('precio_flete');
});

it('sends each card with the avisos for abastecimiento and facturacion', function (): void {
    config([
        'transpaty.areas.abastecimiento' => '950301881',
        'transpaty.areas.facturacion' => '950301882',
    ]);

    Programacion::factory()->create([
        'fecha' => RelojOperativo::hoy(),
        'precio_flete' => 1850,
    ]);

    actingAs(actorConRol('admin'))
        ->get(route('programacion.index'))
        ->assertInertia(fn ($page) => $page->has('programaciones.0', fn ($tarjeta) => $tarjeta
            ->where('precio_flete', fn (int|float $precio): bool => (float) $precio === 1850.0)
            ->has('avisos_area', 2)
            ->where('avisos_area.0.numero', '51950301881')
            ->where('avisos_area.1.mensaje', fn (string $mensaje): bool => str_contains($mensaje, 'Flete acordado'))
            ->etc()
        ));
});
