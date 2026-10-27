<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVuelta;
use App\Enums\ResultadoTaller;
use App\Http\Requests\RevisarSalidaRequest;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\Vuelta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La bandeja de taller: los tickets que esperan el OK de salida. Taller da
 * apto (pasa a abastecimiento) o no apto, con cuándo estará lista la unidad,
 * el motivo y los trabajos.
 */
class TallerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RevisionTaller::class);

        $vueltas = Vuelta::query()
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->with([
                'tracto:id,placa',
                'carreta:id,placa',
                'conductor:id,nombres,apellidos',
                'paradas.viajes:id,parada_vuelta_id',
                'revisiones.usuario:id,name',
            ])
            ->get()
            // La que ya volvió a la base espera el ticket siguiente.
            ->reject(fn (Vuelta $vuelta): bool => $vuelta->etapa() === 'en_base')
            ->sortBy(fn (Vuelta $vuelta): string => $vuelta->inicio?->toDateString().'|'.$vuelta->tracto->placa)
            ->values();

        return Inertia::render('taller/index', [
            'tickets' => array_values($vueltas
                ->map(fn (Vuelta $vuelta): array => $this->fila($vuelta, $request))
                ->all()),
        ]);
    }

    public function store(RevisarSalidaRequest $request, Vuelta $vuelta): RedirectResponse
    {
        $this->authorize('create', [RevisionTaller::class, $vuelta]);

        $resultado = ResultadoTaller::from($request->string('resultado')->toString());
        $noApta = $resultado === ResultadoTaller::NoApto;

        $vuelta->revisiones()->create([
            'resultado' => $resultado,
            'disponible_estimado' => $noApta ? Carbon::parse($request->string('disponible_estimado')->toString()) : null,
            'motivo' => $noApta ? $request->string('motivo')->toString() : null,
            'trabajos' => $request->string('trabajos')->toString() ?: null,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', [
            'type' => $noApta ? 'warning' : 'success',
            'message' => $noApta
                ? "{$vuelta->codigo}: no apta. Programación verá cuándo estará lista."
                : "{$vuelta->codigo}: apta. Pasa a abastecimiento.",
        ]);
    }

    /**
     * Un ticket en la bandeja, con su revisión vigente y las anteriores.
     *
     * @return array<string, mixed>
     */
    private function fila(Vuelta $vuelta, Request $request): array
    {
        $primera = $vuelta->paradas->first();
        $ultima = $vuelta->paradas->last();
        $conductor = $vuelta->conductor;

        return [
            'id' => $vuelta->id,
            'codigo' => $vuelta->codigo,
            'placa' => $vuelta->tracto->placa,
            'carreta' => $vuelta->carreta?->placa,
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
