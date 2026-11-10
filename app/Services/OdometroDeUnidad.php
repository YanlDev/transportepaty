<?php

namespace App\Services;

use App\Models\LecturaKilometraje;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El kilometraje que se anota en el circuito del ticket (OK de taller,
 * despacho de combustible, llegada a la base) es también una lectura para
 * mantenimiento: así el próximo servicio se calcula con el último km real.
 *
 * Cada paso anota una sola lectura, identificada por su origen
 * («abastecimiento:12», «llegada:5», «salida:5»): corregir un km mal
 * tipeado corrige esa lectura, no suma otra que la deje para siempre.
 */
class OdometroDeUnidad
{
    public static function deDespacho(int $abastecimientoId): string
    {
        return "abastecimiento:{$abastecimientoId}";
    }

    public static function deLlegada(int $vueltaId): string
    {
        return "llegada:{$vueltaId}";
    }

    public static function deSalida(int $vueltaId): string
    {
        return "salida:{$vueltaId}";
    }

    /**
     * Cuánto puede subir el odómetro entre una lectura y la siguiente. Más
     * que eso no es un viaje: es un dígito de más («1 500 000» por
     * «150 000»), y aceptado dejaría a la unidad sin poder anotar ningún km
     * real después.
     */
    public const SALTO_MAXIMO = 500_000;

    /**
     * Por qué el km no se acepta, o null si está bien. El odómetro no
     * retrocede: un km menor que el anterior es un error de tipeo o de
     * placa. La lectura que se está corrigiendo (su origen) no cuenta.
     *
     * Se compara por fecha y no contra el mayor km de la historia: un
     * voucher que se registra tarde trae un km real, menor que el de una
     * llegada posterior ya anotada, y tiene que entrar. El km debe quedar
     * entre lo anotado antes de su día y lo anotado después. En una fecha
     * pasada, lo de ese mismo día no se compara: el orden dentro del día ya
     * no se sabe.
     */
    public function error(Vehiculo $vehiculo, int $kilometraje, ?string $origen = null, ?CarbonInterface $fecha = null): ?string
    {
        // El día es el de la operación (Lima). Una fecha que llega sola
        // (la del voucher, la de la llegada) ya es ese día.
        $dia = $fecha?->toDateString() ?? RelojOperativo::hoy();
        $conocidos = $this->conocidos($vehiculo, $origen);

        // Lo que se anota hoy viene después de todo lo conocido, también de
        // lo de hoy: en el día se tipea en el orden en que pasa.
        $esDeAhora = $dia === RelojOperativo::hoy();

        $anterior = $conocidos
            ->filter(fn (array $lectura): bool => $lectura['dia'] < $dia || ($esDeAhora && $lectura['dia'] === $dia))
            ->sortByDesc('km')
            ->first();
        $posterior = $conocidos->filter(fn (array $lectura): bool => $lectura['dia'] > $dia)->sortBy('km')->first();

        if ($anterior !== null && $kilometraje < $anterior['km']) {
            return 'El km de '.$vehiculo->placa.' era '.$this->km($anterior['km']).' el '.$this->dia($anterior['dia']).': el nuevo no puede ser menor.';
        }

        if ($posterior !== null && $kilometraje > $posterior['km']) {
            return $vehiculo->placa.' ya tiene '.$this->km($posterior['km']).' km anotados el '.$this->dia($posterior['dia']).': el de antes no puede ser mayor.';
        }

        if ($anterior !== null && $kilometraje - $anterior['km'] > self::SALTO_MAXIMO) {
            return 'Son más de '.$this->km(self::SALTO_MAXIMO).' km sobre el último de '.$vehiculo->placa.' ('.$this->km($anterior['km']).'): revisa si sobra un dígito.';
        }

        return null;
    }

    /**
     * Todo km que se le conoce a la unidad, con su día: las lecturas y las
     * órdenes de trabajo que lo anotaron.
     *
     * @return Collection<int, array{km: int, dia: string}>
     */
    private function conocidos(Vehiculo $vehiculo, ?string $origen): Collection
    {
        $lecturas = LecturaKilometraje::query()
            ->where('vehiculo_id', $vehiculo->id)
            ->when($origen !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('origen')->orWhere('origen', '!=', $origen)))
            ->get(['kilometraje', 'leido_en'])
            ->map(fn (LecturaKilometraje $lectura): array => ['km' => $lectura->kilometraje, 'dia' => self::diaDe($lectura->leido_en)]);

        $ordenes = OrdenTrabajo::query()
            ->vigentes()
            ->where('vehiculo_id', $vehiculo->id)
            ->whereNotNull('kilometraje')
            ->get(['kilometraje', 'fecha'])
            ->map(fn (OrdenTrabajo $orden): array => ['km' => (int) $orden->kilometraje, 'dia' => Carbon::parse($orden->fecha)->toDateString()]);

        return $lecturas->toBase()->concat($ordenes);
    }

    /** El día de la operación (Lima) en que se anotó una lectura. */
    private static function diaDe(CarbonInterface $instante): string
    {
        return $instante->copy()->setTimezone(config()->string('app.business_timezone'))->toDateString();
    }

    private function km(int $kilometraje): string
    {
        return number_format($kilometraje, 0, '.', ',');
    }

    private function dia(string $dia): string
    {
        return Carbon::parse($dia)->format('d/m/Y');
    }

    /**
     * Anota (o corrige) la lectura de ese paso del ticket, con la fecha en
     * que se leyó el odómetro (la del voucher, la de la llegada), no la del
     * día en que se tipeó. Sin km, la que había se quita: el km se borró
     * porque estaba mal.
     */
    public function anotar(int $vehiculoId, ?int $kilometraje, ?User $usuario, string $observacion, string $origen, ?CarbonInterface $fecha = null): void
    {
        if ($kilometraje === null) {
            $this->quitar($origen);

            return;
        }

        LecturaKilometraje::query()->updateOrCreate(['origen' => $origen], [
            'vehiculo_id' => $vehiculoId,
            'kilometraje' => $kilometraje,
            // De un día pasado solo se sabe el día: va al mediodía.
            'leido_en' => $fecha === null || $fecha->toDateString() === RelojOperativo::hoy()
                ? now()
                : Carbon::parse($fecha->toDateString().' 12:00:00', config()->string('app.business_timezone'))->setTimezone(config()->string('app.timezone')),
            'user_id' => $usuario?->id,
            'observacion' => $observacion,
        ]);
    }

    /**
     * Quita la lectura de un paso del ticket: el km se borró, o el registro
     * que la anotó (un combustible) se anuló.
     */
    public function quitar(string $origen): void
    {
        LecturaKilometraje::query()->where('origen', $origen)->first()?->delete();
    }
}
