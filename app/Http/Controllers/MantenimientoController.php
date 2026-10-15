<?php

namespace App\Http\Controllers;

use App\Enums\EstadoMantenimiento;
use App\Enums\TipoVehiculo;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\ServicioMantenimiento;
use App\Models\Vehiculo;
use App\Services\ProximoMantenimiento;
use App\Services\RelojOperativo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El semáforo de mantenimiento de la flota y la ficha de cada unidad. Lo que
 * se muestra lo calcula `ProximoMantenimiento`; acá solo se arma para la
 * pantalla.
 *
 * @phpstan-import-type EstadoUnidad from ProximoMantenimiento
 * @phpstan-import-type EstadoServicio from ProximoMantenimiento
 */
class MantenimientoController extends Controller
{
    public function __construct(private readonly ProximoMantenimiento $mantenimiento) {}

    public function index(): Response
    {
        $this->authorize('viewAny', OrdenTrabajo::class);

        $tractos = $this->tractosActivos();
        $estados = $this->mantenimiento->paraFlota($tractos);

        // En el semáforo solo las marcas con plan; las demás igual pueden
        // registrar su km, que queda listo para cuando tengan el suyo.
        $unidades = $tractos
            ->filter(fn (Vehiculo $vehiculo): bool => $estados[$vehiculo->id]['plan'] !== null)
            ->map(fn (Vehiculo $vehiculo): array => $this->filaUnidad($vehiculo, $estados[$vehiculo->id]))
            ->sortBy([
                fn (array $a, array $b): int => $a['prioridad'] <=> $b['prioridad'],
                fn (array $a, array $b): int => ($a['proximo']['faltan_km'] ?? PHP_INT_MAX) <=> ($b['proximo']['faltan_km'] ?? PHP_INT_MAX),
            ])
            ->values();

        return Inertia::render('mantenimiento/index', [
            'unidades' => $unidades->all(),
            'resumen' => collect(EstadoMantenimiento::cases())
                ->map(fn (EstadoMantenimiento $estado): array => [
                    'estado' => $estado->value,
                    'label' => $estado->label(),
                    'cantidad' => $unidades->where('estado', $estado->value)->count(),
                ])
                ->all(),
            'planes' => PlanMantenimiento::query()->orderBy('marca')->pluck('marca')->all(),
            'tractos' => $this->opcionesTractos($tractos, $estados),
        ]);
    }

    public function show(Vehiculo $vehiculo): Response
    {
        $this->authorize('viewAny', OrdenTrabajo::class);

        $estado = $this->mantenimiento->paraVehiculo($vehiculo);

        return Inertia::render('mantenimiento/show', [
            'unidad' => [
                ...$this->filaUnidad($vehiculo, $estado),
                'plan' => $estado['plan'],
                'servicios' => array_map(fn (array $servicio): array => $this->servicio($servicio), $estado['servicios']),
            ],
            // Lo que hay que preparar para el próximo servicio: el suyo y el
            // de los niveles que incluye.
            'repuestos' => $estado['proximo'] === null ? [] : $this->repuestosHasta($estado['proximo']),
            'ordenes' => OrdenTrabajo::query()
                ->where('vehiculo_id', $vehiculo->id)
                ->with(['servicio:id,codigo,nombre', 'media'])
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (OrdenTrabajo $orden): array => [
                    'id' => $orden->id,
                    'fecha' => $orden->fecha->toDateString(),
                    'tipo' => $orden->tipo->value,
                    'tipo_label' => $orden->tipo->label(),
                    'servicio' => $orden->servicio?->codigo,
                    'estado' => $orden->estado->value,
                    'estado_label' => $orden->estado->label(),
                    'kilometraje' => $orden->kilometraje,
                    'trabajo' => $orden->trabajo,
                    'tecnico' => $orden->tecnico,
                    'fotos' => $orden->media->count(),
                ])
                ->all(),
            'lecturas' => LecturaKilometraje::query()
                ->where('vehiculo_id', $vehiculo->id)
                ->with(['user:id,name', 'media'])
                ->orderByDesc('leido_en')
                ->limit(20)
                ->get()
                ->map(fn (LecturaKilometraje $lectura): array => [
                    'id' => $lectura->id,
                    'kilometraje' => $lectura->kilometraje,
                    'leido_en' => $lectura->leido_en->toIso8601String(),
                    'usuario' => $lectura->user?->name,
                    'observacion' => $lectura->observacion,
                    'foto_url' => $lectura->getFirstMediaUrl('foto') ?: null,
                ])
                ->all(),
            'tractos' => $this->opcionesTractos(collect([$vehiculo]), [$vehiculo->id => $estado]),
        ]);
    }

    /**
     * @return Collection<int, Vehiculo>
     */
    private function tractosActivos(): Collection
    {
        return Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca', 'modelo', 'anio', 'tipo']);
    }

    /**
     * @param  EstadoUnidad  $estado
     * @return array<string, mixed>
     */
    private function filaUnidad(Vehiculo $vehiculo, array $estado): array
    {
        return [
            'id' => $vehiculo->id,
            'placa' => $vehiculo->placa,
            'marca' => $vehiculo->marca,
            'modelo' => $vehiculo->modelo,
            'anio' => $vehiculo->anio,
            'km_actual' => $estado['km_actual'],
            'km_fecha' => $estado['km_fecha'],
            'km_desactualizado' => $this->desactualizado($estado['km_fecha']),
            'estado' => $estado['estado']->value,
            'estado_label' => $estado['estado']->label(),
            'prioridad' => $estado['estado']->prioridad(),
            'proximo' => $estado['proximo'] === null ? null : $this->servicio($estado['proximo']),
        ];
    }

    /**
     * @param  EstadoServicio  $servicio
     * @return array<string, mixed>
     */
    private function servicio(array $servicio): array
    {
        return [
            ...$servicio,
            'estado' => $servicio['estado']->value,
            'estado_label' => $servicio['estado']->label(),
        ];
    }

    /**
     * Un km anotado hace más de unos días: el semáforo sale de un dato viejo
     * y conviene decirlo junto al número.
     */
    private function desactualizado(?string $fecha): bool
    {
        if ($fecha === null) {
            return false;
        }

        return CarbonImmutable::parse($fecha)->diffInDays(RelojOperativo::fechaDeHoy())
            > (int) config('transpaty.mantenimiento.km_desactualizado_dias');
    }

    /**
     * @param  EstadoServicio  $proximo
     * @return list<array{servicio: string, descripcion: string, cantidad: float|null, unidad: string|null}>
     */
    private function repuestosHasta(array $proximo): array
    {
        $servicio = ServicioMantenimiento::query()->findOrFail($proximo['id']);

        $niveles = ServicioMantenimiento::query()
            ->where('plan_mantenimiento_id', $servicio->plan_mantenimiento_id)
            ->where('nivel', '<=', $servicio->nivel)
            ->with('repuestos')
            ->orderBy('nivel')
            ->get();

        $repuestos = [];

        foreach ($niveles as $nivel) {
            foreach ($nivel->repuestos as $repuesto) {
                $repuestos[] = [
                    'servicio' => $nivel->codigo,
                    'descripcion' => $repuesto->descripcion,
                    'cantidad' => $repuesto->cantidad === null ? null : (float) $repuesto->cantidad,
                    'unidad' => $repuesto->unidad,
                ];
            }
        }

        return $repuestos;
    }

    /**
     * Los tractos para el selector de «Registrar km», con su último km para
     * mostrar la diferencia mientras se escribe.
     *
     * @param  Collection<int, Vehiculo>  $tractos
     * @param  array<int, EstadoUnidad>  $estados
     * @return list<array{id: int, placa: string, marca: string|null, km_actual: int|null}>
     */
    private function opcionesTractos(Collection $tractos, array $estados): array
    {
        return array_values($tractos
            ->map(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'km_actual' => $estados[$vehiculo->id]['km_actual'] ?? null,
            ])
            ->all());
    }
}
