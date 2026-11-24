<?php

use App\Enums\AreaGasto;
use App\Enums\EstadoValeCompra;
use App\Enums\TipoAlerta;
use App\Models\CategoriaGasto;
use App\Models\User;
use App\Models\ValeGasto;
use App\Models\Vehiculo;
use App\Notifications\Alerta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * Los vales de gasto: cualquier área pide plata para algo que no pasa por el
 * almacén, diciendo su área, la categoría y para qué; gerencia aprueba todo.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');

    $this->operaciones = actorConRol('operaciones');
    $this->rrhh = actorConRol('rrhh');
    $this->gerencia = actorConRol('gerencia');
    $this->contador = actorConRol('contador');
    $this->peajes = CategoriaGasto::query()->where('nombre', 'Peajes')->firstOrFail();
});

/** Un vale de S/ 85 en peajes, pedido por quien se indique. */
function valePedidoPor(User $quien, array $extra = []): ValeGasto
{
    actingAs($quien)
        ->post(route('gastos.store'), [
            'categoria_gasto_id' => CategoriaGasto::query()->where('nombre', 'Peajes')->value('id'),
            'concepto' => 'Peajes de la ruta a Arequipa',
            'motivo' => 'El conductor salió sin saldo en el telepeaje.',
            'monto' => 85,
            'beneficiario' => 'Conductor Juan Pérez',
            ...$extra,
        ])
        ->assertSessionHasNoErrors();

    return ValeGasto::query()->latest('id')->firstOrFail();
}

it('lets every area request a vale, tagged with its area', function (string $rol, AreaGasto $area): void {
    $vale = valePedidoPor(actorConRol($rol));

    expect($vale->area)->toBe($area)
        ->and($vale->estado)->toBe(EstadoValeCompra::Solicitado)
        ->and($vale->categoria->nombre)->toBe('Peajes')
        ->and((float) $vale->monto)->toBe(85.0)
        ->and($vale->numero())->toStartWith('VG-');
})->with([
    'operaciones' => ['operaciones', AreaGasto::Operaciones],
    'taller' => ['taller', AreaGasto::Taller],
    'abastecimiento' => ['abastecimiento', AreaGasto::Abastecimiento],
    'contabilidad' => ['contador', AreaGasto::Contabilidad],
    'rrhh' => ['rrhh', AreaGasto::Rrhh],
    'almacén' => ['almacen', AreaGasto::Almacen],
]);

it('keeps the unit, a corrected area and the supporting files', function (): void {
    $unidad = Vehiculo::factory()->create();

    $vale = valePedidoPor($this->operaciones, [
        'area' => AreaGasto::Taller->value,
        'vehiculo_id' => $unidad->id,
        'sustento' => [UploadedFile::fake()->image('cotizacion.jpg', 600, 800)],
    ]);

    expect($vale->area)->toBe(AreaGasto::Taller)
        ->and($vale->vehiculo_id)->toBe($unidad->id)
        ->and($vale->getMedia('sustento'))->toHaveCount(1);

    $archivo = $vale->getFirstMedia('sustento');

    actingAs($this->operaciones)->get(route('gastos.archivo', [$vale, $archivo]))->assertSuccessful();
    actingAs($this->rrhh)->get(route('gastos.archivo', [$vale, $archivo]))->assertForbidden();
});

it('requires what the vale is for', function (): void {
    actingAs($this->operaciones)
        ->post(route('gastos.store'), ['monto' => 0])
        ->assertSessionHasErrors(['categoria_gasto_id', 'concepto', 'motivo', 'monto', 'beneficiario']);
});

it('does not offer an inactive category', function (): void {
    $inactiva = CategoriaGasto::factory()->create(['activa' => false]);

    actingAs($this->operaciones)
        ->post(route('gastos.store'), [
            'categoria_gasto_id' => $inactiva->id,
            'concepto' => 'Algo',
            'motivo' => 'Para probar',
            'monto' => 10,
            'beneficiario' => 'Alguien',
        ])
        ->assertSessionHasErrors('categoria_gasto_id');
});

it('shows each requester only their own vales, and everything to gerencia and contabilidad', function (): void {
    $deOperaciones = valePedidoPor($this->operaciones);
    $deRrhh = valePedidoPor($this->rrhh);

    actingAs($this->operaciones)->get(route('gastos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gastos/index')
            ->has('vales.data', 1)
            ->where('vales.data.0.id', $deOperaciones->id)
            ->where('veTodos', false)
            ->where('todasLasCategorias', null));

    actingAs($this->operaciones)->get(route('gastos.show', $deRrhh))->assertForbidden();

    foreach ([$this->gerencia, $this->contador] as $quien) {
        actingAs($quien)->get(route('gastos.index'))
            ->assertInertia(fn (Assert $page) => $page->has('vales.data', 2)->where('veTodos', true)->where('porAprobar', 2));
        actingAs($quien)->get(route('gastos.show', $deRrhh))->assertSuccessful();
    }

    actingAs($this->gerencia)->get(route('gastos.index', ['area' => 'rrhh']))
        ->assertInertia(fn (Assert $page) => $page->has('vales.data', 1)->where('vales.data.0.id', $deRrhh->id));
});

it('keeps the mechanic out', function (): void {
    actingAs(actorConRol('mecanico'))->get(route('gastos.index'))->assertForbidden();
});

it('tells gerencia there is a vale to approve and the requester when it is resolved', function (): void {
    Notification::fake();

    $vale = valePedidoPor($this->operaciones);

    Notification::assertSentTo(
        $this->gerencia,
        Alerta::class,
        fn (Alerta $alerta): bool => $alerta->tipo === TipoAlerta::ValeGastoPorAprobar
            && str_contains($alerta->titulo, $vale->numero())
            && str_contains($alerta->titulo, '85.00')
            && $alerta->url === route('gastos.show', $vale),
    );
    Notification::assertNotSentTo($this->operaciones, Alerta::class);

    actingAs($this->gerencia)->post(route('gastos.aprobar', $vale))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->operaciones, Alerta::class, fn (Alerta $alerta): bool => $alerta->tipo === TipoAlerta::ValeGastoResuelto);
    // Solo a quien lo pidió, no a toda su área ni a las demás.
    Notification::assertNotSentTo($this->rrhh, Alerta::class);
});

it('only lets gerencia approve, and never the requester', function (): void {
    $vale = valePedidoPor($this->operaciones);

    actingAs($this->operaciones)->post(route('gastos.aprobar', $vale))->assertForbidden();
    actingAs($this->contador)->post(route('gastos.aprobar', $vale))->assertForbidden();

    actingAs($this->gerencia)->post(route('gastos.aprobar', $vale))->assertSessionHasNoErrors();

    $vale->refresh();

    expect($vale->estado)->toBe(EstadoValeCompra::Aprobado)
        ->and($vale->aprobado_por)->toBe($this->gerencia->id)
        ->and($vale->aprobado_at)->not->toBeNull();

    // Ya resuelto no se vuelve a aprobar ni se rechaza.
    actingAs($this->gerencia)->post(route('gastos.rechazar', $vale), ['motivo' => 'Ya no va'])->assertForbidden();

    // El admin pide y también aprueba, pero no lo suyo.
    $admin = actorConRol('admin');
    $propio = valePedidoPor($admin);

    actingAs($admin)->post(route('gastos.aprobar', $propio))->assertSessionHasErrors('aprobacion');
    expect($propio->refresh()->estado)->toBe(EstadoValeCompra::Solicitado);
});

it('rejects with a reason', function (): void {
    $vale = valePedidoPor($this->operaciones);

    actingAs($this->gerencia)->post(route('gastos.rechazar', $vale), ['motivo' => ''])->assertSessionHasErrors('motivo');

    actingAs($this->gerencia)->post(route('gastos.rechazar', $vale), ['motivo' => 'Ese peaje lo cubre el viático.'])
        ->assertSessionHasNoErrors();

    $vale->refresh();

    expect($vale->estado)->toBe(EstadoValeCompra::Rechazado)
        ->and($vale->motivo_rechazo)->toBe('Ese peaje lo cubre el viático.')
        ->and($vale->rechazado_por)->toBe($this->gerencia->id);
});

it('lets the requester withdraw a pending vale, and only gerencia annul an approved one', function (): void {
    $pendiente = valePedidoPor($this->operaciones);

    actingAs($this->rrhh)->post(route('gastos.anular', $pendiente), ['motivo' => 'No es mío'])->assertForbidden();
    actingAs($this->operaciones)->post(route('gastos.anular', $pendiente), ['motivo' => 'Me equivoqué de monto'])
        ->assertSessionHasNoErrors();

    expect($pendiente->refresh()->estaAnulado())->toBeTrue();

    // Anulado ya no se aprueba.
    actingAs($this->gerencia)->post(route('gastos.aprobar', $pendiente))->assertForbidden();

    $aprobado = ValeGasto::factory()->aprobado()->create(['solicitado_por' => $this->operaciones->id]);

    actingAs($this->operaciones)->post(route('gastos.anular', $aprobado), ['motivo' => 'Ya no hace falta'])->assertForbidden();
    actingAs($this->gerencia)->post(route('gastos.anular', $aprobado), ['motivo' => 'Ya no hace falta'])->assertSessionHasNoErrors();

    expect($aprobado->refresh()->motivo_anulacion)->toBe('Ya no hace falta');
});

it('lets only gerencia manage the categories', function (): void {
    actingAs($this->contador)->post(route('gastos.categorias.store'), ['nombre' => 'Capacitaciones'])->assertForbidden();

    actingAs($this->gerencia)->post(route('gastos.categorias.store'), ['nombre' => 'Capacitaciones'])->assertSessionHasNoErrors();
    actingAs($this->gerencia)->post(route('gastos.categorias.store'), ['nombre' => 'Peajes'])->assertSessionHasErrors('nombre');

    // Desactivarla la saca del formulario, pero sigue en la lista de gerencia.
    actingAs($this->gerencia)->put(route('gastos.categorias.update', $this->peajes), ['nombre' => 'Peajes y telepeaje', 'activa' => false])
        ->assertSessionHasNoErrors();

    expect($this->peajes->refresh())->nombre->toBe('Peajes y telepeaje')->activa->toBeFalse();

    actingAs($this->gerencia)->get(route('gastos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categorias', fn ($categorias) => collect($categorias)->pluck('nombre')->doesntContain('Peajes y telepeaje'))
            ->where('todasLasCategorias', fn ($categorias) => collect($categorias)->pluck('nombre')->contains('Peajes y telepeaje')));
});
