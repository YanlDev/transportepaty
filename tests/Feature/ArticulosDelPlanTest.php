<?php

use App\Enums\UnidadMedida;
use App\Models\Articulo;
use App\Models\MovimientoAlmacen;
use App\Models\PlanMantenimiento;
use App\Models\RepuestoServicio;
use App\Services\ArticulosDelPlan;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * Cada ítem de un plan preventivo que sale del almacén tiene su artículo (en
 * stock cero) y su misma unidad. Operaciones ajusta el plan desde su editor:
 * si renombra o quita un ítem, su artículo lo sigue mientras no tenga
 * movimientos.
 */
beforeEach(function (): void {
    $this->scania = PlanMantenimiento::deMarca('SCANIA');
    $this->operaciones = actorConRol('operaciones');
});

/** El plan como lo manda el editor, con el artículo de cada ítem. */
function planDelEditor(PlanMantenimiento $plan, ?callable $cambiar = null): array
{
    $datos = [
        'nombre' => $plan->nombre,
        'servicios' => $plan->servicios()->with('repuestos')->get()
            ->map(fn ($servicio): array => [
                'codigo' => $servicio->codigo,
                'nombre' => $servicio->nombre,
                'intervalo_km' => $servicio->intervalo_km,
                'intervalo_meses' => $servicio->intervalo_meses,
                'repuestos' => $servicio->repuestos
                    ->map(fn ($repuesto): array => $repuesto->only(['descripcion', 'codigo', 'cantidad', 'unidad', 'articulo_id', 'es_servicio']))
                    ->all(),
            ])
            ->all(),
    ];

    return $cambiar === null ? $datos : $cambiar($datos);
}

it('gives every plan item its own article with zero stock and the same unit', function (): void {
    expect(Articulo::query()->count())->toBe(0);

    $creados = app(ArticulosDelPlan::class)->asegurar();
    $piezas = RepuestoServicio::query()->where('es_servicio', false)->with('articulo')->get();

    expect($creados)->toBeGreaterThan(60)
        // Todos los que salen del almacén quedaron ligados; los servicios, no.
        ->and($piezas->whereNull('articulo_id'))->toHaveCount(0)
        ->and(RepuestoServicio::query()->where('es_servicio', true)->whereNotNull('articulo_id')->count())->toBe(0)
        ->and((float) Articulo::query()->sum('stock'))->toBe(0.0)
        // El plan y el almacén hablan en la misma unidad.
        ->and($piezas->every(fn (RepuestoServicio $pieza): bool => $pieza->unidad === $pieza->articulo->unidad->value))->toBeTrue();

    $aceite = Articulo::query()->where('nombre', 'ACEITE DE MOTOR SCANIA')->sole();
    $filtroVolvo = Articulo::query()->where('nombre', 'FILTRO DE COMBUSTIBLE VOLVO')->sole();

    expect($aceite->unidad)->toBe(UnidadMedida::Litro)
        ->and($aceite->categoria?->nombre)->toBe('Lubricantes')
        // El de Scania no es el de Volvo: cada marca tiene el suyo.
        ->and(Articulo::query()->where('nombre', 'FILTRO DE COMBUSTIBLE SCANIA')->exists())->toBeTrue()
        ->and($filtroVolvo->marcas_compatibles)->toBe(['VOLVO'])
        // La nota del fabricante no va en el nombre del artículo.
        ->and(Articulo::query()->where('nombre', 'like', '%FABRICANTE%')->count())->toBe(0);

    // Volver a correrlo no duplica nada.
    expect(app(ArticulosDelPlan::class)->asegurar())->toBe(0);
});

it('lets operations open the plan editor with the warehouse units', function (): void {
    actingAs($this->operaciones)
        ->get(route('mantenimiento.planes.edit', $this->scania))
        ->assertInertia(fn (Assert $page) => $page
            ->component('mantenimiento/plan')
            ->where('unidades', fn ($unidades) => collect($unidades)->pluck('value')->contains('lt'))
            // Lo que estaba escrito «L» se muestra ya como la unidad del almacén.
            ->where('plan.servicios.1.repuestos.1.unidad', 'lt'));

    actingAs(actorConRol('mecanico'))->get(route('mantenimiento.planes.edit', $this->scania))->assertForbidden();
});

it('creates the article by itself when operations adds an item to the plan', function (): void {
    app(ArticulosDelPlan::class)->asegurar($this->scania);
    $antes = Articulo::query()->count();

    actingAs($this->operaciones)
        ->put(route('mantenimiento.planes.update', $this->scania), planDelEditor($this->scania, function (array $datos): array {
            $datos['servicios'][0]['repuestos'][] = ['descripcion' => 'Filtro de urea', 'codigo' => 'UR-123', 'cantidad' => 1, 'unidad' => 'und', 'articulo_id' => null, 'es_servicio' => false];

            return $datos;
        }))
        ->assertSessionHasNoErrors();

    $articulo = Articulo::query()->where('nombre', 'FILTRO DE UREA SCANIA')->sole();

    expect(Articulo::query()->count())->toBe($antes + 1)
        ->and($articulo->numero_parte)->toBe('UR-123')
        ->and((float) $articulo->stock)->toBe(0.0)
        ->and(RepuestoServicio::query()->where('descripcion', 'Filtro de urea')->sole()->articulo_id)->toBe($articulo->id);
});

it('renames and removes the article along with its item while it never moved', function (): void {
    app(ArticulosDelPlan::class)->asegurar($this->scania);
    $racor = Articulo::query()->where('nombre', 'FILTRO RACOR SCANIA')->sole();
    $grasa = Articulo::query()->where('nombre', 'GRASA SCANIA')->sole();

    actingAs($this->operaciones)
        ->put(route('mantenimiento.planes.update', $this->scania), planDelEditor($this->scania, function (array $datos) use ($racor, $grasa): array {
            foreach ($datos['servicios'] as &$servicio) {
                foreach ($servicio['repuestos'] as $i => &$repuesto) {
                    if ($repuesto['articulo_id'] === $racor->id) {
                        $repuesto['descripcion'] = 'Filtro separador de agua';
                    }

                    if ($repuesto['articulo_id'] === $grasa->id) {
                        unset($servicio['repuestos'][$i]);
                    }
                }
                $servicio['repuestos'] = array_values($servicio['repuestos']);
            }

            return $datos;
        }))
        ->assertSessionHasNoErrors();

    // El mismo artículo, con el nombre nuevo; el del ítem quitado ya no está.
    expect($racor->refresh()->nombre)->toBe('FILTRO SEPARADOR DE AGUA SCANIA')
        ->and(Articulo::query()->whereKey($grasa->id)->exists())->toBeFalse();
});

it('keeps an article that already has movements even if its item changes', function (): void {
    app(ArticulosDelPlan::class)->asegurar($this->scania);
    $racor = Articulo::query()->where('nombre', 'FILTRO RACOR SCANIA')->sole();
    MovimientoAlmacen::query()->create([
        'articulo_id' => $racor->id, 'fecha' => now()->toDateString(), 'tipo' => 'compra', 'documento' => 'F001-1',
        'entrada' => 2, 'salida' => 0, 'costo_unitario' => 50, 'costo_total' => 100,
        'origen_type' => 'prueba', 'origen_id' => 1,
    ]);

    actingAs($this->operaciones)
        ->put(route('mantenimiento.planes.update', $this->scania), planDelEditor($this->scania, function (array $datos) use ($racor): array {
            foreach ($datos['servicios'] as &$servicio) {
                $servicio['repuestos'] = array_values(array_filter($servicio['repuestos'], fn (array $repuesto): bool => $repuesto['articulo_id'] !== $racor->id));
            }

            return $datos;
        }))
        ->assertSessionHasNoErrors();

    // Ya es historia del kardex: se queda, con su nombre.
    expect($racor->refresh()->nombre)->toBe('FILTRO RACOR SCANIA');
});
