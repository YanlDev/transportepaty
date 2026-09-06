<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Conductor;
use App\Models\DescansoDebido;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Construye el calendario anual (enero a diciembre) de un conductor: usado
 * tanto por la pestaña de asistencia en su ficha como, antes, por la página
 * individual de asistencia. Vive fuera de los controladores porque ambos
 * puntos de entrada necesitan exactamente el mismo cálculo.
 */
class CalendarioAsistenciaService
{
    /**
     * Iniciales de los días de la semana en el mismo orden que Carbon numera
     * `dayOfWeekIso` (1 = lunes .. 7 = domingo).
     *
     * @var list<string>
     */
    public const DIAS_SEMANA = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

    /**
     * @return array{anio: int, calendarios: list<array{mes: string, dias: list<array{numero: int, fecha: string, dia_semana: string, es_domingo: bool, es_relleno: bool}>, marcas: array<string, array{asistencia_id: int, estado: string, estado_label: string}>, dias_debidos: int, notas: string|null}>}
     */
    public function paraConductor(Conductor $conductor, int $anio): array
    {
        $mesInicio = CarbonImmutable::create($anio, 1, 1);
        $mesFin = $mesInicio->endOfYear();

        $diasDebidos = DescansoDebido::query()
            ->where('conductor_id', $conductor->id)
            ->whereBetween('mes', [$mesInicio->toDateString(), $mesFin->toDateString()])
            ->get()
            ->keyBy(fn (DescansoDebido $descansoDebido): string => $descansoDebido->mes->toDateString());

        $calendarios = [];

        for ($i = 0; $i < 12; $i++) {
            $mes = $mesInicio->addMonths($i);
            $finMes = $mes->endOfMonth();

            $asistencias = Asistencia::query()
                ->where('conductor_id', $conductor->id)
                ->whereBetween('fecha', [$mes->toDateString(), $finMes->toDateString()])
                ->get();

            $calendarios[] = [
                'mes' => $mes->toDateString(),
                'dias' => $this->diasDelMes($mes),
                'marcas' => $this->comoMarcas($asistencias),
                'dias_debidos' => $diasDebidos->get($mes->toDateString())->dias_debidos ?? 0,
                'notas' => $diasDebidos->get($mes->toDateString())->notas ?? null,
            ];
        }

        return [
            'anio' => $anio,
            'calendarios' => $calendarios,
        ];
    }

    /**
     * Las marcas de un conductor indexadas por fecha. Solo trae los días que
     * sí tienen un registro: el resto son «sin marcar» por omisión, así que
     * no hace falta mandar una entrada vacía por cada uno.
     *
     * @param  Collection<int, Asistencia>  $asistenciasDelConductor
     * @return array<string, array{asistencia_id: int, estado: string, estado_label: string}>
     */
    public function comoMarcas(Collection $asistenciasDelConductor): array
    {
        return $asistenciasDelConductor
            ->keyBy(fn (Asistencia $asistencia): string => $asistencia->fecha->toDateString())
            ->map(fn (Asistencia $asistencia): array => [
                'asistencia_id' => $asistencia->id,
                'estado' => $asistencia->estado->value,
                'estado_label' => $asistencia->estado->label(),
            ])
            ->all();
    }

    /**
     * La grilla del calendario mensual: semanas completas de lunes a
     * domingo, así que los primeros y últimos días pueden pertenecer al mes
     * anterior o siguiente —se marcan con `es_relleno` para que el frontend
     * los pinte apagados y no editables.
     *
     * @return list<array{numero: int, fecha: string, dia_semana: string, es_domingo: bool, es_relleno: bool}>
     */
    private function diasDelMes(CarbonImmutable $mes): array
    {
        $inicioGrilla = $mes->subDays($mes->dayOfWeekIso - 1);
        $finMes = $mes->endOfMonth();
        $finGrilla = $finMes->addDays(7 - $finMes->dayOfWeekIso);

        $dias = [];

        for ($dia = $inicioGrilla; $dia->lte($finGrilla); $dia = $dia->addDay()) {
            $dias[] = [
                'numero' => $dia->day,
                'fecha' => $dia->toDateString(),
                'dia_semana' => self::DIAS_SEMANA[$dia->dayOfWeekIso - 1],
                'es_domingo' => $dia->dayOfWeekIso === 7,
                'es_relleno' => ! $dia->isSameMonth($mes),
            ];
        }

        return $dias;
    }
}
