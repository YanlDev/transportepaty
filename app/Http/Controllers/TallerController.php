<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVuelta;
use App\Enums\ResultadoTaller;
use App\Http\Requests\RevisarSalidaRequest;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\Vuelta;
use App\Services\AlertasDelTicket;
use App\Services\AvisosDeTaller;
use App\Services\OdometroDeUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La bandeja de taller: los tickets que esperan el OK de salida. Taller da
 * apto (pasa a abastecimiento) o no apto, con cuándo estará lista la unidad,
 * el motivo y los trabajos.
 *
 * @phpstan-import-type AvisosDeUnidad from AvisosDeTaller
 */
class TallerController extends Controller
{
    /** Hasta cuántos días atrás se reclama el km de una llegada. */
    private const DIAS_DE_LLEGADA = 7;

    public function __construct(
        private readonly AvisosDeTaller $avisos,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RevisionTaller::class);

        $vueltas = Vuelta::query()
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->with([
                'tracto:id,placa,marca,tipo',
                'carreta:id,placa,marca,tipo',
                'conductor:id,nombres,apellidos',
                'paradas.viajes:id,parada_vuelta_id,fecha_traslado',
                'revisiones.usuario:id,name',
            ])
            ->get()
            // La que ya volvió a la base espera el ticket siguiente.
            ->reject(fn (Vuelta $vuelta): bool => $vuelta->etapa() === 'en_base')
            ->sortBy(fn (Vuelta $vuelta): string => $vuelta->inicio?->toDateString().'|'.$vuelta->tracto->placa)
            ->values();

        $usuario = $request->user();
        $veMantenimiento = $usuario?->can('viewAny', OrdenTrabajo::class) ?? false;
        $registraKm = $usuario?->can('create', LecturaKilometraje::class) ?? false;
        $llegadas = $registraKm ? $this->llegadasSinKm() : collect();

        // Lo que hay que saber de cada unidad antes del apto, para todas de
        // una vez: solo para quien puede ver el mantenimiento.
        $avisos = $veMantenimiento
            ? $this->avisos->paraFlota(
                $vueltas->flatMap(fn (Vuelta $vuelta): array => array_filter([$vuelta->tracto, $vuelta->carreta]))
                    ->merge($llegadas->pluck('tracto'))
                    ->values(),
            )
            : [];

        return Inertia::render('taller/index', [
            'tickets' => array_values($vueltas
                ->map(fn (Vuelta $vuelta): array => $this->fila($vuelta, $request, $avisos))
                ->all()),
            'ordenes' => $veMantenimiento ? $this->avisos->ordenesAbiertas() : [],
            'llegadas' => array_values($llegadas
                ->map(fn (Vuelta $vuelta): array => [
                    'id' => $vuelta->tracto_id,
                    'placa' => $vuelta->tracto->placa,
                    'marca' => $vuelta->tracto->marca,
                    'km_actual' => $avisos[$vuelta->tracto_id]['km_actual'] ?? null,
                    'codigo' => $vuelta->codigo,
                    'llegada' => $vuelta->llegada_el?->toDateString(),
                ])
                ->all()),
            'puede' => [
                'ver_unidad' => $veMantenimiento,
                'registrar_km' => $registraKm,
            ],
        ]);
    }

    /**
     * Las unidades que llegaron a la base en la última semana y a las que
     * nadie les anotó el km desde entonces: de ese km sale el preventivo.
     *
     * @return Collection<int, Vuelta>
     */
    private function llegadasSinKm(): Collection
    {
        $llegadas = Vuelta::query()
            ->whereNotNull('llegada_el')
            ->where('llegada_el', '>=', now()->subDays(self::DIAS_DE_LLEGADA)->toDateString())
            ->with('tracto:id,placa,marca,tipo')
            ->orderByDesc('llegada_el')
            ->get()
            ->unique('tracto_id');

        $ultimas = LecturaKilometraje::query()
            ->whereIn('vehiculo_id', $llegadas->pluck('tracto_id'))
            ->groupBy('vehiculo_id')
            ->selectRaw('vehiculo_id, max(leido_en) as ultima')
            ->pluck('ultima', 'vehiculo_id');

        return $llegadas
            ->filter(function (Vuelta $vuelta) use ($ultimas): bool {
                $ultima = $ultimas->get($vuelta->tracto_id);

                return $ultima === null || Carbon::parse($ultima)->lt($vuelta->llegada_el?->startOfDay());
            })
            ->values();
    }

    public function store(RevisarSalidaRequest $request, Vuelta $vuelta, AlertasDelTicket $alertas, OdometroDeUnidad $odometro): RedirectResponse
    {
        $this->authorize('create', [RevisionTaller::class, $vuelta]);

        $resultado = ResultadoTaller::from($request->string('resultado')->toString());
        $noApta = $resultado === ResultadoTaller::NoApto;

        $revision = $vuelta->revisiones()->create([
            'resultado' => $resultado,
            'disponible_estimado' => $noApta ? Carbon::parse($request->string('disponible_estimado')->toString()) : null,
            'motivo' => $noApta ? $request->string('motivo')->toString() : null,
            'trabajos' => $request->string('trabajos')->toString() ?: null,
            'kilometraje' => $request->filled('kilometraje') ? $request->integer('kilometraje') : null,
            'user_id' => $request->user()?->id,
        ]);

        if ($revision->kilometraje !== null) {
            $odometro->anotar($vuelta->tracto_id, $revision->kilometraje, $request->user(), "OK de salida de taller ({$vuelta->codigo})", OdometroDeUnidad::deSalida($vuelta->id));
        }

        $alertas->revisado($vuelta, $revision, $request->user());

        return back()->with('toast', [
            'type' => $noApta ? 'warning' : 'success',
            'message' => $noApta
                ? "{$vuelta->codigo}: no apta. Programación verá cuándo estará lista."
                : "{$vuelta->codigo}: apta. Pasa a abastecimiento.",
        ]);
    }

    /**
     * Un ticket en la bandeja, con su revisión vigente y las anteriores, y
     * los avisos de su tracto y de su carreta.
     *
     * @param  array<int, AvisosDeUnidad>  $avisos
     * @return array<string, mixed>
     */
    private function fila(Vuelta $vuelta, Request $request, array $avisos): array
    {
        $primera = $vuelta->paradas->first();
        $ultima = $vuelta->paradas->last();
        $conductor = $vuelta->conductor;

        return [
            'id' => $vuelta->id,
            'codigo' => $vuelta->codigo,
            'vehiculo_id' => $vuelta->tracto_id,
            'placa' => $vuelta->tracto->placa,
            'carreta' => $vuelta->carreta?->placa,
            // El último km conocido: el de salida se propone desde ahí.
            'km_actual' => $avisos[$vuelta->tracto_id]['km_actual'] ?? null,
            'avisos' => [
                ...($avisos[$vuelta->tracto_id]['avisos'] ?? []),
                // Los de la carreta dicen de quién son: van en el mismo ticket.
                ...array_map(
                    fn (array $aviso): array => [...$aviso, 'texto' => "Carreta: {$aviso['texto']}"],
                    $vuelta->carreta_id === null ? [] : ($avisos[$vuelta->carreta_id]['avisos'] ?? []),
                ),
            ],
            'conductor' => $conductor ? "{$conductor->nombres} {$conductor->apellidos}" : null,
            'inicio' => $vuelta->inicio?->toDateString(),
            'etapa' => $vuelta->etapa(),
            'ruta' => $primera && $ultima
                ? $this->lugar($primera, true).' → '.$this->lugar($ultima, false)
                : null,
            'revision' => $vuelta->revisiones->first()?->resumen(),
            'anteriores' => array_values($vuelta->revisiones
                ->skip(1)
                ->map(fn (RevisionTaller $revision): array => $revision->resumen())
                ->all()),
            'revisable' => $request->user()?->can('create', [RevisionTaller::class, $vuelta]) ?? false,
        ];
    }

    private function lugar(ParadaVuelta $parada, bool $origen): string
    {
        return $origen
            ? ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?')
            : ($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?');
    }
}
