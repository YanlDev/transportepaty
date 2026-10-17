<?php

namespace App\Http\Controllers;

use App\Enums\CondicionLlanta;
use App\Enums\EstadoLlanta;
use App\Enums\MotivoRetiroLlanta;
use App\Enums\TipoMovimientoLlanta;
use App\Enums\TipoVehiculo;
use App\Http\Requests\StoreMedicionLlantasRequest;
use App\Http\Requests\StoreMovimientoLlantaRequest;
use App\Http\Requests\UpdateLlantaRequest;
use App\Http\Requests\UpdateMovimientoLlantaRequest;
use App\Http\Requests\UpdateParametrosLlantasRequest;
use App\Models\Llanta;
use App\Models\MovimientoLlanta;
use App\Models\ParametroMantenimiento;
use App\Models\Vehiculo;
use App\Services\EstadoLlantas;
use App\Services\HistorialLlantas;
use App\Services\ProximoMantenimiento;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las llantas de la flota, por posición: el tracto con sus 10 y la carreta
 * con sus 12 y dos de repuesto. Cada unidad tiene su esquema, y cada
 * posición su historial de instalaciones, mediciones, rotaciones,
 * reparaciones y retiros.
 *
 * @phpstan-import-type EstadoPosicion from EstadoLlantas
 */
class LlantaController extends Controller
{
    public function __construct(
        private readonly EstadoLlantas $llantas,
        private readonly HistorialLlantas $historial,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Llanta::class);

        $vehiculos = Vehiculo::query()
            ->orderBy('tipo')
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca', 'tipo']);

        $estados = $this->llantas->paraFlota($vehiculos);

        return Inertia::render('mantenimiento/llantas/index', [
            'unidades' => $vehiculos
                ->map(fn (Vehiculo $vehiculo): array => [
                    'id' => $vehiculo->id,
                    'placa' => $vehiculo->placa,
                    'marca' => $vehiculo->marca,
                    'tipo' => $vehiculo->tipo->value,
                    ...$this->conteo($estados[$vehiculo->id]),
                ])
                ->sortBy([
                    fn (array $a, array $b): int => $a['prioridad'] <=> $b['prioridad'],
                    fn (array $a, array $b): int => $a['placa'] <=> $b['placa'],
                ])
                ->values()
                ->all(),
            'umbrales' => $this->umbrales(),
        ]);
    }

    public function show(Vehiculo $vehiculo, ProximoMantenimiento $mantenimiento): Response
    {
        $this->authorize('viewAny', Llanta::class);

        $movimientos = MovimientoLlanta::query()
            ->where('vehiculo_id', $vehiculo->id)
            ->with(['llanta:id,marca,medida,codigo', 'user:id,name'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        $eliminables = $this->historial->eliminables($movimientos);

        return Inertia::render('mantenimiento/llantas/show', [
            'unidad' => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'tipo' => $vehiculo->tipo->value,
                // Las carretas no llevan km: lo suyo se cuenta por fechas.
                'km_actual' => $vehiculo->tipo === TipoVehiculo::Tracto
                    ? $mantenimiento->paraVehiculo($vehiculo)['km_actual']
                    : null,
            ],
            'posiciones' => array_map(
                fn (array $posicion): array => $this->posicion($posicion),
                $this->llantas->paraVehiculo($vehiculo),
            ),
            'movimientos' => $movimientos->map(fn (MovimientoLlanta $movimiento): array => [
                'id' => $movimiento->id,
                'llanta_id' => $movimiento->llanta_id,
                'tipo' => $movimiento->tipo->value,
                'tipo_label' => $movimiento->tipo->label(),
                'fecha' => $movimiento->fecha->toDateString(),
                'posicion' => $movimiento->posicion,
                'posicion_destino' => $movimiento->posicion_destino,
                'kilometraje' => $movimiento->kilometraje,
                'profundidad_mm' => $movimiento->profundidad_mm === null ? null : (float) $movimiento->profundidad_mm,
                'observacion' => $movimiento->observacion,
                'llanta' => trim(implode(' ', array_filter([$movimiento->llanta->marca, $movimiento->llanta->medida]))) ?: null,
                'usuario' => $movimiento->user?->name,
                'eliminable' => $eliminables[$movimiento->id] ?? false,
            ])
                ->all(),
            'hoy' => RelojOperativo::hoy(),
            'condiciones' => CondicionLlanta::options(),
            'motivos' => MotivoRetiroLlanta::options(),
            'umbrales' => $this->umbrales(),
        ]);
    }

    public function store(StoreMovimientoLlantaRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', Llanta::class);

        $datos = $request->validated();
        $tipo = TipoMovimientoLlanta::from($datos['tipo']);
        $comun = [
            'vehiculo_id' => $vehiculo->id,
            'tipo' => $tipo,
            'fecha' => $datos['fecha'],
            'kilometraje' => $datos['kilometraje'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
            'user_id' => $request->user()?->id,
        ];

        $mensaje = DB::transaction(function () use ($vehiculo, $datos, $tipo, $comun): string {
            if ($tipo === TipoMovimientoLlanta::Instalacion) {
                $llanta = Llanta::create([
                    'vehiculo_id' => $vehiculo->id,
                    'posicion' => $datos['posicion'],
                    'marca' => $datos['marca'] ?? null,
                    'medida' => $datos['medida'] ?? null,
                    'codigo' => $datos['codigo'] ?? null,
                    'condicion' => $datos['condicion'],
                    'instalada_el' => $datos['fecha'],
                    'km_instalacion' => $datos['kilometraje'] ?? null,
                ]);

                $llanta->movimientos()->create([
                    ...$comun,
                    'posicion' => $datos['posicion'],
                    'profundidad_mm' => $datos['profundidad_mm'] ?? null,
                ]);

                return "Llanta instalada en la posición {$datos['posicion']}.";
            }

            $llanta = $this->montadaEn($vehiculo, $datos['posicion']);

            if ($tipo === TipoMovimientoLlanta::Rotacion) {
                return $this->rotar($vehiculo, $llanta, $datos['posicion_destino'], $comun);
            }

            if ($tipo === TipoMovimientoLlanta::Retiro) {
                $llanta->update([
                    'retirada_el' => $datos['fecha'],
                    'km_retiro' => $datos['kilometraje'] ?? null,
                    'motivo_retiro' => $datos['motivo_retiro'],
                ]);
            }

            $llanta->movimientos()->create([
                ...$comun,
                'posicion' => $llanta->posicion,
                'profundidad_mm' => $datos['profundidad_mm'] ?? null,
            ]);

            return $tipo === TipoMovimientoLlanta::Retiro
                ? "Llanta de la posición {$llanta->posicion} retirada: la posición queda libre."
                : "Reparación de la posición {$llanta->posicion} registrada.";
        });

        return back()->with('toast', ['type' => 'success', 'message' => $mensaje]);
    }

    /**
     * La profundidad de varias llantas en una misma vuelta, cada una con su
     * movimiento de medición.
     */
    public function medir(StoreMedicionLlantasRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', Llanta::class);

        $datos = $request->validated();

        $llantas = Llanta::query()
            ->montadas()
            ->where('vehiculo_id', $vehiculo->id)
            ->get()
            ->keyBy('posicion');

        DB::transaction(function () use ($datos, $llantas, $vehiculo, $request): void {
            foreach ($datos['mediciones'] as $medicion) {
                $llanta = $llantas->get($medicion['posicion']);

                $llanta?->movimientos()->create([
                    'vehiculo_id' => $vehiculo->id,
                    'tipo' => TipoMovimientoLlanta::Inspeccion,
                    'fecha' => $datos['fecha'],
                    'posicion' => $llanta->posicion,
                    'kilometraje' => $datos['kilometraje'] ?? null,
                    'profundidad_mm' => $medicion['profundidad_mm'],
                    'user_id' => $request->user()?->id,
                ]);
            }
        });

        $parametros = ParametroMantenimiento::vigentes();
        $profundidades = Arr::pluck($datos['mediciones'], 'profundidad_mm');
        $cambiar = count(array_filter(
            $profundidades,
            fn (mixed $milimetros): bool => $parametros->estadoLlanta((float) $milimetros) === EstadoLlanta::Cambiar,
        ));

        $cantidad = count($profundidades);

        return back()->with('toast', [
            'type' => $cambiar > 0 ? 'warning' : 'success',
            'message' => "Medición de {$cantidad} ".($cantidad === 1 ? 'llanta' : 'llantas').' registrada.'
                .($cambiar > 0 ? " {$cambiar} ".($cambiar === 1 ? 'está' : 'están').' para cambiar.' : ''),
        ]);
    }

    /**
     * Corregir los datos de la llanta que se escribieron mal al instalarla.
     */
    public function update(UpdateLlantaRequest $request, Llanta $llanta): RedirectResponse
    {
        $this->authorize('update', Llanta::class);

        $llanta->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => "Datos de la llanta de la posición {$llanta->posicion} corregidos."]);
    }

    public function corregirMovimiento(UpdateMovimientoLlantaRequest $request, MovimientoLlanta $movimiento): RedirectResponse
    {
        $this->authorize('update', Llanta::class);

        $this->historial->corregir(
            $movimiento,
            fecha: $request->string('fecha')->value(),
            kilometraje: $request->filled('kilometraje') ? $request->integer('kilometraje') : null,
            profundidad: $request->filled('profundidad_mm') ? $request->float('profundidad_mm') : null,
            observacion: $request->filled('observacion') ? $request->string('observacion')->value() : null,
        );

        return back()->with('toast', ['type' => 'success', 'message' => "{$movimiento->tipo->label()} corregida."]);
    }

    public function eliminarMovimiento(MovimientoLlanta $movimiento): RedirectResponse
    {
        $this->authorize('update', Llanta::class);

        $this->historial->eliminar($movimiento);

        return back()->with('toast', [
            'type' => 'success',
            'message' => match ($movimiento->tipo) {
                TipoMovimientoLlanta::Instalacion => "Instalación eliminada: la posición {$movimiento->posicion} queda libre.",
                TipoMovimientoLlanta::Retiro => "Retiro eliminado: la llanta vuelve a la posición {$movimiento->posicion}.",
                default => "{$movimiento->tipo->label()} eliminada.",
            },
        ]);
    }

    /**
     * Los cortes del semáforo, para toda la flota.
     */
    public function actualizarParametros(UpdateParametrosLlantasRequest $request): RedirectResponse
    {
        $parametros = ParametroMantenimiento::vigentes();

        $this->authorize('update', $parametros);

        $parametros->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Cortes del semáforo de llantas guardados.']);
    }

    /**
     * Pasa la llanta a otra posición. Si allá hay otra, se intercambian: es
     * lo que se hace al rotar, y así ninguna posición queda con dos.
     *
     * @param  array<string, mixed>  $comun
     */
    private function rotar(Vehiculo $vehiculo, Llanta $llanta, string $destino, array $comun): string
    {
        $origen = $llanta->posicion;
        $otra = Llanta::query()->montadas()->where('vehiculo_id', $vehiculo->id)->where('posicion', $destino)->first();

        $llanta->update(['posicion' => $destino]);
        $llanta->movimientos()->create([...$comun, 'posicion' => $origen, 'posicion_destino' => $destino]);

        if ($otra === null) {
            return "Llanta movida de la posición {$origen} a la {$destino}.";
        }

        $otra->update(['posicion' => $origen]);
        $otra->movimientos()->create([...$comun, 'posicion' => $destino, 'posicion_destino' => $origen]);

        return "Llantas de las posiciones {$origen} y {$destino} intercambiadas.";
    }

    private function montadaEn(Vehiculo $vehiculo, string $posicion): Llanta
    {
        return Llanta::query()
            ->montadas()
            ->where('vehiculo_id', $vehiculo->id)
            ->where('posicion', $posicion)
            ->firstOrFail();
    }

    /**
     * @param  EstadoPosicion  $posicion
     * @return array<string, mixed>
     */
    private function posicion(array $posicion): array
    {
        return [
            ...$posicion,
            'estado' => $posicion['estado']?->value,
            'estado_label' => $posicion['estado']?->label() ?? 'Vacía',
        ];
    }

    /**
     * Cuántas llantas hay en cada estado, y el peor, para la lista de unidades.
     *
     * @param  list<EstadoPosicion>  $posiciones
     * @return array{posiciones: int, montadas: int, cambiar: int, revisar: int, sin_medir: int, prioridad: int}
     */
    private function conteo(array $posiciones): array
    {
        $estados = collect($posiciones)->pluck('estado')->filter();

        return [
            'posiciones' => count($posiciones),
            'montadas' => $estados->count(),
            'cambiar' => $estados->filter(fn (EstadoLlanta $estado): bool => $estado === EstadoLlanta::Cambiar)->count(),
            'revisar' => $estados->filter(fn (EstadoLlanta $estado): bool => $estado === EstadoLlanta::Revisar)->count(),
            'sin_medir' => $estados->filter(fn (EstadoLlanta $estado): bool => $estado === EstadoLlanta::SinMedir)->count(),
            // Las unidades sin ninguna llanta cargada, al final.
            'prioridad' => $estados->isEmpty()
                ? 4
                : $estados->map(fn (EstadoLlanta $estado): int => $estado->prioridad())->min(),
        ];
    }

    /**
     * @return array{revisar_mm: float, cambiar_mm: float}
     */
    private function umbrales(): array
    {
        return ParametroMantenimiento::vigentes()->umbralesLlanta();
    }
}
