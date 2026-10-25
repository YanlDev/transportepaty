<?php

namespace App\Http\Controllers;

use App\Enums\SentidoTramo;
use App\Models\ParadaVuelta;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use Closure;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las vueltas de la flota: en qué anda cada unidad (la portada) y la
 * historia de una unidad, vuelta por vuelta, con sus GR.
 */
class VueltaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Vuelta::class);

        // La vuelta más reciente de cada unidad: la que está andando o, si no
        // hay ninguna abierta, la última que cerró.
        $ultimas = Vuelta::query()
            ->whereIn('id', Vuelta::query()->selectRaw('max(id)')->groupBy('tracto_id'))
            ->with($this->relaciones())
            ->get();

        $cantidades = Vuelta::query()
            ->selectRaw('tracto_id, count(*) as total')
            ->groupBy('tracto_id')
            ->pluck('total', 'tracto_id');

        return Inertia::render('vueltas/index', [
            'unidades' => $ultimas
                ->sortBy(fn (Vuelta $vuelta): string => $vuelta->tracto->placa)
                ->values()
                ->map(fn (Vuelta $vuelta): array => [
                    'tracto' => ['id' => $vuelta->tracto->id, 'placa' => $vuelta->tracto->placa],
                    'vueltas' => (int) ($cantidades[$vuelta->tracto_id] ?? 0),
                    'vuelta' => $this->vuelta($vuelta),
                ])
                ->all(),
        ]);
    }

    public function unidad(Vehiculo $vehiculo): Response
    {
        $this->authorize('viewAny', Vuelta::class);

        $vueltas = Vuelta::query()
            ->where('tracto_id', $vehiculo->id)
            ->with($this->relaciones())
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('vueltas/unidad', [
            'tracto' => ['id' => $vehiculo->id, 'placa' => $vehiculo->placa],
            'vueltas' => $vueltas->map(fn (Vuelta $vuelta): array => $this->vuelta($vuelta))->all(),
        ]);
    }

    /**
     * Las relaciones de la vuelta que pinta la pantalla: las simples por
     * nombre, las GR de cada parada con sus columnas y en orden.
     *
     * @return array<int|string, string|Closure>
     */
    private function relaciones(): array
    {
        return [
            'tracto:id,placa',
            'carreta:id,placa',
            'conductor:id,nombres,apellidos',
            'paradas.viajes' => fn ($query) => $query
                ->select(['id', 'parada_vuelta_id', 'numero_gr', 'fecha_traslado', 'peso', 'unidad_peso'])
                ->orderBy('fecha_traslado')
                ->orderBy('id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vuelta(Vuelta $vuelta): array
    {
        $conductor = $vuelta->conductor;

        return [
            'id' => $vuelta->id,
            'codigo' => $vuelta->codigo,
            'tipo' => $vuelta->tipo->value,
            'tipo_label' => $vuelta->tipo->label(),
            'estado' => $vuelta->estado->value,
            'estado_label' => $vuelta->estado->label(),
            'origen' => $vuelta->origen->value,
            'inicio' => $vuelta->inicio?->toDateString(),
            'fin' => $vuelta->fin?->toDateString(),
            'dias' => $vuelta->inicio && $vuelta->fin ? (int) $vuelta->inicio->diffInDays($vuelta->fin) + 1 : null,
            'carreta' => $vuelta->carreta?->placa,
            'conductor' => $conductor ? "{$conductor->nombres} {$conductor->apellidos}" : null,
            'ida' => $vuelta->paradasDe(SentidoTramo::Ida)->map(fn (ParadaVuelta $parada): array => $this->parada($parada))->all(),
            'regreso' => $vuelta->paradasDe(SentidoTramo::Regreso)->map(fn (ParadaVuelta $parada): array => $this->parada($parada))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parada(ParadaVuelta $parada): array
    {
        return [
            'id' => $parada->id,
            'origen' => $parada->lugar_origen ?? $parada->zona_origen?->label(),
            'destino' => $parada->lugar_destino ?? $parada->zona_destino?->label(),
            'zona_origen' => $parada->zona_origen?->label(),
            'zona_destino' => $parada->zona_destino?->label(),
            'tipo_carga' => $parada->tipo_carga->value,
            'tipo_carga_label' => $parada->tipo_carga->label(),
            'cliente' => $parada->cliente,
            'fecha' => $parada->fecha?->toDateString(),
            'guias' => $parada->viajes
                ->map(fn (Viaje $viaje): array => [
                    'id' => $viaje->id,
                    'numero_gr' => $viaje->numero_gr,
                    'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                ])
                ->all(),
        ];
    }
}
