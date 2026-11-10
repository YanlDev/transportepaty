<?php

namespace App\Services;

use App\Enums\EstadoLlanta;
use App\Enums\EstadoMantenimiento;
use App\Enums\EstadoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use Illuminate\Support\Collection;

/**
 * Lo que taller necesita saber de una unidad antes de darle el apto, sin
 * salir de la bandeja: si tiene una orden de trabajo abierta (que bloquea el
 * apto), cómo va frente a su preventivo y si tiene llantas por cambiar.
 *
 * No guarda nada: junta lo que ya calculan `ProximoMantenimiento` y
 * `EstadoLlantas`, para todas las unidades de la bandeja a la vez.
 *
 * @phpstan-type Aviso array{tipo: 'orden'|'preventivo'|'llantas', tono: 'mal'|'aviso', texto: string, url: string}
 * @phpstan-type AvisosDeUnidad array{km_actual: int|null, avisos: list<Aviso>}
 */
class AvisosDeTaller
{
    public function __construct(
        private readonly ProximoMantenimiento $mantenimiento,
        private readonly EstadoLlantas $llantas,
        private readonly MaterialesDeOrden $materiales,
    ) {}

    /**
     * Las órdenes de trabajo sin terminar: son las unidades que están en
     * taller, tengan ticket o no. De una preventiva se dice cuántos materiales
     * de su nivel todavía no salieron del almacén.
     *
     * @return list<array{id: int, numero: string, vehiculo_id: int, placa: string, tipo: string, servicio: string|null, estado: string, fecha: string, trabajo: string|null, faltan: int}>
     */
    public function ordenesAbiertas(): array
    {
        return array_values(OrdenTrabajo::query()
            ->vigentes()
            ->where('estado', '!=', EstadoOrdenTrabajo::Finalizada)
            ->with(['vehiculo:id,placa', 'servicio'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (OrdenTrabajo $orden): array => [
                'id' => $orden->id,
                'numero' => $orden->numero(),
                'vehiculo_id' => $orden->vehiculo_id,
                'placa' => $orden->vehiculo->placa,
                'tipo' => $orden->tipo->label(),
                'servicio' => $orden->servicio?->codigo,
                'estado' => $orden->estado->label(),
                'fecha' => $orden->fecha->toDateString(),
                'trabajo' => $orden->trabajo,
                'faltan' => $orden->servicio === null ? 0 : count($this->materiales->faltantes($orden->servicio, $orden)),
            ])
            ->all());
    }

    /**
     * @param  Collection<int, Vehiculo>  $vehiculos  con `id`, `placa`, `marca` y `tipo`
     * @return array<int, AvisosDeUnidad>
     */
    public function paraFlota(Collection $vehiculos): array
    {
        $vehiculos = $vehiculos->unique('id')->values();

        if ($vehiculos->isEmpty()) {
            return [];
        }

        $preventivo = $this->mantenimiento->paraFlota($vehiculos);
        $llantas = $this->llantas->paraFlota($vehiculos);
        $abiertas = OrdenTrabajo::query()
            ->vigentes()
            ->whereIn('vehiculo_id', $vehiculos->pluck('id'))
            ->where('estado', '!=', EstadoOrdenTrabajo::Finalizada)
            ->orderBy('id')
            ->get(['id', 'vehiculo_id', 'estado'])
            ->groupBy('vehiculo_id');

        $resultado = [];

        foreach ($vehiculos as $vehiculo) {
            $avisos = [];

            foreach ($abiertas->get($vehiculo->id, []) as $orden) {
                $avisos[] = [
                    'tipo' => 'orden',
                    'tono' => 'mal',
                    'texto' => "{$orden->numero()} abierta",
                    'url' => route('mantenimiento.ordenes.edit', $orden, absolute: false),
                ];
            }

            $estado = $preventivo[$vehiculo->id];
            $aviso = $this->avisoDePreventivo($vehiculo, $estado['estado'], $estado['proximo']);

            if ($aviso !== null) {
                $avisos[] = $aviso;
            }

            array_push($avisos, ...$this->avisosDeLlantas($vehiculo, $llantas[$vehiculo->id] ?? []));

            $resultado[$vehiculo->id] = ['km_actual' => $estado['km_actual'], 'avisos' => $avisos];
        }

        return $resultado;
    }

    /**
     * @param  array{codigo: string, faltan_km: int|null, vence_el: string|null}|null  $proximo
     * @return Aviso|null
     */
    private function avisoDePreventivo(Vehiculo $vehiculo, EstadoMantenimiento $estado, ?array $proximo): ?array
    {
        if ($proximo === null || ! in_array($estado, [EstadoMantenimiento::Vencido, EstadoMantenimiento::Proximo], true)) {
            return null;
        }

        $cuanto = match (true) {
            $estado === EstadoMantenimiento::Vencido && $proximo['faltan_km'] !== null && $proximo['faltan_km'] < 0 => 'vencido por '.number_format(abs($proximo['faltan_km']), 0, '.', ' ').' km',
            $estado === EstadoMantenimiento::Vencido => 'vencido',
            $proximo['faltan_km'] !== null => 'en '.number_format($proximo['faltan_km'], 0, '.', ' ').' km',
            default => 'próximo',
        };

        return [
            'tipo' => 'preventivo',
            'tono' => $estado === EstadoMantenimiento::Vencido ? 'mal' : 'aviso',
            'texto' => "Preventivo {$proximo['codigo']} {$cuanto}",
            'url' => route('mantenimiento.show', $vehiculo, absolute: false),
        ];
    }

    /**
     * @param  list<array{estado: EstadoLlanta|null}>  $posiciones
     * @return list<Aviso>
     */
    private function avisosDeLlantas(Vehiculo $vehiculo, array $posiciones): array
    {
        $url = route('mantenimiento.llantas.show', $vehiculo, absolute: false);
        $avisos = [];

        foreach ([[EstadoLlanta::Cambiar, 'mal', 'por cambiar'], [EstadoLlanta::Revisar, 'aviso', 'por revisar']] as [$estado, $tono, $que]) {
            $cuantas = count(array_filter($posiciones, fn (array $posicion): bool => $posicion['estado'] === $estado));

            if ($cuantas > 0) {
                $avisos[] = [
                    'tipo' => 'llantas',
                    'tono' => $tono,
                    'texto' => $cuantas === 1 ? "1 llanta {$que}" : "{$cuantas} llantas {$que}",
                    'url' => $url,
                ];
            }
        }

        return $avisos;
    }
}
