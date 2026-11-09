<?php

namespace App\Http\Controllers\Almacen;

use App\Enums\DestinoSalida;
use App\Enums\TipoIngreso;
use App\Http\Controllers\Almacen\Concerns\CatalogosAlmacen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\Almacen\StoreDevolucionRequest;
use App\Http\Requests\Almacen\StoreSalidaRequest;
use App\Models\Articulo;
use App\Models\DetalleSalida;
use App\Models\IngresoAlmacen;
use App\Models\OrdenTrabajo;
use App\Models\SalidaAlmacen;
use App\Services\Kardex;
use App\Services\PeriodoAlmacen;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los vales de salida: lo que se le entrega al taller, a qué unidad u orden
 * va y cuánto costó. También lo que vuelve sin usar.
 */
class SalidaAlmacenController extends Controller
{
    use CatalogosAlmacen;

    public function __construct(private readonly Kardex $kardex) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalidaAlmacen::class);

        $veCostos = $this->veCostos($request->user());
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'destino' => $request->string('destino')->value(),
            'vehiculo' => $request->string('vehiculo')->value(),
        ];

        $salidas = SalidaAlmacen::query()
            ->with(['vehiculo:id,placa', 'ordenTrabajo:id,trabajo', 'user:id,name'])
            ->withCount('detalles')
            ->when($filtros['buscar'] !== '', function ($query) use ($filtros): void {
                $texto = $filtros['buscar'];
                $id = (int) preg_replace('/\D/', '', $texto);

                $query->where(fn ($query) => $query
                    ->whereRaw('lower(entregado_a) like ?', ['%'.mb_strtolower($texto).'%'])
                    ->orWhereIn('id', DetalleSalida::query()
                        ->whereIn('articulo_id', Articulo::query()->buscar($texto)->select('id'))
                        ->select('salida_almacen_id'))
                    ->when($id > 0, fn ($query) => $query->orWhere('id', $id)));
            })
            ->when($filtros['destino'] !== '', fn ($query) => $query->where('destino', $filtros['destino']))
            ->when($filtros['vehiculo'] !== '', fn ($query) => $query->where('vehiculo_id', (int) $filtros['vehiculo']))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (SalidaAlmacen $salida): array => [
                ...$this->cabecera($salida, $veCostos),
                'lineas' => $salida->detalles_count,
            ]);

        return Inertia::render('almacen/salidas/index', [
            'salidas' => $salidas,
            'filtros' => $filtros,
            'destinos' => DestinoSalida::options(),
            'vehiculos' => $this->vehiculosParaElegir(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SalidaAlmacen::class);

        // Desde una orden de trabajo se llega con la orden ya elegida.
        $orden = OrdenTrabajo::query()->find($request->integer('orden'));

        return Inertia::render('almacen/salidas/create', [
            'articulos' => $this->articulosParaElegir($this->veCostos($request->user()), soloConStock: true),
            'vehiculos' => $this->vehiculosParaElegir(),
            'ordenes' => $this->ordenesParaElegir(),
            'destinos' => DestinoSalida::options(),
            'inicial' => [
                'destino' => $orden !== null ? DestinoSalida::OrdenTrabajo->value : DestinoSalida::Unidad->value,
                'orden_trabajo_id' => $orden?->id,
                'vehiculo_id' => $orden !== null ? $orden->vehiculo_id : ($request->integer('vehiculo') ?: null),
                'entregado_a' => $orden?->tecnico,
            ],
            'hoy' => RelojOperativo::hoy(),
            'inicioPeriodo' => PeriodoAlmacen::inicio()->toDateString(),
        ]);
    }

    public function store(StoreSalidaRequest $request): RedirectResponse
    {
        $this->authorize('create', SalidaAlmacen::class);

        $datos = $request->validated();
        $destino = DestinoSalida::from($datos['destino']);
        $orden = $destino === DestinoSalida::OrdenTrabajo ? OrdenTrabajo::query()->findOrFail((int) $datos['orden_trabajo_id']) : null;

        $salida = DB::transaction(function () use ($request, $datos, $destino, $orden): SalidaAlmacen {
            $salida = SalidaAlmacen::create([
                'fecha' => $datos['fecha'],
                'destino' => $destino,
                // La orden ya dice de qué unidad es.
                'vehiculo_id' => match ($destino) {
                    DestinoSalida::OrdenTrabajo => $orden?->vehiculo_id,
                    DestinoSalida::Unidad => $datos['vehiculo_id'],
                    DestinoSalida::Consumo => null,
                },
                'orden_trabajo_id' => $orden?->id,
                'entregado_a' => $datos['entregado_a'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'user_id' => $request->user()?->id,
            ]);

            foreach ($datos['detalles'] as $linea) {
                $salida->detalles()->create([
                    'articulo_id' => $linea['articulo_id'],
                    'cantidad' => $linea['cantidad'],
                ]);
            }

            $this->kardex->registrarSalida($salida, $request->user()?->id);

            return $salida;
        });

        return to_route('almacen.salidas.show', $salida)
            ->with('toast', ['type' => 'success', 'message' => "Vale {$salida->numero()} emitido."]);
    }

    public function show(Request $request, SalidaAlmacen $salida): Response
    {
        $this->authorize('view', $salida);

        $veCostos = $this->veCostos($request->user());
        $salida->load(['vehiculo:id,placa,marca', 'ordenTrabajo', 'user:id,name', 'anuladoPor:id,name', 'detalles.articulo', 'devoluciones']);

        return Inertia::render('almacen/salidas/show', [
            'salida' => [
                ...$this->cabecera($salida, $veCostos),
                'observacion' => $salida->observacion,
                'anulado_por' => $salida->anuladoPor?->name,
                'motivo_anulacion' => $salida->motivo_anulacion,
                'orden' => $salida->ordenTrabajo === null ? null : [
                    'id' => $salida->ordenTrabajo->id,
                    'trabajo' => $salida->ordenTrabajo->trabajo,
                    'estado' => $salida->ordenTrabajo->estado->label(),
                ],
                'detalles' => $salida->detalles->map(fn (DetalleSalida $detalle): array => [
                    'id' => $detalle->id,
                    'articulo_id' => $detalle->articulo_id,
                    'codigo' => $detalle->articulo->codigo,
                    'nombre' => $detalle->articulo->nombre,
                    'unidad' => $detalle->articulo->unidad->abreviatura(),
                    'cantidad' => (float) $detalle->cantidad,
                    'devuelto' => $detalle->cantidadDevuelta(),
                    'costo_unitario' => $this->costo($veCostos, $detalle->costo_unitario),
                    'costo_total' => $this->costo($veCostos, $detalle->costo_total),
                ])->values()->all(),
                'devoluciones' => $salida->devoluciones->map(fn (IngresoAlmacen $devolucion): array => [
                    'id' => $devolucion->id,
                    'numero' => $devolucion->numero(),
                    'fecha' => $devolucion->fecha->toDateString(),
                    'costo_total' => $this->costo($veCostos, $devolucion->costo_total),
                    'anulado' => $devolucion->estaAnulado(),
                ])->values()->all(),
            ],
            'puedeAnular' => $request->user()?->can('anular', $salida) ?? false,
            'puedeDevolver' => $request->user()?->can('devolver', $salida) ?? false,
            'periodoAbierto' => PeriodoAlmacen::estaAbierto($salida->fecha),
            'hoy' => RelojOperativo::hoy(),
        ]);
    }

    /**
     * Lo que no se usó vuelve al almacén al mismo costo con que salió.
     */
    public function devolver(StoreDevolucionRequest $request, SalidaAlmacen $salida): RedirectResponse
    {
        $this->authorize('devolver', $salida);

        $datos = $request->validated();
        $lineas = $salida->detalles()->get()->keyBy('id');

        $devolucion = DB::transaction(function () use ($request, $salida, $datos, $lineas): IngresoAlmacen {
            $devolucion = IngresoAlmacen::create([
                'tipo' => TipoIngreso::Devolucion,
                'fecha' => $datos['fecha'],
                'salida_almacen_id' => $salida->id,
                'observacion' => $datos['observacion'] ?? null,
                'user_id' => $request->user()?->id,
            ]);

            foreach ($datos['detalles'] as $linea) {
                /** @var DetalleSalida $origen */
                $origen = $lineas[(int) $linea['detalle_salida_id']];
                $cantidad = (float) $linea['cantidad'];

                $devolucion->detalles()->create([
                    'articulo_id' => $origen->articulo_id,
                    'detalle_salida_id' => $origen->id,
                    'cantidad' => $cantidad,
                    'factor' => 1,
                    'cantidad_base' => $cantidad,
                    'costo_unitario' => $origen->costo_unitario,
                    'costo_total' => round($cantidad * (float) $origen->costo_unitario, 2),
                ]);
            }

            $this->kardex->registrarIngreso($devolucion, $request->user()?->id);

            return $devolucion;
        });

        return back()->with('toast', ['type' => 'success', 'message' => "Devolución {$devolucion->numero()} registrada: volvió al stock."]);
    }

    public function anular(AnularDocumentoRequest $request, SalidaAlmacen $salida): RedirectResponse
    {
        $this->authorize('anular', $salida);
        PeriodoAlmacen::exigirAbierto($salida->fecha);

        if ($salida->devoluciones()->vigentes()->exists()) {
            throw ValidationException::withMessages(['motivo' => 'El vale tiene devoluciones: anúlalas primero.']);
        }

        DB::transaction(function () use ($request, $salida): void {
            $salida->update([
                'anulado_at' => now(),
                'anulado_por' => $request->user()?->id,
                'motivo_anulacion' => $request->validated('motivo'),
            ]);

            $this->kardex->anular($salida);
        });

        return back()->with('toast', ['type' => 'success', 'message' => "Vale {$salida->numero()} anulado: lo despachado volvió al stock."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cabecera(SalidaAlmacen $salida, bool $veCostos): array
    {
        return [
            'id' => $salida->id,
            'numero' => $salida->numero(),
            'fecha' => $salida->fecha->toDateString(),
            'destino' => $salida->destino->value,
            'destino_label' => $salida->destino->label(),
            'placa' => $salida->vehiculo?->placa,
            'vehiculo_id' => $salida->vehiculo_id,
            'orden_trabajo_id' => $salida->orden_trabajo_id,
            'entregado_a' => $salida->entregado_a,
            'costo_total' => $this->costo($veCostos, $salida->costo_total),
            'usuario' => $salida->user?->name,
            'registrado' => $salida->created_at?->toIso8601String(),
            'anulado' => $salida->estaAnulado(),
            'anulado_at' => $salida->anulado_at?->toIso8601String(),
        ];
    }
}
