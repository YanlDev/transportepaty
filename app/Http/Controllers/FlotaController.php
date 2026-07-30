<?php

namespace App\Http\Controllers;

use App\Models\EstadoUnidad;
use App\Models\Vehiculo;
use App\Services\EstimadorLlegada;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dónde está la flota ahora mismo y qué está por descargar. Lee el último estado
 * conocido de cada unidad, no el de hoy: si el día todavía no se cargó, lo útil
 * sigue siendo saber dónde quedó cada unidad, no una pantalla vacía.
 *
 * El mapa muestra la última posición reportada, que no es lo mismo que la
 * posición actual. Con el corredor por delante y sin GPS de por medio, alcanza
 * para lo que se necesita: saber qué unidad va con qué carga y cuándo descarga.
 */
class FlotaController extends Controller
{
    /**
     * @var list<string>
     */
    private const RELACIONES = [
        'tracto:id,placa',
        'carreta:id,placa',
        'conductor:id,nombres,apellidos',
        'ubicacion',
        'origen',
        'destino',
    ];

    public function index(): Response
    {
        $this->authorize('viewAny', EstadoUnidad::class);

        // Hasta mañana, o sea todo lo registrado incluido hoy.
        $estados = EstadoUnidad::ultimosAntesDe(
            now()->addDay()->toDateString(),
            relaciones: self::RELACIONES,
        );

        $estimador = EstimadorLlegada::paraLaFlota();

        return Inertia::render('flota/index', [
            'puntos' => $this->puntosDelMapa($estados),
            'descargas' => $this->proximasDescargas($estados, $estimador),
            'resumen' => [
                'unidades' => $estados->count(),
                'en_base' => $estados->filter(
                    fn (EstadoUnidad $estado): bool => $estado->ubicacion !== null
                        && $estado->ubicacion->es_zona_base
                )->count(),
                'sin_posicion' => $estados->filter(
                    fn (EstadoUnidad $estado): bool => $estado->ubicacion === null
                        || ! $estado->ubicacion->tieneCoordenadas()
                )->count(),
                'estimacion_calibrada' => $estimador->estaCalibrado(),
                'kilometros_por_dia' => (int) round($estimador->kilometrosPorDia()),
            ],
        ]);
    }

    /**
     * Línea de tiempo de una unidad: por dónde pasó, qué llevaba y cuándo. Es lo
     * que convierte los estados diarios en historia consultable.
     */
    public function unidad(Vehiculo $vehiculo): Response
    {
        $this->authorize('viewAny', EstadoUnidad::class);

        $estados = EstadoUnidad::query()
            ->with(self::RELACIONES)
            ->where('tracto_id', $vehiculo->id)
            ->orderByDesc('fecha')
            ->get();

        return Inertia::render('flota/unidad', [
            'tracto' => ['id' => $vehiculo->id, 'placa' => $vehiculo->placa],
            'linea' => $estados->map(fn (EstadoUnidad $estado): array => [
                'id' => $estado->id,
                'fecha' => $estado->fecha->toDateString(),
                'ubicacion' => $estado->ubicacion === null
                    ? $estado->ubicacion_texto
                    : $estado->ubicacion->nombre,
                'tipo_carga_label' => $estado->tipo_carga?->label(),
                'fase_label' => $estado->fase?->label(),
                'ruta' => $estado->origen === null && $estado->destino === null ? null : [
                    'origen' => $estado->origen?->nombre,
                    'destino' => $estado->destino?->nombre,
                ],
                'conductor' => $estado->conductor?->nombre_completo,
                'carreta' => $estado->carreta?->placa,
                'observaciones' => $estado->observaciones,
            ]),
        ]);
    }

    /**
     * Unidades agrupadas por punto del mapa. Con sesenta y un unidades sobre
     * unas pocas ubicaciones los marcadores se encimarían, así que cada punto
     * lleva su cuenta y despliega las unidades al pulsarlo.
     *
     * Los puntos sin coordenadas quedan fuera: un camión mal ubicado en el mapa
     * es peor que uno sin ubicar, porque al primero se le cree.
     *
     * @param  Collection<int, EstadoUnidad>  $estados
     * @return list<array<string, mixed>>
     */
    private function puntosDelMapa(Collection $estados): array
    {
        $puntos = [];

        foreach ($estados as $estado) {
            $ubicacion = $estado->ubicacion;

            if ($ubicacion === null || ! $ubicacion->tieneCoordenadas()) {
                continue;
            }

            $puntos[$ubicacion->id] ??= [
                'id' => $ubicacion->id,
                'nombre' => $ubicacion->nombre,
                'latitud' => $ubicacion->latitud,
                'longitud' => $ubicacion->longitud,
                'es_zona_base' => $ubicacion->es_zona_base,
                'unidades' => [],
                'total' => 0,
            ];

            $puntos[$ubicacion->id]['unidades'][] = [
                'id' => $estado->tracto_id,
                'placa' => $estado->tracto->placa,
                'tipo_carga_label' => $estado->tipo_carga?->label(),
                'destino' => $estado->destino?->nombre,
                'fecha' => $estado->fecha->toDateString(),
            ];
            $puntos[$ubicacion->id]['total']++;
        }

        foreach ($puntos as &$punto) {
            usort(
                $punto['unidades'],
                fn (array $una, array $otra): int => strcmp($una['placa'], $otra['placa']),
            );
        }
        unset($punto);

        // Los puntos más cargados primero, para que el mapa dibuje encima los
        // círculos pequeños y no queden tapados por los grandes.
        usort($puntos, fn (array $uno, array $otro): int => $otro['total'] <=> $uno['total']);

        return $puntos;
    }

    /**
     * Lo que está por descargar, agrupado por destino y ordenado por llegada.
     * Es la vista que reemplaza revisar el Excel a ver quién está por llegar.
     *
     * @param  Collection<int, EstadoUnidad>  $estados
     * @return list<array<string, mixed>>
     */
    private function proximasDescargas(Collection $estados, EstimadorLlegada $estimador): array
    {
        $grupos = [];

        foreach ($estados as $estado) {
            $descarga = $this->comoDescarga($estado, $estimador);

            if ($descarga === null) {
                continue;
            }

            $destinoId = $descarga['destino_id'];

            $grupos[$destinoId] ??= [
                'destino_id' => $destinoId,
                'destino' => $descarga['destino'],
                'unidades' => [],
                'total' => 0,
            ];

            $grupos[$destinoId]['unidades'][] = $descarga;
            $grupos[$destinoId]['total']++;
        }

        foreach ($grupos as &$grupo) {
            usort(
                $grupo['unidades'],
                fn (array $una, array $otra): int => strcmp($una['fecha_estimada'], $otra['fecha_estimada']),
            );
        }
        unset($grupo);

        // El destino con la llegada más próxima encabeza el tablero.
        usort($grupos, fn (array $uno, array $otro): int => strcmp(
            $uno['unidades'][0]['fecha_estimada'],
            $otro['unidades'][0]['fecha_estimada'],
        ));

        return $grupos;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function comoDescarga(EstadoUnidad $estado, EstimadorLlegada $estimador): ?array
    {
        $estimacion = $estimador->estimar($estado);

        if ($estimacion === null) {
            return null;
        }

        return [
            'estado_id' => $estado->id,
            'tracto_id' => $estado->tracto_id,
            'placa' => $estado->tracto->placa,
            'carreta' => $estado->carreta?->placa,
            'conductor' => $estado->conductor?->nombre_completo,
            'tipo_carga_label' => $estado->tipo_carga?->label(),
            'ubicacion' => $estado->ubicacion?->nombre,
            'destino_id' => $estado->destino?->id,
            'destino' => $estado->destino?->nombre,
            'reportado' => $estado->fecha->toDateString(),
            'fecha_estimada' => $estimacion->fechaEstimada,
            'estimacion' => $estimacion->toArray(),
        ];
    }
}
