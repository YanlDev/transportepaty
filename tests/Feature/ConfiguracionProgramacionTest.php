<?php

use App\Enums\ZonaRuta;
use App\Models\DestinoParticular;
use App\Models\OpcionTramo;
use App\Models\PalabraZona;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Lo que antes estaba fijo en el código de la programación se configura
 * desde la app: opciones de tramo, destinos de la carga particular y las
 * palabras de cada zona. Viene cargado con lo de siempre.
 */
beforeEach(function (): void {
    foreach (['admin', 'contador'] as $rol) {
        Role::findOrCreate($rol, 'web');
    }
});

it('comes loaded with what was in the code', function (): void {
    expect(OpcionTramo::query()->where('sentido', 'ida')->pluck('nombre')->all())
        ->toContain('Concentrado a Pisco', 'Metálico al Callao', 'Vacía de Pisco a Lima')
        ->not->toContain('Concentrado + metálico al Callao')
        ->and(OpcionTramo::query()->where('pide_destino', true)->sole()->nombre)->toBe('Carga particular')
        ->and(DestinoParticular::query()->pluck('lugar')->all())->toContain('Tacna', 'Ilo', 'Cusco')
        ->and(PalabraZona::query()->where('palabra', 'ANTAUTA')->sole()->zona)->toBe(ZonaRuta::Mina);
});

it('makes the metallic to Callao its own outbound leg from Pisco', function (): void {
    expect(OpcionTramo::query()->where('nombre', 'Metálico al Callao')->sole()->paradas)
        ->toBe([['zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'metalico']]);
});

it('offers private cargo up to the mine on the way back', function (): void {
    expect(OpcionTramo::query()->where('nombre', 'Particular a mina')->sole())
        ->sentido->value->toBe('regreso')
        ->paradas->toBe([['zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'particular']]);
});

it('is configured only by whoever has the permission', function (): void {
    actingAs(actorConRol('contador'))->get(route('programacion.configuracion'))->assertForbidden();

    actingAs(actorConRol('admin'))
        ->get(route('programacion.configuracion'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('vueltas/configuracion')
            ->has('opciones', 10)
            ->has('zonas', 6));
});

it('teaches a new place to the zones', function (): void {
    expect(ZonaRuta::desdeDireccion('AV. PRINCIPAL 100 - MAJES - CAYLLOMA - AREQUIPA'))->toBe(ZonaRuta::Sur)
        ->and(ZonaRuta::desdeDireccion('PLANTA ATICO - ATICO - CARAVELI - XX'))->toBeNull();

    actingAs(actorConRol('admin'))
        ->post(route('programacion.palabras.store'), ['zona' => 'sur', 'palabra' => ' caraveli '])
        ->assertSessionHas('toast.type', 'success');

    expect(PalabraZona::query()->where('palabra', 'CARAVELI')->exists())->toBeTrue()
        ->and(ZonaRuta::desdeDireccion('PLANTA ATICO - ATICO - CARAVELI - XX'))->toBe(ZonaRuta::Sur);

    // Una palabra no puede reconocer dos zonas.
    actingAs(actorConRol('admin'))
        ->post(route('programacion.palabras.store'), ['zona' => 'lima', 'palabra' => 'CARAVELI'])
        ->assertSessionHasErrors('palabra');
});

it('offers the configured options and destinations when programming', function (): void {
    actingAs(actorConRol('admin'))->post(route('programacion.opciones.store'), [
        'nombre' => 'Sacos a mina',
        'descripcion' => 'Pisco → Mina',
        'sentido' => 'regreso',
        'activa' => true,
        'paradas' => [['zona_origen' => 'pisco', 'zona_destino' => 'mina', 'tipo_carga' => 'sacos_nuevos']],
    ])->assertSessionHas('toast.type', 'success');
    actingAs(actorConRol('admin'))->post(route('programacion.destinos.store'), ['lugar' => 'Camaná', 'zona' => 'sur']);
    OpcionTramo::query()->where('nombre', 'Vacía a Lima')->update(['activa' => false]);

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tramos.regreso', fn ($opciones): bool => collect($opciones)->contains('label', 'Sacos a mina'))
            ->where('tramos.ida', fn ($opciones): bool => ! collect($opciones)->contains('label', 'Vacía a Lima'))
            ->where('tramos.destinos', fn ($destinos): bool => collect($destinos)->contains('lugar', 'Camaná')));
});
