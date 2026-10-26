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

    /**
     * Hasta cuántos días antes pudo arrancar la vuelta a la que se le agrega
     * un regreso: las vueltas duran 11 a 15 días, con un margen.
     */
    private const DIAS_DE_UNA_VUELTA = 20;

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
     * Una salida de Programación es una parada particular de una vuelta. Si
     * sube a la sierra (Lima → Juliaca, lo de casi siempre) es el regreso de
     * la vuelta en la que ya anda la unidad y se le agrega ahí; si baja
     * (Juliaca → Lima) es la ida, y se engancha a la vuelta programada para
     * esos días o hace nacer una nueva.
     *
     * Al editar la salida se actualiza su parada, salvo que ya tenga GR: eso
     * ya pasó.
     */
    public function enlazarSalida(Programacion $salida): void
    {
        DB::transaction(function () use ($salida): void {
            $sentido = $this->sentidoDeLaSalida($salida);
            $parada = $salida->parada;

            if ($parada !== null && ($parada->vuelta->tracto_id !== $salida->vehiculo_id || $parada->sentido !== $sentido)) {
                // Cambió la unidad o el sentido: la salida va a otra vuelta.
                $this->soltarSalida($salida);
                $parada = null;
            }

            $parada ??= $this->paradaLibre($salida, $sentido)
                ?? $this->paradaEnLaVueltaEnCurso($salida, $sentido)
                ?? $this->paradaEnVueltaNueva($salida, $sentido);

            if (! $parada->viajes()->exists()) {
                $parada->update($this->paradaDeLaSalida($salida, $sentido));
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
     * Una parada particular del mismo sentido, sin GR ni salida propia, en
     * una vuelta programada de la unidad que arranca en esos días.
     */
    private function paradaLibre(Programacion $salida, SentidoTramo $sentido): ?ParadaVuelta
    {
        return ParadaVuelta::query()
            ->where('sentido', $sentido)
            ->where('tipo_carga', TipoCarga::Particular)
            ->whereDoesntHave('viajes')
            ->whereNotIn('id', Programacion::query()->whereNotNull('parada_vuelta_id')->select('parada_vuelta_id'))
            ->whereIn('vuelta_id', Vuelta::query()
                ->abiertasDeLaUnidad($salida->vehiculo_id, $salida->fecha->copy()->addDays(self::DIAS_DE_HOLGURA))
                ->whereDate('inicio', '>=', $salida->fecha->copy()->subDays(
                    $sentido === SentidoTramo::Regreso ? self::DIAS_DE_UNA_VUELTA : self::DIAS_DE_HOLGURA,
                )->toDateString())
                ->select('id'))
            ->orderBy('orden')
            ->first();
    }

    /**
     * El regreso que se programa con la unidad ya en la costa: se le agrega
     * a la vuelta en la que anda, si todavía no volvió a subir. Si esa vuelta
     * era del historial, pasa a ser programada: desde ahora alguien la
     * planifica.
     */
    private function paradaEnLaVueltaEnCurso(Programacion $salida, SentidoTramo $sentido): ?ParadaVuelta
    {
        if ($sentido !== SentidoTramo::Regreso) {
            return null;
        }

        $vuelta = Vuelta::query()
            ->abiertasDeLaUnidad($salida->vehiculo_id, $salida->fecha->copy()->addDays(self::DIAS_DE_HOLGURA))
            ->whereDate('inicio', '>=', $salida->fecha->copy()->subDays(self::DIAS_DE_UNA_VUELTA)->toDateString())
            ->whereDoesntHave('paradas', fn ($query) => $query
                ->where('sentido', SentidoTramo::Regreso)
                ->whereHas('viajes'))
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->first();

        if ($vuelta === null) {
            return null;
        }

        if ($vuelta->origen === OrigenVuelta::Historial) {
            $vuelta->update(['origen' => OrigenVuelta::Programacion]);
        }

        return $vuelta->paradas()->create([
            'sentido' => SentidoTramo::Regreso,
            'orden' => (int) $vuelta->paradas()->reorder()->max('orden') + 1,
            'tipo_carga' => TipoCarga::Particular,
        ]);
    }

    private function paradaEnVueltaNueva(Programacion $salida, SentidoTramo $sentido): ParadaVuelta
    {
        $vuelta = $this->programar([
            'tracto_id' => $salida->vehiculo_id,
            'carreta_id' => null,
            'conductor_id' => $salida->conductor_id,
            'inicio' => $salida->fecha->toDateString(),
            // La parada se completa después, con los datos de la salida.
            'paradas' => [[
                'sentido' => $sentido->value,
                'zona_origen' => null,
                'zona_destino' => null,
                'tipo_carga' => TipoCarga::Particular->value,
            ]],
        ]);

        return $vuelta->paradas()->sole();
    }

    /**
     * Sube si va a la sierra; sin destino reconocible se asume que sube,
     * que es lo que hace casi toda la carga particular (Lima → Juliaca).
     */
    private function sentidoDeLaSalida(Programacion $salida): SentidoTramo
    {
        $destino = ZonaRuta::desdeDireccion($salida->destino);

        return $destino === null ? SentidoTramo::Regreso : SentidoTramo::hacia($destino);
    }

    /**
     * La parada tal como la describe la salida. El destino se escribe suelto
     * («Juliaca», «Ilo») y de ahí sale la zona. Sin lugar de carga
     * reconocible, el origen es Lima si la carga sube y Juliaca si baja.
     *
     * @return array<string, mixed>
     */
    private function paradaDeLaSalida(Programacion $salida, SentidoTramo $sentido): array
    {
        $origenPorDefecto = $sentido === SentidoTramo::Regreso ? ZonaRuta::Lima : ZonaRuta::Juliaca;

        return [
            'sentido' => $sentido,
            'zona_origen' => ($salida->lugar_carga ? ZonaRuta::desdeDireccion($salida->lugar_carga) : null) ?? $origenPorDefecto,
            'zona_destino' => ZonaRuta::desdeDireccion($salida->destino),
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
