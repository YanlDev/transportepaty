<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVuelta;
use App\Models\Cliente;
use App\Models\ParadaVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\User;
use App\Models\Vuelta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Programar una vuelta y corregirla en el camino.
 *
 * Las paradas que ya tienen GR son lo que pasó: no se cambian ni se quitan,
 * solo se reordenan junto con las demás. Todo lo que todavía no ocurrió se
 * puede editar, porque en Pisco o en Callao se decide qué sigue.
 */
class ProgramadorVueltas
{
    /**
     * @param  array{tracto_id: int, carreta_id: int|null, conductor_id: int|null, inicio: string, paradas: list<array<string, mixed>>, rutas?: list<array{sentido: string, ruta_id: int, variante_ruta_id: int|null}>}  $datos
     */
    public function programar(array $datos, ?User $usuario = null): Vuelta
    {
        return DB::transaction(function () use ($datos, $usuario): Vuelta {
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
            $this->guardarRutas($vuelta, $datos['rutas'] ?? [], $usuario);

            return $vuelta;
        });
    }

    /**
     * @param  array{tracto_id: int, carreta_id: int|null, conductor_id: int|null, inicio: string, paradas: list<array<string, mixed>>, rutas?: list<array{sentido: string, ruta_id: int, variante_ruta_id: int|null}>}  $datos
     */
    public function actualizar(Vuelta $vuelta, array $datos, ?User $usuario = null): Vuelta
    {
        return DB::transaction(function () use ($vuelta, $datos, $usuario): Vuelta {
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
            $this->guardarRutas($vuelta, $datos['rutas'] ?? [], $usuario);

            return $vuelta;
        });
    }

    /**
     * Las rutas de cada tramo, copiadas al ticket. La que ya estaba con la
     * misma variante se conserva tal cual (lo autorizado no se refresca con
     * el catálogo); la nueva, o la que cambió de variante, se copia como
     * está ahora. La que se sacó se quita.
     *
     * @param  list<array{sentido: string, ruta_id: int, variante_ruta_id: int|null}>  $elegidas
     */
    private function guardarRutas(Vuelta $vuelta, array $elegidas, ?User $usuario): void
    {
        // Los adicionales no los elige el formulario: se agregan aparte y
        // editar el ticket no los toca.
        $existentes = $vuelta->rutas()->whereNull('motivo')->get();
        $catalogo = Ruta::query()->with(['lineas.punto', 'variantes'])->findMany(array_column($elegidas, 'ruta_id'))->keyBy('id');
        $conservadas = [];

        foreach ($elegidas as $orden => $elegida) {
            $ruta = $catalogo->get($elegida['ruta_id']);

            if ($ruta === null) {
                continue;
            }

            $existente = $existentes->first(fn (RutaVuelta $rutaVuelta): bool => $rutaVuelta->sentido->value === $elegida['sentido']
                && $rutaVuelta->ruta_id === $ruta->id
                && ! in_array($rutaVuelta->id, $conservadas, true));
            $mismaVariante = $existente !== null
                && ($elegida['variante_ruta_id'] === null || $existente->variante_ruta_id === $elegida['variante_ruta_id']);

            if ($mismaVariante) {
                $existente->update(['orden' => $orden + 1]);
                $conservadas[] = $existente->id;

                continue;
            }

            $variante = $ruta->variantes->firstWhere('id', $elegida['variante_ruta_id']);
            $datos = [...RutaVuelta::copiaDe($ruta, $variante), 'sentido' => $elegida['sentido'], 'orden' => $orden + 1, 'user_id' => $usuario?->id];

            if ($existente !== null) {
                $existente->update($datos);
                $conservadas[] = $existente->id;
            } else {
                $conservadas[] = $vuelta->rutas()->create($datos)->id;
            }
        }

        $existentes->reject(fn (RutaVuelta $rutaVuelta): bool => in_array($rutaVuelta->id, $conservadas, true))->each->delete();
        $vuelta->unsetRelation('rutas');
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

            $vacia = $parada['tipo_carga'] === TipoCarga::Vacio->value;
            $clienteId = $vacia ? null : ($parada['cliente_id'] ?? null);
            // Sin cliente del padrón vale el nombre escrito a mano.
            $escrito = $vacia || blank($parada['cliente'] ?? null) ? null : Str::squish((string) $parada['cliente']);
            $campos = [
                'sentido' => $parada['sentido'],
                'orden' => $indice + 1,
                'zona_origen' => $parada['zona_origen'],
                'zona_destino' => $parada['zona_destino'],
                'lugar_origen' => $parada['lugar_origen'] ?? null,
                'lugar_destino' => $parada['lugar_destino'] ?? null,
                'tipo_carga' => $parada['tipo_carga'],
                'cliente_id' => $clienteId,
                'cliente' => $clienteId ? Cliente::query()->whereKey($clienteId)->value('alias') : $escrito,
                'viaje_continuado_id' => $parada['viaje_continuado_id'] ?? null,
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
