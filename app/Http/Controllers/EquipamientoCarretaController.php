<?php

namespace App\Http\Controllers;

use App\Enums\TipoVehiculo;
use App\Http\Requests\StoreConteoEquipamientoRequest;
use App\Http\Requests\UpdateItemsEquipamientoRequest;
use App\Models\ConteoEquipamiento;
use App\Models\DetalleConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\Vehiculo;
use App\Services\EstadoEquipamientoCarretas;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El equipamiento de las carretas —por ahora, sus fajas—, contado en base.
 * La lista dice a cuáles les falta algo; la ficha de cada carreta, qué lleva
 * y sus conteos anteriores.
 *
 * @phpstan-import-type EstadoCarreta from EstadoEquipamientoCarretas
 */
class EquipamientoCarretaController extends Controller
{
    public function __construct(private readonly EstadoEquipamientoCarretas $equipamiento) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ConteoEquipamiento::class);

        $carretas = Vehiculo::query()
            ->where('tipo', TipoVehiculo::Carreta)
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca', 'tipo']);

        $estados = $this->equipamiento->paraFlota($carretas);

        return Inertia::render('mantenimiento/equipamiento/index', [
            'carretas' => $carretas
                ->map(fn (Vehiculo $carreta): array => [
                    'id' => $carreta->id,
                    'placa' => $carreta->placa,
                    'marca' => $carreta->marca,
                    ...$this->estado($estados[$carreta->id]),
                ])
                ->sortBy([
                    fn (array $a, array $b): int => $a['prioridad'] <=> $b['prioridad'],
                    fn (array $a, array $b): int => $b['faltan'] <=> $a['faltan'],
                    fn (array $a, array $b): int => $a['placa'] <=> $b['placa'],
                ])
                ->values()
                ->all(),
            'catalogo' => $this->catalogo(),
        ]);
    }

    public function show(Vehiculo $vehiculo): Response
    {
        $this->authorize('viewAny', ConteoEquipamiento::class);

        abort_unless($vehiculo->tipo === TipoVehiculo::Carreta, 404);

        return Inertia::render('mantenimiento/equipamiento/show', [
            'carreta' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                ...$this->estado($this->equipamiento->paraVehiculo($vehiculo)),
            ],
            'conteos' => ConteoEquipamiento::query()
                ->where('vehiculo_id', $vehiculo->id)
                ->with(['detalles.item:id,nombre', 'user:id,name'])
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (ConteoEquipamiento $conteo): array => [
                    'id' => $conteo->id,
                    'fecha' => $conteo->fecha->toDateString(),
                    'observacion' => $conteo->observacion,
                    'usuario' => $conteo->user?->name,
                    'detalles' => $conteo->detalles
                        ->map(fn (DetalleConteoEquipamiento $detalle): array => [
                            'item' => $detalle->item->nombre,
                            'buenas' => $detalle->buenas,
                            'danadas' => $detalle->danadas,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
            'hoy' => RelojOperativo::hoy(),
        ]);
    }

    public function store(StoreConteoEquipamientoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $datos = $request->validated();

        DB::transaction(function () use ($request, $vehiculo, $datos): void {
            $conteo = ConteoEquipamiento::create([
                'vehiculo_id' => $vehiculo->id,
                'fecha' => $datos['fecha'],
                'observacion' => $datos['observacion'] ?? null,
                'user_id' => $request->user()?->id,
            ]);

            foreach ($datos['items'] as $item) {
                $conteo->detalles()->create([
                    'item_equipamiento_id' => $item['id'],
                    'buenas' => $item['buenas'],
                    'danadas' => $item['danadas'],
                ]);
            }
        });

        $estado = $this->equipamiento->paraVehiculo($vehiculo);

        return back()->with('toast', [
            'type' => $estado['faltan'] > 0 ? 'warning' : 'success',
            'message' => "Conteo de {$vehiculo->placa} registrado."
                .($estado['faltan'] > 0 ? " Le faltan {$estado['faltan']}." : ''),
        ]);
    }

    public function destroy(ConteoEquipamiento $conteo): RedirectResponse
    {
        $this->authorize('delete', $conteo);

        $conteo->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Conteo eliminado: la carreta vuelve a su conteo anterior.',
        ]);
    }

    /**
     * El catálogo con sus mínimos. Un ítem quitado deja de contarse, pero no
     * se borra: sus conteos anteriores lo siguen nombrando.
     */
    public function actualizarCatalogo(UpdateItemsEquipamientoRequest $request): RedirectResponse
    {
        $this->authorize('configurar', ConteoEquipamiento::class);

        $items = $request->items();

        DB::transaction(function () use ($items): void {
            $quedan = [];

            foreach ($items as $orden => $datos) {
                $item = ItemEquipamiento::query()->updateOrCreate(
                    ['id' => $datos['id'] ?? null],
                    [
                        'nombre' => $datos['nombre'],
                        'minimo' => $datos['minimo'] ?? null,
                        'orden' => $orden + 1,
                        'activo' => true,
                    ],
                );

                $quedan[] = $item->id;
            }

            ItemEquipamiento::query()->whereNotIn('id', $quedan)->update(['activo' => false]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Equipamiento y mínimos guardados.']);
    }

    /**
     * @param  EstadoCarreta  $estado
     * @return array<string, mixed>
     */
    private function estado(array $estado): array
    {
        return [
            'conteo_fecha' => $estado['conteo']['fecha'] ?? null,
            'items' => $estado['items'],
            'faltan' => $estado['faltan'],
            'estado' => $estado['estado']->value,
            'estado_label' => $estado['estado']->label(),
            'prioridad' => $estado['estado']->prioridad(),
        ];
    }

    /**
     * @return list<array{id: int, nombre: string, minimo: int|null}>
     */
    private function catalogo(): array
    {
        return array_values(ItemEquipamiento::query()
            ->vigentes()
            ->get()
            ->map(fn (ItemEquipamiento $item): array => [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'minimo' => $item->minimo,
            ])
            ->all());
    }
}
