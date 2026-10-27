<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\TipoCarga;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;
use Illuminate\Support\Facades\DB;

/**
 * Rearma las vueltas del historial desde las GR, para que el módulo arranque
 * con la historia de la flota y no vacío.
 *
 * Es el `AsignadorVueltas` corrido GR por GR en orden de traslado, sobre las
 * GR que no están en una vuelta programada: rehacerlo es seguro y da siempre
 * el mismo resultado.
 */
class ReconstructorVueltas
{
    public function __construct(private readonly AsignadorVueltas $asignador) {}

    /**
     * @return array{vueltas: int, paradas: int, vacios: int, guias: int}
     */
    public function reconstruir(?int $tractoId = null): array
    {
        return DB::transaction(function () use ($tractoId): array {
            $this->borrarHistorial($tractoId);

            Viaje::query()
                ->whereNull('parada_vuelta_id')
                ->whereNotNull('tracto_id')
                ->when($tractoId, fn ($query) => $query->where('tracto_id', $tractoId))
                ->orderBy('tracto_id')
                ->orderBy('fecha_traslado')
                ->orderBy('fecha_emision')
                ->orderBy('id')
                ->get()
                ->each(fn (Viaje $guia) => $this->asignador->asignar($guia));

            $this->cerrarLasQuedadasAtras($tractoId);

            return $this->resumen($tractoId);
        });
    }

    private function borrarHistorial(?int $tractoId): void
    {
        // Las que ya tienen el OK de taller, combustible o viáticos se quedan
        // con sus GR: borrarlas perdería ese registro (y los viáticos no lo
        // dejan).
        $vueltas = Vuelta::query()->delHistorial()
            ->when($tractoId, fn ($query) => $query->where('tracto_id', $tractoId))
            ->whereDoesntHave('revisiones')
            ->whereDoesntHave('abastecimientos')
            ->whereDoesntHave('viaticos')
            ->pluck('id');

        // Primero se sueltan las GR: el `nullOnDelete` de la FK haría lo
        // mismo, pero así no depende de que el motor lo respete (SQLite).
        Viaje::query()->conAnuladas()
            ->whereIn('parada_vuelta_id', ParadaVuelta::query()->whereIn('vuelta_id', $vueltas)->select('id'))
            ->update(['parada_vuelta_id' => null]);

        Vuelta::query()->whereIn('id', $vueltas)->delete();

        // Mientras nadie haya programado una vuelta a mano, rehacer todo el
        // historial vuelve a numerar desde V-00001: si no, cada prueba dejaría
        // los códigos más arriba. Con programadas no se toca, para no repetir
        // un código que ya se le dio a alguien.
        if (DB::getDriverName() === 'pgsql' && ! Vuelta::query()->exists()) {
            DB::statement("select setval(pg_get_serial_sequence('vueltas', 'id'), 1, false)");
        }
    }

    /**
     * La última vuelta del historial de cada unidad queda en curso, salvo que
     * después venga otra (una programada): esa ya terminó.
     */
    private function cerrarLasQuedadasAtras(?int $tractoId): void
    {
        Vuelta::query()->delHistorial()
            ->where('estado', EstadoVuelta::EnCurso)
            ->when($tractoId, fn ($query) => $query->where('tracto_id', $tractoId))
            ->get()
            ->filter(fn (Vuelta $vuelta): bool => Vuelta::query()
                ->where('tracto_id', $vuelta->tracto_id)
                ->where('id', '!=', $vuelta->id)
                ->where('estado', '!=', EstadoVuelta::Anulada)
                ->whereDate('inicio', '>', $vuelta->inicio)
                ->exists())
            ->each(fn (Vuelta $vuelta) => $this->asignador->cerrar($vuelta));
    }

    /**
     * @return array{vueltas: int, paradas: int, vacios: int, guias: int}
     */
    private function resumen(?int $tractoId): array
    {
        $vueltas = Vuelta::query()->delHistorial()
            ->when($tractoId, fn ($query) => $query->where('tracto_id', $tractoId))
            ->select('id');
        $paradas = ParadaVuelta::query()->whereIn('vuelta_id', $vueltas);

        return [
            'vueltas' => Vuelta::query()->whereIn('id', $vueltas)->count(),
            'paradas' => (clone $paradas)->count(),
            'vacios' => (clone $paradas)->where('tipo_carga', TipoCarga::Vacio)->count(),
            'guias' => Viaje::query()->whereIn('parada_vuelta_id', (clone $paradas)->select('id'))->count(),
        ];
    }
}
