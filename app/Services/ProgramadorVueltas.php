<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVuelta;
use App\Models\Cliente;
use App\Models\ParadaVuelta;
use App\Models\Vuelta;
use Illuminate\Support\Facades\DB;

/**
 * Programar una vuelta y corregirla en el camino.
 *
 * Las paradas que ya tienen GR son lo que pasó: no se cambian ni se quitan,
 * solo se reordenan junto con las demás. Todo lo que todavía no ocurrió se
 * puede editar, porque en Pisco o en Callao se decide qué sigue.
 */
class ProgramadorVueltas
{
    public function __construct(private readonly AsignadorVueltas $asignador) {}

    /**
     * @param  array{tracto_id: int, carreta_id: int|null, conductor_id: int|null, inicio: string, paradas: list<array<string, mixed>>}  $datos
     */
    public function programar(array $datos): Vuelta
    {
        return DB::transaction(function () use ($datos): Vuelta {
            // Programar la siguiente da por terminada la que estaba andando.
            Vuelta::query()
                ->where('tracto_id', $datos['tracto_id'])
                ->where('estado', EstadoVuelta::EnCurso)
                ->whereDate('inicio', '<', $datos['inicio'])
                ->get()
                ->each(fn (Vuelta $vuelta) => $this->asignador->cerrar($vuelta));

            $vuelta = Vuelta::query()->create([
                'tracto_id' => $datos['tracto_id'],
                'carreta_id' => $datos['carreta_id'],
                'conductor_id' => $datos['conductor_id'],
                'tipo' => $this->tipo($datos['paradas']),
                'estado' => EstadoVuelta::Programada,
                'origen' => OrigenVuelta::Programacion,
                'inicio' => $datos['inicio'],
            ]);

            $this->guardarParadas($vuelta, $datos['paradas']);

            return $vuelta;
        });
    }

    /**
     * @param  array{tracto_id: int, carreta_id: int|null, conductor_id: int|null, inicio: string, paradas: list<array<string, mixed>>}  $datos
     */
    public function actualizar(Vuelta $vuelta, array $datos): Vuelta
    {
        return DB::transaction(function () use ($vuelta, $datos): Vuelta {
            $tieneGuias = $vuelta->viajes()->exists();

            $vuelta->update([
                // La unidad de una vuelta que ya tiene GR es la de esas GR.
                'tracto_id' => $tieneGuias ? $vuelta->tracto_id : $datos['tracto_id'],
                'carreta_id' => $datos['carreta_id'],
                'conductor_id' => $datos['conductor_id'],
                'inicio' => $datos['inicio'],
                'tipo' => $this->tipo($datos['paradas']),
            ]);

            $this->guardarParadas($vuelta, $datos['paradas']);

            return $vuelta;
        });
    }

    /**
     * Una programada que todavía no tiene GR se puede quitar; con GR ya es
     * historia y no. Tampoco si ya se le autorizó combustible o se le
     * depositó un viático: ese gasto quedaría suelto, sin ticket.
     */
    public function puedeQuitarse(Vuelta $vuelta): bool
    {
        return $vuelta->origen === OrigenVuelta::Programacion
            && ! $vuelta->viajes()->exists()
            && ! $vuelta->abastecimientos()->exists()
            && ! $vuelta->viaticos()->exists();
    }

    /**
     * @param  list<array<string, mixed>>  $paradas
     */
    private function guardarParadas(Vuelta $vuelta, array $paradas): void
    {
        $existentes = $vuelta->paradas()->withCount('viajes')->get()->keyBy('id');

        // La ida va antes que el regreso; dentro de cada tramo, en el orden
        // en que se cargaron.
        $ordenadas = collect($paradas)
            ->sortBy(fn (array $parada, int $indice): int => ($parada['sentido'] === SentidoTramo::Regreso->value ? 1000 : 0) + $indice)
            ->values();

        $conservadas = [];

        foreach ($ordenadas as $indice => $parada) {
            /** @var ParadaVuelta|null $existente */
            $existente = isset($parada['id']) ? $existentes->get((int) $parada['id']) : null;

            if ($existente !== null && $existente->viajes_count > 0) {
                $existente->update(['orden' => $indice + 1]);
                $conservadas[] = $existente->id;

                continue;
            }

            $clienteId = $parada['tipo_carga'] === TipoCarga::Vacio->value ? null : ($parada['cliente_id'] ?? null);
            $campos = [
                'sentido' => $parada['sentido'],
                'orden' => $indice + 1,
                'zona_origen' => $parada['zona_origen'],
                'zona_destino' => $parada['zona_destino'],
                'lugar_origen' => $parada['lugar_origen'] ?? null,
                'lugar_destino' => $parada['lugar_destino'] ?? null,
                'tipo_carga' => $parada['tipo_carga'],
                'cliente_id' => $clienteId,
                'cliente' => $clienteId ? Cliente::query()->whereKey($clienteId)->value('alias') : null,
            ];

            if ($existente !== null) {
                $existente->update($campos);
                $conservadas[] = $existente->id;

                continue;
            }

            $conservadas[] = $vuelta->paradas()->create($campos)->id;
        }

        // Lo que se sacó del formulario se borra, salvo que ya tenga GR: eso
        // pasó y se queda, al final.
        $siguiente = count($conservadas);

        foreach ($existentes->except($conservadas) as $parada) {
            if ($parada->viajes_count > 0) {
                $parada->update(['orden' => ++$siguiente]);

                continue;
            }

            $parada->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $paradas
     */
    private function tipo(array $paradas): TipoVuelta
    {
        $deMina = collect($paradas)->contains(fn (array $parada): bool => ! in_array(
            $parada['tipo_carga'],
            [TipoCarga::Particular->value, TipoCarga::Vacio->value],
            true,
        ));

        return $deMina ? TipoVuelta::Mina : TipoVuelta::Particular;
    }
}
