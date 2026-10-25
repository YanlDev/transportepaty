<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVuelta;
use App\Enums\ZonaRuta;
use App\Models\Cliente;
use App\Models\ParadaVuelta;
use App\Models\Programacion;
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
    /** Cuántos días antes o después de la salida puede arrancar su vuelta. */
    private const DIAS_DE_HOLGURA = 3;

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
     * Una salida de Programación es la ida particular de una vuelta. Si la
     * unidad ya tiene una vuelta programada para esos días con una ida
     * particular todavía sin GR, la salida se engancha ahí; si no, nace una
     * vuelta nueva con esa parada y el resto se programa después.
     *
     * Al editar la salida se actualiza su parada, salvo que ya tenga GR: eso
     * ya pasó.
     */
    public function enlazarSalida(Programacion $salida): void
    {
        DB::transaction(function () use ($salida): void {
            $parada = $salida->parada;

            if ($parada !== null && $parada->vuelta->tracto_id !== $salida->vehiculo_id) {
                // Cambió la unidad: la salida pasa a la vuelta de la nueva.
                $this->soltarSalida($salida);
                $parada = null;
            }

            $parada ??= $this->paradaLibre($salida);

            if ($parada === null) {
                $vuelta = $this->programar([
                    'tracto_id' => $salida->vehiculo_id,
                    'carreta_id' => null,
                    'conductor_id' => $salida->conductor_id,
                    'inicio' => $salida->fecha->toDateString(),
                    // La parada se completa justo abajo, con los datos de la salida.
                    'paradas' => [[
                        'sentido' => SentidoTramo::Ida->value,
                        'zona_origen' => null,
                        'zona_destino' => null,
                        'tipo_carga' => TipoCarga::Particular->value,
                    ]],
                ]);
                $parada = $vuelta->paradas()->sole();
            }

            if (! $parada->viajes()->exists()) {
                $parada->update($this->paradaDeLaSalida($salida));
                $parada->vuelta->update(['conductor_id' => $salida->conductor_id]);
            }

            if ($salida->parada_vuelta_id !== $parada->id) {
                $salida->forceFill(['parada_vuelta_id' => $parada->id])->saveQuietly();
            }
        });
    }

    /**
     * Al quitar la salida se va su parada, y la vuelta si queda vacía. Lo
     * que ya tiene GR se queda: pasó.
     */
    public function soltarSalida(Programacion $salida): void
    {
        $parada = $salida->parada;

        if ($parada === null || $parada->viajes()->exists()) {
            return;
        }

        $vuelta = $parada->vuelta;
        $parada->delete();
        $salida->forceFill(['parada_vuelta_id' => null])->saveQuietly();

        if ($vuelta->origen === OrigenVuelta::Programacion && ! $vuelta->paradas()->exists()) {
            $vuelta->delete();
        }
    }

    /**
     * Una ida particular sin GR ni salida propia, en una vuelta programada
     * de la unidad que sale en esos días.
     */
    private function paradaLibre(Programacion $salida): ?ParadaVuelta
    {
        return ParadaVuelta::query()
            ->where('sentido', SentidoTramo::Ida)
            ->where('tipo_carga', TipoCarga::Particular)
            ->whereDoesntHave('viajes')
            ->whereNotIn('id', Programacion::query()->whereNotNull('parada_vuelta_id')->select('parada_vuelta_id'))
            ->whereHas('vuelta', fn ($query) => $query
                ->where('tracto_id', $salida->vehiculo_id)
                ->where('origen', OrigenVuelta::Programacion)
                ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
                ->whereBetween('inicio', [
                    $salida->fecha->copy()->subDays(self::DIAS_DE_HOLGURA)->toDateString(),
                    $salida->fecha->copy()->addDays(self::DIAS_DE_HOLGURA)->toDateString(),
                ]))
            ->orderBy('orden')
            ->first();
    }

    /**
     * La parada tal como la describe la salida. El destino se escribe suelto
     * («Juliaca», «Ilo») y de ahí sale la zona; se carga casi siempre en
     * Lima, así que sin lugar de carga reconocible el origen es Lima.
     *
     * @return array<string, mixed>
     */
    private function paradaDeLaSalida(Programacion $salida): array
    {
        $destino = ZonaRuta::desdeDireccion($salida->destino);

        return [
            'sentido' => $destino === null ? SentidoTramo::Ida : SentidoTramo::hacia($destino),
            'zona_origen' => ($salida->lugar_carga ? ZonaRuta::desdeDireccion($salida->lugar_carga) : null) ?? ZonaRuta::Lima,
            'zona_destino' => $destino,
            'lugar_origen' => $salida->lugar_carga ? Str::title(mb_strtolower($salida->lugar_carga)) : null,
            'lugar_destino' => Str::title(mb_strtolower($salida->destino)),
            'tipo_carga' => TipoCarga::Particular,
            'cliente_id' => $salida->cliente_id,
            'cliente' => $salida->cliente->alias,
            'fecha' => $salida->fecha,
        ];
    }

    /**
     * Una programada que todavía no tiene GR se puede quitar; con GR ya es
     * historia y no.
     */
    public function puedeQuitarse(Vuelta $vuelta): bool
    {
        return $vuelta->origen === OrigenVuelta::Programacion && ! $vuelta->viajes()->exists();
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
