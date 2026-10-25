<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVuelta;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Arma las vueltas de cada unidad a partir de sus GR, para que el módulo
 * arranque con la historia de la flota y no vacío.
 *
 * Por tracto y en orden de traslado:
 *
 * 1. Las GR seguidas con el mismo origen, destino y carga, a no más de un día
 *    entre sí, son una sola parada (los materiales de mina salen con varias
 *    GR el mismo día).
 * 2. Si una parada no arranca donde terminó la anterior, la unidad hizo ese
 *    trayecto vacía: se agrega una parada de vacío (Juliaca → mina, Pisco →
 *    Lima…), porque también gasta ruta.
 * 3. Cada parada es de la ida si termina en la sierra y del regreso si
 *    termina en la costa. Una vuelta nueva empieza cada vez que la unidad
 *    vuelve a subir después de haber bajado.
 *
 * Solo toca las vueltas del historial y las GR que no están en una vuelta
 * programada: rehacerlo es seguro y da siempre el mismo resultado.
 */
class ReconstructorVueltas
{
    /** Cuántos días puede haber entre dos GR de la misma parada. */
    private const DIAS_MISMA_PARADA = 1;

    /**
     * @return array{vueltas: int, paradas: int, vacios: int, guias: int}
     */
    public function reconstruir(?int $tractoId = null): array
    {
        return DB::transaction(function () use ($tractoId): array {
            $this->borrarHistorial($tractoId);

            $resumen = ['vueltas' => 0, 'paradas' => 0, 'vacios' => 0, 'guias' => 0];

            $this->guiasSinVuelta($tractoId)
                ->groupBy('tracto_id')
                ->each(function (Collection $guias) use (&$resumen): void {
                    $vueltas = $this->vueltasDe($guias);

                    // La última de cada unidad es la que todavía está andando.
                    foreach ($vueltas as $indice => $paradas) {
                        $estado = $indice === array_key_last($vueltas) ? EstadoVuelta::EnCurso : EstadoVuelta::Cerrada;
                        $this->guardar($paradas, $estado, $resumen);
                    }
                });

            return $resumen;
        });
    }

    private function borrarHistorial(?int $tractoId): void
    {
        $vueltas = Vuelta::query()->delHistorial()
            ->when($tractoId, fn ($query) => $query->where('tracto_id', $tractoId))
            ->pluck('id');

        // Primero se sueltan las GR: el `nullOnDelete` de la FK haría lo
        // mismo, pero así no depende de que el motor lo respete (SQLite).
        Viaje::query()->conAnuladas()
            ->whereIn('parada_vuelta_id', ParadaVuelta::query()->whereIn('vuelta_id', $vueltas)->select('id'))
            ->update(['parada_vuelta_id' => null]);

        Vuelta::query()->whereIn('id', $vueltas)->delete();
    }

    /**
     * @return Collection<int, Viaje>
     */
    private function guiasSinVuelta(?int $tractoId): Collection
    {
        return Viaje::query()
            ->whereNull('parada_vuelta_id')
            ->whereNotNull('tracto_id')
            ->when($tractoId, fn ($query) => $query->where('tracto_id', $tractoId))
            ->orderBy('tracto_id')
            ->orderBy('fecha_traslado')
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->get(['id', 'tracto_id', 'carreta_id', 'conductor_id', 'fecha_traslado', 'tipo_carga', 'origen', 'destino', 'cliente', 'cliente_id']);
    }

    /**
     * Las vueltas de una unidad, cada una como la lista de sus paradas.
     *
     * @param  Collection<int, Viaje>  $guias
     * @return list<list<array<string, mixed>>>
     */
    private function vueltasDe(Collection $guias): array
    {
        $vueltas = [];
        $actual = null;
        $bajando = false;

        foreach ($this->conVacios($this->paradasDe($guias)) as $parada) {
            $sentido = $parada['zona_destino'] !== null
                ? SentidoTramo::hacia($parada['zona_destino'])
                : ($bajando ? SentidoTramo::Regreso : SentidoTramo::Ida);

            if ($actual === null || ($sentido === SentidoTramo::Ida && $bajando)) {
                if ($actual !== null) {
                    $vueltas[] = $actual;
                }
                $actual = [];
                $bajando = false;
            }

            $bajando = $bajando || $sentido === SentidoTramo::Regreso;
            $actual[] = [...$parada, 'sentido' => $sentido];
        }

        if ($actual !== null) {
            $vueltas[] = $actual;
        }

        return $vueltas;
    }

    /**
     * Junta las GR seguidas que son una misma carga.
     *
     * @param  Collection<int, Viaje>  $guias
     * @return list<array<string, mixed>>
     */
    private function paradasDe(Collection $guias): array
    {
        $paradas = [];

        foreach ($guias as $guia) {
            $zonaOrigen = ZonaRuta::desdeDireccion($guia->origen);
            $zonaDestino = ZonaRuta::desdeDireccion($guia->destino);
            $anterior = $paradas === [] ? null : $paradas[array_key_last($paradas)];

            $mismaParada = $anterior !== null
                && $anterior['zona_origen'] === $zonaOrigen
                && $anterior['zona_destino'] === $zonaDestino
                && $anterior['tipo_carga'] === $guia->tipo_carga
                && $anterior['hasta']->diffInDays($guia->fecha_traslado) <= self::DIAS_MISMA_PARADA;

            if ($mismaParada) {
                $paradas[array_key_last($paradas)]['guias'][] = $guia;
                $paradas[array_key_last($paradas)]['hasta'] = $guia->fecha_traslado;

                continue;
            }

            $paradas[] = [
                'zona_origen' => $zonaOrigen,
                'zona_destino' => $zonaDestino,
                'lugar_origen' => $this->lugar($guia->origen),
                'lugar_destino' => $this->lugar($guia->destino),
                'tipo_carga' => $guia->tipo_carga,
                'cliente_id' => $guia->cliente_id,
                'cliente' => $guia->cliente,
                'fecha' => $guia->fecha_traslado,
                'hasta' => $guia->fecha_traslado,
                'guias' => [$guia],
            ];
        }

        return $paradas;
    }

    /**
     * Intercala los trayectos vacíos: donde una parada no arranca en la zona
     * en que terminó la anterior.
     *
     * @param  list<array<string, mixed>>  $paradas
     * @return list<array<string, mixed>>
     */
    private function conVacios(array $paradas): array
    {
        $resultado = [];

        foreach ($paradas as $parada) {
            $anterior = $resultado === [] ? null : $resultado[array_key_last($resultado)];

            if ($anterior !== null
                && $anterior['zona_destino'] !== null
                && $parada['zona_origen'] !== null
                && $anterior['zona_destino'] !== $parada['zona_origen']) {
                $resultado[] = [
                    'zona_origen' => $anterior['zona_destino'],
                    'zona_destino' => $parada['zona_origen'],
                    'lugar_origen' => $anterior['lugar_destino'],
                    'lugar_destino' => $parada['lugar_origen'],
                    'tipo_carga' => TipoCarga::Vacio,
                    'cliente_id' => null,
                    'cliente' => null,
                    'fecha' => null,
                    'hasta' => $anterior['hasta'],
                    'guias' => [],
                ];
            }

            $resultado[] = $parada;
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $paradas
     * @param  array{vueltas: int, paradas: int, vacios: int, guias: int}  $resumen
     */
    private function guardar(array $paradas, EstadoVuelta $estado, array &$resumen): void
    {
        $guias = collect($paradas)->flatMap(fn (array $parada): array => $parada['guias']);
        /** @var Viaje $primera */
        $primera = $guias->first();
        $fechas = collect($paradas)->pluck('fecha')->filter();

        $vuelta = Vuelta::query()->create([
            'tracto_id' => $primera->tracto_id,
            'carreta_id' => $primera->carreta_id,
            'conductor_id' => $primera->conductor_id,
            'tipo' => $guias->contains(fn (Viaje $guia): bool => $guia->tipo_carga !== TipoCarga::Particular)
                ? TipoVuelta::Mina
                : TipoVuelta::Particular,
            'estado' => $estado,
            'origen' => OrigenVuelta::Historial,
            'inicio' => $fechas->min(),
            'fin' => $estado === EstadoVuelta::Cerrada ? $guias->max('fecha_traslado') : null,
        ]);

        foreach ($paradas as $orden => $parada) {
            $registro = $vuelta->paradas()->create([
                'sentido' => $parada['sentido'],
                'orden' => $orden + 1,
                'zona_origen' => $parada['zona_origen'],
                'zona_destino' => $parada['zona_destino'],
                'lugar_origen' => $parada['lugar_origen'],
                'lugar_destino' => $parada['lugar_destino'],
                'tipo_carga' => $parada['tipo_carga'],
                'cliente_id' => $parada['cliente_id'],
                'cliente' => $parada['cliente'],
                'fecha' => $parada['fecha'],
            ]);

            if ($parada['guias'] !== []) {
                Viaje::query()->conAnuladas()
                    ->whereKey(array_map(fn (Viaje $guia): int => $guia->id, $parada['guias']))
                    ->update(['parada_vuelta_id' => $registro->id]);
            }

            $resumen['paradas']++;
            $resumen['vacios'] += $parada['tipo_carga'] === TipoCarga::Vacio ? 1 : 0;
        }

        $resumen['vueltas']++;
        $resumen['guias'] += $guias->count();
    }

    /**
     * El distrito de una dirección de GR («… - PARACAS - PISCO - ICA» →
     * Paracas). Las GR terminan siempre en distrito, provincia y departamento.
     */
    private function lugar(string $direccion): ?string
    {
        $partes = array_map('trim', explode(' - ', $direccion));

        if (count($partes) < 3) {
            return null;
        }

        return Str::limit(Str::title(mb_strtolower($partes[count($partes) - 3])), 100, '');
    }
}
