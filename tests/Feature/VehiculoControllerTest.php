<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function usuarioCon(string $rol): User
{
    return User::factory()->create()->assignRole($rol);
}

function datosVehiculo(array $overrides = []): array
{
    return array_merge([
        'placa' => 'ABC-123',
        'marca' => 'INTERNATIONAL',
        'modelo' => 'LT625 6X4',
        'anio' => 2023,
        'tipo' => TipoVehiculo::Tracto->value,
        'estado' => EstadoVehiculo::Activo->value,
        'caja' => TipoCaja::Mecanica->value,
        'vin' => null,
        'numero_motor' => null,
        'color' => 'BLANCO',
        'ejes' => 3,
        'peso_neto' => 8000,
        'peso_bruto' => 27000,
        'carga_util' => 19000,
        'fecha_adquisicion' => null,
        'observaciones' => null,
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('tractos.index'))->assertRedirect(route('login'));
});

it('lets authenticated users see the tractos list', function (): void {
    Vehiculo::factory()->count(3)->create();

    actingAs(usuarioCon('visor'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/index')
            ->where('seccion', 'tracto')
            ->has('vehiculos.data', 3)
        );
});

it('lets authenticated users see the carretas list', function (): void {
    Vehiculo::factory()->carreta()->count(2)->create();

    actingAs(usuarioCon('visor'))
        ->get(route('carretas.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/index')
            ->where('seccion', 'carreta')
            ->has('vehiculos.data', 2)
        );
});

it('keeps tractos and carretas apart in their own listing', function (): void {
    Vehiculo::factory()->create();
    Vehiculo::factory()->carreta()->create();

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.tipo', TipoVehiculo::Tracto->value)
        );

    actingAs(usuarioCon('admin'))
        ->get(route('carretas.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.tipo', TipoVehiculo::Carreta->value)
        );
});

it('filters the list by search term', function (): void {
    Vehiculo::factory()->create(['placa' => 'XYZ-999', 'marca' => 'VOLVO']);
    Vehiculo::factory()->create(['placa' => 'ABC-111', 'marca' => 'SCANIA']);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index', ['buscar' => 'XYZ']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});

it('filters tractos by marca', function (): void {
    Vehiculo::factory()->create(['marca' => 'VOLVO']);
    Vehiculo::factory()->create(['marca' => 'SCANIA']);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index', ['marca' => 'VOLVO']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.marca', 'VOLVO')
        );
});

it('offers only the marcas actually in use for that tipo', function (): void {
    Vehiculo::factory()->create(['marca' => 'VOLVO']);
    Vehiculo::factory()->carreta()->create(['marca' => 'RANDON']);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('marcas', 1)
            ->where('marcas.0.value', 'VOLVO')
        );
});

it('filters the list by caja', function (): void {
    Vehiculo::factory()->create(['caja' => TipoCaja::Mecanica]);
    Vehiculo::factory()->create(['caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->carreta()->create();

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index', ['caja' => TipoCaja::Mecanica->value]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.caja', TipoCaja::Mecanica->value)
        );
});

it('combines the marca and caja filters', function (): void {
    Vehiculo::factory()->create(['marca' => 'VOLVO', 'caja' => TipoCaja::Automatica]);
    Vehiculo::factory()->create(['marca' => 'VOLVO', 'caja' => TipoCaja::Mecanica]);
    Vehiculo::factory()->carreta()->create(['marca' => 'VOLVO']);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index', [
            'marca' => 'VOLVO',
            'caja' => TipoCaja::Automatica->value,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});

it('offers every gearbox option for tractos', function (): void {
    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('cajas', 2));
});

it('does not offer a caja filter for carretas', function (): void {
    actingAs(usuarioCon('admin'))
        ->get(route('carretas.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('cajas', 0));
});

it('gives every row in the list its own semáforo', function (): void {
    $alDia = Vehiculo::factory()->create(['placa' => 'AAA-111']);
    Vehiculo::factory()->create(['placa' => 'ZZZ-999']);

    foreach ($alDia->tipo->documentosObligatorios() as $tipo) {
        VehiculoDocumento::create([
            'vehiculo_id' => $alDia->id,
            'tipo' => $tipo,
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
        ]);
    }

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.data.0.documentacion.semaforo', 'verde')
            ->where('vehiculos.data.1.documentacion.semaforo', 'rojo')
            ->missing('vehiculos.data.0.modelo')
        );
});

it('leaves loose documents out of the semáforo', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    foreach ($vehiculo->tipo->documentosObligatorios() as $tipo) {
        VehiculoDocumento::create([
            'vehiculo_id' => $vehiculo->id,
            'tipo' => $tipo,
            'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
        ]);
    }

    // Un papel suelto ya vencido no debería ensuciar el semáforo.
    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Otro,
        'fecha_vencimiento' => now()->subYear()->format('Y-m-d'),
    ]);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.data.0.documentacion.semaforo', 'verde')
            ->where('vehiculos.data.0.documentacion.vencidos', [])
        );
});

it('loads the documents without an N+1 query', function (): void {
    $vehiculos = Vehiculo::factory()->count(5)->create();

    foreach ($vehiculos as $vehiculo) {
        VehiculoDocumento::create([
            'vehiculo_id' => $vehiculo->id,
            'tipo' => TipoDocumento::HabilitacionMtc,
            'numero' => "TUC-{$vehiculo->id}",
            'fecha_vencimiento' => '2028-02-05',
        ]);
    }

    DB::enableQueryLog();

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful();

    $consultasDocumentos = collect(DB::getQueryLog())
        ->filter(fn (array $consulta): bool => str_contains($consulta['query'], 'vehiculo_documentos'))
        ->count();

    DB::disableQueryLog();

    expect($consultasDocumentos)->toBe(1);
});

it('allows an admin to create a tracto', function (): void {
    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo())
        ->assertRedirect();

    $this->assertDatabaseHas('vehiculos', [
        'placa' => 'ABC-123',
        'tipo' => TipoVehiculo::Tracto->value,
        'caja' => TipoCaja::Mecanica->value,
    ]);
});

it('drops the caja when the vehicle is a carreta', function (): void {
    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo([
            'tipo' => TipoVehiculo::Carreta->value,
            'caja' => TipoCaja::Automatica->value,
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('vehiculos', [
        'placa' => 'ABC-123',
        'tipo' => TipoVehiculo::Carreta->value,
        'caja' => null,
    ]);
});

it('forbids a viewer from creating a vehicle', function (): void {
    actingAs(usuarioCon('visor'))
        ->post(route('vehiculos.store'), datosVehiculo())
        ->assertForbidden();

    $this->assertDatabaseCount('vehiculos', 0);
});

it('validates required fields when creating', function (): void {
    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo(['placa' => '']))
        ->assertSessionHasErrors('placa');
});

it('rejects a duplicate placa', function (): void {
    Vehiculo::factory()->create(['placa' => 'ABC-123']);

    actingAs(usuarioCon('admin'))
        ->post(route('vehiculos.store'), datosVehiculo(['placa' => 'ABC-123']))
        ->assertSessionHasErrors('placa');
});

it('allows an admin to update a vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('admin'))
        ->put(route('vehiculos.update', $vehiculo), datosVehiculo([
            'placa' => $vehiculo->placa,
            'estado' => EstadoVehiculo::EnMantenimiento->value,
        ]))
        ->assertRedirect();

    expect($vehiculo->refresh())
        ->estado->toBe(EstadoVehiculo::EnMantenimiento);
});

it('soft deletes a tracto and redirects to the tractos list', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('admin'))
        ->delete(route('vehiculos.destroy', $vehiculo))
        ->assertRedirect(route('tractos.index'));

    $this->assertSoftDeleted($vehiculo);
});

it('soft deletes a carreta and redirects to the carretas list', function (): void {
    $vehiculo = Vehiculo::factory()->carreta()->create();

    actingAs(usuarioCon('admin'))
        ->delete(route('vehiculos.destroy', $vehiculo))
        ->assertRedirect(route('carretas.index'));

    $this->assertSoftDeleted($vehiculo);
});

it('forbids a viewer from deleting a vehicle', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('visor'))
        ->delete(route('vehiculos.destroy', $vehiculo))
        ->assertForbidden();

    $this->assertNotSoftDeleted($vehiculo);
});

it('offers the soat only for tractos', function (): void {
    $tracto = Vehiculo::factory()->create();
    $carreta = Vehiculo::factory()->carreta()->create();
    $admin = usuarioCon('admin');

    actingAs($admin)
        ->get(route('vehiculos.show', $tracto))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/show')
            ->has('tiposDocumento', 6)
        );

    actingAs($admin)
        ->get(route('vehiculos.show', $carreta))
        ->assertInertia(fn (Assert $page) => $page->has('tiposDocumento', 5));
});

it('muestra el semáforo documental en el listado', function (): void {
    $vehiculo = Vehiculo::factory()->create();
    $vehiculo->documentos()->create([
        'tipo' => TipoDocumento::Soat,
        'fecha_vencimiento' => now()->subDay()->format('Y-m-d'),
    ]);

    actingAs(actorConRol('visor'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.data.0.documentacion.semaforo', 'rojo')
            ->where('vehiculos.data.0.documentacion.vencidos', ['SOAT'])
            ->has('vehiculos.data.0.documentacion.faltantes', 4)
        );
});

it('orders the list by placa', function (): void {
    Vehiculo::factory()->create(['placa' => 'ZZZ-999']);
    Vehiculo::factory()->create(['placa' => 'AAA-111']);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.data.0.placa', 'AAA-111')
            ->where('vehiculos.data.1.placa', 'ZZZ-999')
        );
});

it('keeps every obligatory document in a fixed slot on the detail page', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    // Solo el MATPEL cargado: los otros cuatro deben seguir apareciendo, cada
    // uno en su sitio, en vez de que el MATPEL se corra al primer lugar.
    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Matpel,
        'fecha_vencimiento' => now()->addYear()->format('Y-m-d'),
    ]);

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.show', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/show')
            ->has('ranuras', 5)
            ->where('ranuras.0.tipo', 'tarjeta_propiedad')
            ->where('ranuras.1.tipo', 'soat')
            ->where('ranuras.2.tipo', 'revision_tecnica_carga')
            ->where('ranuras.3.tipo', 'habilitacion_mtc')
            ->where('ranuras.4.tipo', 'matpel')
            ->where('ranuras.0.documento', null)
            ->where('ranuras.0.estado', 'faltante')
            ->where('ranuras.4.estado', 'vigente')
            ->where('ranuras.4.documento.tipo', 'matpel')
        );
});

it('does not offer a soat slot for a carreta', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create();

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.show', $carreta))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('ranuras', 4)
            ->where('ranuras.0.tipo', 'tarjeta_propiedad')
            ->where('ranuras.1.tipo', 'revision_tecnica_carga')
        );
});

it('puts loose documents after the obligatory slots', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Otro,
        'nombre' => 'Póliza de responsabilidad civil',
    ]);

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.show', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('ranuras', 6)
            ->where('ranuras.5.obligatorio', false)
            ->where('ranuras.5.label', 'Póliza de responsabilidad civil')
            ->where('ranuras.0.obligatorio', true)
        );
});

it('shares the same slots with the documents page', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    actingAs(usuarioCon('admin'))
        ->get(route('vehiculos.documentos.index', $vehiculo))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehiculos/documentos')
            ->has('ranuras', 5)
            ->where('ranuras.0.tipo', 'tarjeta_propiedad')
        );
});

it('exposes the TUC number so the list can copy it', function (): void {
    $conTuc = Vehiculo::factory()->create(['placa' => 'AAA-111']);
    Vehiculo::factory()->create(['placa' => 'BBB-222']);

    VehiculoDocumento::create([
        'vehiculo_id' => $conTuc->id,
        'tipo' => TipoDocumento::HabilitacionMtc,
        'numero' => '21M22000405E',
        'fecha_vencimiento' => '2028-02-05',
    ]);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.data.0.tuc_numero', '21M22000405E')
            ->where('vehiculos.data.1.tuc_numero', null)
        );
});

it('does not mistake another document for the TUC number', function (): void {
    $vehiculo = Vehiculo::factory()->create();

    VehiculoDocumento::create([
        'vehiculo_id' => $vehiculo->id,
        'tipo' => TipoDocumento::Soat,
        'numero' => 'SOAT-123',
        'fecha_vencimiento' => '2027-01-01',
    ]);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehiculos.data.0.tuc_numero', null)
        );
});

it('reads the TUC number without extra queries', function (): void {
    $vehiculos = Vehiculo::factory()->count(5)->create();

    foreach ($vehiculos as $vehiculo) {
        VehiculoDocumento::create([
            'vehiculo_id' => $vehiculo->id,
            'tipo' => TipoDocumento::HabilitacionMtc,
            'numero' => "TUC-{$vehiculo->id}",
            'fecha_vencimiento' => '2028-02-05',
        ]);
    }

    DB::enableQueryLog();

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index'))
        ->assertSuccessful();

    $consultas = collect(DB::getQueryLog())
        ->filter(fn (array $consulta): bool => str_contains($consulta['query'], 'vehiculo_documentos'))
        ->count();

    DB::disableQueryLog();

    expect($consultas)->toBe(1);
});

it('finds a placa searched without its hyphen', function (): void {
    Vehiculo::factory()->create(['placa' => 'BJF-934']);
    Vehiculo::factory()->create(['placa' => 'ZZZ-999']);

    // Lo que el usuario copia de la tabla viene sin guion.
    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index', ['buscar' => 'BJF934']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vehiculos.data', 1)
            ->where('vehiculos.data.0.placa', 'BJF-934')
        );
});

it('still finds a placa searched with its hyphen', function (): void {
    Vehiculo::factory()->create(['placa' => 'BJF-934']);
    Vehiculo::factory()->create(['placa' => 'ZZZ-999']);

    actingAs(usuarioCon('admin'))
        ->get(route('tractos.index', ['buscar' => 'BJF-934']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('vehiculos.data', 1));
});
