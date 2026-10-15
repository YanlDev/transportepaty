<?php

namespace App\Services;

use App\Enums\EstadoMantenimiento;
use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\ServicioMantenimiento;
use App\Models\Vehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Qué le toca a cada unidad según el plan de su marca.
 *
 * Nada de esto se guarda: sale del último kilometraje y de las preventivas
 * finalizadas, así que registrar un km o cerrar una orden lo actualiza solo.
 *
 * - **Km actual:** el mayor entre las lecturas y las órdenes de la unidad.
 * - **Base de un servicio:** el km de la última preventiva finalizada de ese
 *   nivel o mayor. Una L reinicia la M, la S y la X porque las incluye.
 * - **Próximo:** base + intervalo. Faltan = próximo − km actual. Si el
 *   servicio tiene intervalo en meses, vence también por fecha, lo que llegue
 *   primero.
 * - **Sin datos:** sin km o sin ninguna preventiva de ese nivel no hay de
 *   dónde contar, y se dice así en vez de suponer.
 *
 * @phpstan-type EstadoServicio array{
 *     id: int,
 *     codigo: string,
 *     nombre: string,
 *     nivel: int,
 *     intervalo_km: int,
 *     intervalo_meses: int|null,
 *     ultimo_km: int|null,
 *     ultima_fecha: string|null,
 *     proximo_km: int|null,
 *     faltan_km: int|null,
 *     vence_el: string|null,
 *     estado: EstadoMantenimiento,
 * }
 * @phpstan-type EstadoUnidad array{
 *     plan: array{id: int, nombre: string}|null,
 *     km_actual: int|null,
 *     km_fecha: string|null,
 *     estado: EstadoMantenimiento,
 *     proximo: EstadoServicio|null,
 *     servicios: list<EstadoServicio>,
 * }
 */
class ProximoMantenimiento
{
    /**
     * @return EstadoUnidad
     */
    public function paraVehiculo(Vehiculo $vehiculo): array
    {
        return $this->paraFlota(collect([$vehiculo]))[$vehiculo->id];
    }

    /**
     * Lo mismo para varias unidades con un puñado de consultas en total, no
     * por unidad: el semáforo de la flota lo pide para todas a la vez.
     *
     * @param  Collection<int, Vehiculo>  $vehiculos
     * @return array<int, EstadoUnidad>
     */
    public function paraFlota(Collection $vehiculos): array
    {
        $ids = $vehiculos->pluck('id')->all();

        $planes = PlanMantenimiento::query()->with('servicios')->get()->keyBy('marca');

        $lecturas = LecturaKilometraje::query()
            ->whereIn('vehiculo_id', $ids)
            ->groupBy('vehiculo_id')
            ->selectRaw('vehiculo_id, max(kilometraje) as km, max(leido_en) as ultima')
            ->get()
            ->keyBy('vehiculo_id');

        $ordenes = OrdenTrabajo::query()
            ->whereIn('vehiculo_id', $ids)
            ->whereNotNull('kilometraje')
            ->groupBy('vehiculo_id')
            ->selectRaw('vehiculo_id, max(kilometraje) as km, max(fecha) as ultima')
            ->get()
            ->keyBy('vehiculo_id');

        $preventivas = OrdenTrabajo::query()
            ->whereIn('vehiculo_id', $ids)
            ->where('tipo', TipoOrdenTrabajo::Preventiva)
            ->where('estado', EstadoOrdenTrabajo::Finalizada)
            ->whereNotNull('kilometraje')
            ->whereNotNull('servicio_mantenimiento_id')
            ->with('servicio:id,nivel,plan_mantenimiento_id')
            ->get()
            ->groupBy('vehiculo_id');

        $resultado = [];

        foreach ($vehiculos as $vehiculo) {
            $plan = $vehiculo->marca === null ? null : $planes->get(mb_strtoupper(trim($vehiculo->marca)));

            [$km, $kmFecha] = $this->kmActual($lecturas->get($vehiculo->id), $ordenes->get($vehiculo->id));

            $servicios = $plan === null
                ? []
                : array_values($plan->servicios
                    ->map(fn (ServicioMantenimiento $servicio): array => $this->estadoServicio(
                        $servicio,
                        $km,
                        $preventivas->get($vehiculo->id, collect())
                            ->filter(fn (OrdenTrabajo $orden): bool => $orden->servicio !== null
                                && $orden->servicio->plan_mantenimiento_id === $plan->id
                                && $orden->servicio->nivel >= $servicio->nivel),
                    ))
                    ->all());

            $resultado[$vehiculo->id] = [
                'plan' => $plan === null ? null : ['id' => $plan->id, 'nombre' => $plan->nombre],
                'km_actual' => $km,
                'km_fecha' => $kmFecha,
                'estado' => $this->peorEstado($servicios),
                'proximo' => $this->proximo($servicios),
                'servicios' => $servicios,
            ];
        }

        return $resultado;
    }

    /**
     * El mayor km conocido y cuándo se anotó por última vez, entre lecturas
     * y órdenes: una orden cargada con su km también dice dónde va la unidad.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function kmActual(?LecturaKilometraje $lectura, ?OrdenTrabajo $orden): array
    {
        $kmLectura = $lectura === null ? null : (int) $lectura->getAttribute('km');
        $kmOrden = $orden === null ? null : (int) $orden->getAttribute('km');

        if ($kmLectura === null && $kmOrden === null) {
            return [null, null];
        }

        $fechas = array_filter([
            $lectura?->getAttribute('ultima'),
            $orden?->getAttribute('ultima'),
        ]);

        $fecha = collect($fechas)
            ->map(fn (string $fecha): CarbonImmutable => CarbonImmutable::parse($fecha))
            ->max();

        return [max((int) $kmLectura, (int) $kmOrden), $fecha?->toDateString()];
    }

    /**
     * @param  Collection<int, OrdenTrabajo>  $hechas  preventivas finalizadas de este nivel o mayor
     * @return EstadoServicio
     */
    private function estadoServicio(ServicioMantenimiento $servicio, ?int $km, Collection $hechas): array
    {
        $ultima = $hechas->sortByDesc('kilometraje')->first();

        $proximoKm = $ultima === null ? null : $ultima->kilometraje + $servicio->intervalo_km;
        $faltan = $proximoKm === null || $km === null ? null : $proximoKm - $km;

        $venceEl = $ultima !== null && $servicio->intervalo_meses !== null
            ? $ultima->fecha->copy()->addMonths($servicio->intervalo_meses)->toDateString()
            : null;

        return [
            'id' => $servicio->id,
            'codigo' => $servicio->codigo,
            'nombre' => $servicio->nombre,
            'nivel' => $servicio->nivel,
            'intervalo_km' => $servicio->intervalo_km,
            'intervalo_meses' => $servicio->intervalo_meses,
            'ultimo_km' => $ultima?->kilometraje,
            'ultima_fecha' => $ultima?->fecha->toDateString(),
            'proximo_km' => $proximoKm,
            'faltan_km' => $faltan,
            'vence_el' => $venceEl,
            'estado' => $this->estadoDe($faltan, $venceEl),
        ];
    }

    private function estadoDe(?int $faltan, ?string $venceEl): EstadoMantenimiento
    {
        if ($faltan === null) {
            return EstadoMantenimiento::SinDatos;
        }

        $diasParaVencer = $venceEl === null
            ? null
            : (int) RelojOperativo::fechaDeHoy()->diffInDays(CarbonImmutable::parse($venceEl), absolute: false);

        if ($faltan <= 0 || ($diasParaVencer !== null && $diasParaVencer <= 0)) {
            return EstadoMantenimiento::Vencido;
        }

        if ($faltan <= (int) config('transpaty.mantenimiento.aviso_km')
            || ($diasParaVencer !== null && $diasParaVencer <= (int) config('transpaty.mantenimiento.aviso_dias'))) {
            return EstadoMantenimiento::Proximo;
        }

        return EstadoMantenimiento::AlDia;
    }

    /**
     * El peor estado entre los servicios con datos. Sin ninguno, la unidad
     * entera queda «sin datos».
     *
     * @param  list<EstadoServicio>  $servicios
     */
    private function peorEstado(array $servicios): EstadoMantenimiento
    {
        return collect($servicios)
            ->pluck('estado')
            ->reject(fn (EstadoMantenimiento $estado): bool => $estado === EstadoMantenimiento::SinDatos)
            ->sortBy(fn (EstadoMantenimiento $estado): int => $estado->prioridad())
            ->first() ?? EstadoMantenimiento::SinDatos;
    }

    /**
     * El servicio que toca antes. Si varios tocan a la vez —la X y la S a los
     * 40 mil, o las dos ya vencidas—, el de nivel mayor, que incluye a los
     * otros: hacer la X cuando ya toca la S sería hacer el servicio a medias.
     *
     * @param  list<EstadoServicio>  $servicios
     * @return EstadoServicio|null
     */
    private function proximo(array $servicios): ?array
    {
        $conDatos = collect($servicios)->filter(fn (array $servicio): bool => $servicio['proximo_km'] !== null);

        if ($conDatos->isEmpty()) {
            return null;
        }

        // Todo lo que vence hasta el primero que toca, o hasta el km actual si
        // ya se pasó: eso es lo que hay que hacer en la próxima entrada.
        $hasta = max(
            $conDatos->min('proximo_km'),
            $conDatos->max(fn (array $servicio): int => $servicio['proximo_km'] - (int) $servicio['faltan_km']),
        );

        return $conDatos
            ->filter(fn (array $servicio): bool => $servicio['proximo_km'] <= $hasta)
            ->sortByDesc('nivel')
            ->first();
    }
}
