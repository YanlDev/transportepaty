<?php

namespace App\Http\Controllers;

use App\Enums\AplicaEquipamiento;
use App\Enums\TipoMovimientoEquipamiento;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\StoreConteoEquipamientoRequest;
use App\Http\Requests\StoreMovimientoEquipamientoRequest;
use App\Http\Requests\UpdateItemsEquipamientoRequest;
use App\Models\ConteoEquipamiento;
use App\Models\DetalleConteoEquipamiento;
use App\Models\ItemEquipamiento;
use App\Models\MovimientoEquipamiento;
use App\Models\Vehiculo;
use App\Models\VencimientoEquipamiento;
use App\Services\ArticulosDeEquipamiento;
use App\Services\EquipamientoDeUnidades;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El equipamiento de las unidades: las fajas de las carretas; el extintor, el
 * botiquín, los conos y las herramientas de los tractos.
 *
 * Lo que una unidad tiene lo dice `EquipamientoDeUnidades`: su saldo inicial,
 * lo que salió del almacén con vale a su nombre, lo que llegó o se fue en un
 * traslado y lo que se dio de baja. Acá se cuenta en base, se pasa de una
 * unidad a otra y se da de baja.
 *
 * @phpstan-import-type EstadoUnidadEquipada from EquipamientoDeUnidades
 */
class EquipamientoCarretaController extends Controller
{
    public function __construct(private readonly EquipamientoDeUnidades $equipamiento) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ConteoEquipamiento::class);

        $unidades = Vehiculo::query()
            ->orderBy('tipo')
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca', 'tipo']);

        $estados = $this->equipamiento->paraFlota($unidades);

        return Inertia::render('mantenimiento/equipamiento/index', [
            'carretas' => $unidades
                // Una unidad sin ningún ítem que le aplique no tiene qué mostrar.
                ->filter(fn (Vehiculo $unidad): bool => $estados[$unidad->id]['items'] !== [])
                ->map(fn (Vehiculo $unidad): array => [
                    'id' => $unidad->id,
                    'placa' => $unidad->placa,
                    'marca' => $unidad->marca,
                    'tipo' => $unidad->tipo->value,
                    ...$this->estado($estados[$unidad->id]),
                ])
                ->sortBy([
                    fn (array $a, array $b): int => $a['prioridad'] <=> $b['prioridad'],
                    fn (array $a, array $b): int => $b['faltan'] <=> $a['faltan'],
                    fn (array $a, array $b): int => $a['placa'] <=> $b['placa'],
                ])
                ->values()
                ->all(),
            'catalogo' => $this->catalogo(),
            'aplicaciones' => AplicaEquipamiento::options(),
        ]);
    }

    public function show(Request $request, Vehiculo $vehiculo): Response
    {
        $this->authorize('viewAny', ConteoEquipamiento::class);

        $estado = $this->equipamiento->paraVehiculo($vehiculo);

        abort_if($estado['items'] === [], 404);

        return Inertia::render('mantenimiento/equipamiento/show', [
            'carreta' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'tipo' => $vehiculo->tipo->value,
                ...$this->estado($estado),
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
            // Saldos iniciales, traslados (los que salen y los que llegan) y bajas.
            'movimientos' => MovimientoEquipamiento::query()
                ->where(fn ($query) => $query->where('vehiculo_id', $vehiculo->id)->orWhere('vehiculo_destino_id', $vehiculo->id))
                ->with(['item:id,nombre', 'vehiculo:id,placa', 'destino:id,placa', 'user:id,name'])
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(60)
                ->get()
                ->map(fn (MovimientoEquipamiento $movimiento): array => [
                    'id' => $movimiento->id,
                    'tipo' => $movimiento->tipo->value,
                    'tipo_label' => $movimiento->tipo->label(),
                    'item' => $movimiento->item->nombre,
                    'cantidad' => $movimiento->cantidad,
                    'fecha' => $movimiento->fecha->toDateString(),
                    // En un traslado: si esta unidad lo recibió o lo entregó.
                    'entra' => $movimiento->tipo === TipoMovimientoEquipamiento::SaldoInicial
                        || ($movimiento->tipo === TipoMovimientoEquipamiento::Traslado && $movimiento->vehiculo_destino_id === $vehiculo->id),
                    'otra_placa' => $movimiento->tipo !== TipoMovimientoEquipamiento::Traslado
                        ? null
                        : ($movimiento->vehiculo_destino_id === $vehiculo->id ? $movimiento->vehiculo->placa : $movimiento->destino?->placa),
                    'motivo' => $movimiento->motivo,
                    'usuario' => $movimiento->user?->name,
                    'anulado' => $movimiento->estaAnulado(),
                    'motivo_anulacion' => $movimiento->motivo_anulacion,
                ])
                ->all(),
            'unidades' => Vehiculo::query()
                ->whereKeyNot($vehiculo->id)
                ->orderBy('placa')
                ->get(['id', 'placa', 'tipo'])
                ->map(fn (Vehiculo $unidad): array => ['id' => $unidad->id, 'placa' => $unidad->placa, 'tipo' => $unidad->tipo->value])
                ->all(),
            'puedeAnular' => $request->user()?->can('anularMovimiento', ConteoEquipamiento::class) ?? false,
            'hoy' => RelojOperativo::hoy(),
        ]);
    }

    public function store(StoreConteoEquipamientoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $datos = $request->validated();
        $filas = collect($this->equipamiento->paraVehiculo($vehiculo)['items'])->keyBy('id');

        DB::transaction(function () use ($request, $vehiculo, $datos, $filas): void {
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

                // Lo que la unidad ya tenía antes de que el almacén llevara
                // la cuenta: su primer conteo es su saldo inicial.
                $contado = (int) $item['buenas'] + (int) $item['danadas'];

                if (! $filas[(int) $item['id']]['con_historial'] && $contado > 0) {
                    MovimientoEquipamiento::create([
                        'vehiculo_id' => $vehiculo->id,
                        'item_equipamiento_id' => $item['id'],
                        'tipo' => TipoMovimientoEquipamiento::SaldoInicial,
                        'cantidad' => $contado,
                        'fecha' => $datos['fecha'],
                        'conteo_equipamiento_id' => $conteo->id,
                        'user_id' => $request->user()?->id,
                    ]);
                }
            }
        });

        $estado = $this->equipamiento->paraVehiculo($vehiculo);

        return back()->with('toast', [
            'type' => $estado['faltan'] > 0 ? 'warning' : 'success',
            'message' => "Conteo de {$vehiculo->placa} registrado."
                .($estado['faltan'] > 0 ? " Le faltan {$estado['faltan']}." : ''),
        ]);
    }

    /**
     * Un conteo mal digitado. Si fue el que fijó el saldo inicial y después
     * ya hubo traslados o bajas, no se elimina: esos movimientos salieron de
     * ese saldo.
     */
    public function destroy(ConteoEquipamiento $conteo): RedirectResponse
    {
        $this->authorize('delete', $conteo);

        $iniciales = MovimientoEquipamiento::query()->where('conteo_equipamiento_id', $conteo->id)->pluck('item_equipamiento_id');

        $huboDespues = $iniciales->isNotEmpty() && MovimientoEquipamiento::query()
            ->vigentes()
            ->where('vehiculo_id', $conteo->vehiculo_id)
            ->whereIn('item_equipamiento_id', $iniciales)
            ->where('tipo', '!=', TipoMovimientoEquipamiento::SaldoInicial)
            ->exists();

        if ($huboDespues) {
            throw ValidationException::withMessages([
                'conteo' => 'Este conteo fijó el saldo inicial de la unidad y después hubo traslados o bajas: anula primero esos movimientos.',
            ]);
        }

        $conteo->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Conteo eliminado: la unidad vuelve a su conteo anterior.',
        ]);
    }

    /**
     * Lo que pasa de una unidad a otra sin volver al almacén. Queda en el
     * historial de las dos.
     */
    public function trasladar(StoreMovimientoEquipamientoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $movimiento = $this->registrar($request, $vehiculo, TipoMovimientoEquipamiento::Traslado);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$movimiento->cantidad} de {$movimiento->item->nombre} pasaron a {$movimiento->destino?->placa}.",
        ]);
    }

    /**
     * Lo que ya no sirve: sale de lo asignado a la unidad, con su motivo.
     */
    public function darDeBaja(StoreMovimientoEquipamientoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $movimiento = $this->registrar($request, $vehiculo, TipoMovimientoEquipamiento::Baja);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$movimiento->cantidad} de {$movimiento->item->nombre} dadas de baja en {$vehiculo->placa}.",
        ]);
    }

    /**
     * Un traslado o una baja mal registrados no se borran: se anulan. Si con
     * eso alguna unidad quedara debiendo (ya trasladó lo que recibió), no se
     * puede.
     */
    public function anularMovimiento(AnularDocumentoRequest $request, MovimientoEquipamiento $movimiento): RedirectResponse
    {
        $this->authorize('anularMovimiento', ConteoEquipamiento::class);

        abort_if($movimiento->estaAnulado() || $movimiento->tipo === TipoMovimientoEquipamiento::SaldoInicial, 404);

        if ($movimiento->tipo === TipoMovimientoEquipamiento::Traslado && $movimiento->destino !== null
            && $this->equipamiento->asignado($movimiento->destino, $movimiento->item) < $movimiento->cantidad) {
            throw ValidationException::withMessages([
                'motivo' => "{$movimiento->destino->placa} ya no tiene lo que recibió: no se puede deshacer el traslado.",
            ]);
        }

        $movimiento->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $request->validated('motivo'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "{$movimiento->tipo->label()} anulado."]);
    }

    /**
     * Hasta cuándo vale el extintor o el botiquín de la unidad.
     */
    public function vencimiento(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', ConteoEquipamiento::class);

        $datos = $request->validate([
            'item_equipamiento_id' => ['required', 'integer', Rule::exists('items_equipamiento', 'id')->where('activo', true)->where('lleva_vencimiento', true)],
            'vence_el' => ['required', 'date'],
        ], [
            'item_equipamiento_id.exists' => 'Ese ítem no lleva fecha de vencimiento.',
            'vence_el.required' => 'Anota la fecha de vencimiento.',
        ]);

        VencimientoEquipamiento::query()->updateOrCreate(
            ['vehiculo_id' => $vehiculo->id, 'item_equipamiento_id' => $datos['item_equipamiento_id']],
            ['vence_el' => $datos['vence_el'], 'user_id' => $request->user()?->id],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Vencimiento guardado.']);
    }

    /**
     * El catálogo con sus mínimos. Un ítem quitado deja de contarse, pero no
     * se borra: sus conteos anteriores lo siguen nombrando. Cada ítem queda
     * con su artículo en el almacén.
     */
    public function actualizarCatalogo(UpdateItemsEquipamientoRequest $request, ArticulosDeEquipamiento $articulos): RedirectResponse
    {
        $this->authorize('configurar', ConteoEquipamiento::class);

        $items = $request->items();

        DB::transaction(function () use ($items, $articulos): void {
            $quedan = [];

            foreach ($items as $orden => $datos) {
                $item = ItemEquipamiento::query()->updateOrCreate(
                    ['id' => $datos['id']],
                    [
                        'nombre' => $datos['nombre'],
                        'minimo' => $datos['minimo'],
                        'orden' => $orden + 1,
                        'activo' => true,
                        // Lo que no llega se queda como estaba (o por defecto).
                        ...($datos['aplica_a'] === null ? [] : ['aplica_a' => $datos['aplica_a']]),
                        ...($datos['lleva_vencimiento'] === null ? [] : ['lleva_vencimiento' => $datos['lleva_vencimiento']]),
                    ],
                );

                $quedan[] = $item->id;
            }

            ItemEquipamiento::query()->whereNotIn('id', $quedan)->update(['activo' => false]);

            $articulos->asegurar();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Equipamiento y mínimos guardados.']);
    }

    private function registrar(StoreMovimientoEquipamientoRequest $request, Vehiculo $vehiculo, TipoMovimientoEquipamiento $tipo): MovimientoEquipamiento
    {
        $datos = $request->validated();

        return MovimientoEquipamiento::create([
            'vehiculo_id' => $vehiculo->id,
            'item_equipamiento_id' => $datos['item_equipamiento_id'],
            'tipo' => $tipo,
            'cantidad' => $datos['cantidad'],
            'fecha' => $datos['fecha'],
            'vehiculo_destino_id' => $datos['vehiculo_destino_id'] ?? null,
            'motivo' => $datos['motivo'] ?? null,
            'user_id' => $request->user()?->id,
        ])->load(['item', 'destino']);
    }

    /**
     * @param  EstadoUnidadEquipada  $estado
     * @return array<string, mixed>
     */
    private function estado(array $estado): array
    {
        return [
            'conteo_fecha' => $estado['conteo']['fecha'] ?? null,
            'items' => $estado['items'],
            'faltan' => $estado['faltan'],
            'vencidos' => $estado['vencidos'],
            'estado' => $estado['estado']->value,
            'estado_label' => $estado['estado']->label(),
            'prioridad' => $estado['estado']->prioridad(),
        ];
    }

    /**
     * @return list<array{id: int, nombre: string, minimo: int|null, aplica_a: string, lleva_vencimiento: bool, articulo: string|null}>
     */
    private function catalogo(): array
    {
        return array_values(ItemEquipamiento::query()
            ->vigentes()
            ->with('articulo:id,codigo')
            ->get()
            ->map(fn (ItemEquipamiento $item): array => [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'minimo' => $item->minimo,
                'aplica_a' => $item->aplica_a->value,
                'lleva_vencimiento' => $item->lleva_vencimiento,
                'articulo' => $item->articulo?->codigo,
            ])
            ->all());
    }
}
