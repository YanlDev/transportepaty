<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAsistencia;
use App\Http\Requests\MarcarAsistenciaRequest;
use App\Models\Asistencia;
use App\Models\Conductor;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reemplaza el rooster en papel: una fila por conductor, una columna por día
 * del ciclo de planilla, igual que la planilla que ya usan. Cada celda de
 * ese rooster es acá una fila de `asistencias`; sin registro para el día es
 * «sin marcar», no un quinto estado — no se asume nada de un conductor que
 * nadie marcó.
 */
class AsistenciaController extends Controller
{
    /**
     * El ciclo de planilla no sigue el mes calendario: siempre empieza el
     * día 28 y termina el día antes del próximo 28, sin importar cuántos
     * días tenga el mes de por medio (28-31, según el mes de inicio).
     */
    private const DIA_INICIO_CICLO = 28;

    /**
     * Iniciales de los días de la semana en el mismo orden que Carbon
     * numera `dayOfWeekIso` (1 = lunes .. 7 = domingo).
     *
     * @var list<string>
     */
    private const DIAS_SEMANA = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Asistencia::class);

        $inicioCiclo = $this->inicioCicloPedido($request);
        $finCiclo = $inicioCiclo->addMonth()->subDay();

        $conductores = Conductor::query()
            ->where('activo', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos']);

        $asistencias = Asistencia::query()
            ->whereBetween('fecha', [$inicioCiclo->toDateString(), $finCiclo->toDateString()])
            ->whereIn('conductor_id', $conductores->pluck('id'))
            ->get()
            ->groupBy('conductor_id');

        $filas = $conductores->map(fn (Conductor $conductor): array => [
            'conductor_id' => $conductor->id,
            // Apellidos primero: es el orden en que ya está la grilla y el
            // que usa el rooster en papel, aunque el resto de la app
            // muestre el nombre al revés.
            'nombre_completo' => "{$conductor->apellidos} {$conductor->nombres}",
            'marcas' => $this->comoMarcas($asistencias->get($conductor->id, new Collection)),
        ]);

        return Inertia::render('asistencia/index', [
            'inicioCiclo' => $inicioCiclo->toDateString(),
            'dias' => $this->diasDelCiclo($inicioCiclo, $finCiclo),
            'filas' => $filas,
        ]);
    }

    public function marcar(MarcarAsistenciaRequest $request, Conductor $conductor): RedirectResponse
    {
        $this->authorize('create', Asistencia::class);

        // firstOrNew hidrata la fila si ya existía: cambiar el estado del día
        // no debe perder una observación ya escrita, ni crear un duplicado.
        $asistencia = Asistencia::query()->firstOrNew([
            'conductor_id' => $conductor->id,
            'fecha' => $request->date('fecha')->toDateString(),
        ]);

        $asistencia->estado = $request->enum('estado', EstadoAsistencia::class);

        if ($request->has('observaciones')) {
            $asistencia->observaciones = $request->string('observaciones')->value() ?: null;
        }

        $asistencia->save();

        return back();
    }

    /**
     * Vuelve a dejar el día sin marcar: no todo lo que se marca a mano fue
     * correcto, y forzar a elegir un estado en vez de poder borrarlo
     * convertiría cualquier error de tipeo en un dato falso permanente.
     */
    public function destroy(Asistencia $asistencia): RedirectResponse
    {
        $this->authorize('update', $asistencia);

        $asistencia->delete();

        return back();
    }

    /**
     * Inicio del ciclo pedido, o el ciclo vigente hoy si no se pidió uno
     * válido.
     */
    private function inicioCicloPedido(Request $request): CarbonImmutable
    {
        $inicio = $request->string('inicio')->value();

        try {
            return $inicio === '' ? $this->cicloDe(CarbonImmutable::now()) : CarbonImmutable::parse($inicio);
        } catch (\Exception) {
            return $this->cicloDe(CarbonImmutable::now());
        }
    }

    /**
     * El día 28 del ciclo que contiene la fecha dada: si todavía no se llega
     * al 28 de este mes, el ciclo vigente empezó el 28 del mes anterior.
     */
    private function cicloDe(CarbonImmutable $fecha): CarbonImmutable
    {
        $inicioEsteMes = $fecha->day(self::DIA_INICIO_CICLO);

        return $fecha->day >= self::DIA_INICIO_CICLO
            ? $inicioEsteMes
            : $inicioEsteMes->subMonthNoOverflow();
    }

    /**
     * @return list<array{numero: int, fecha: string, dia_semana: string, es_domingo: bool}>
     */
    private function diasDelCiclo(CarbonImmutable $inicioCiclo, CarbonImmutable $finCiclo): array
    {
        $dias = [];

        for ($dia = $inicioCiclo; $dia->lte($finCiclo); $dia = $dia->addDay()) {
            $dias[] = [
                'numero' => $dia->day,
                'fecha' => $dia->toDateString(),
                'dia_semana' => self::DIAS_SEMANA[$dia->dayOfWeekIso - 1],
                'es_domingo' => $dia->dayOfWeekIso === 7,
            ];
        }

        return $dias;
    }

    /**
     * Las marcas del conductor ese mes, indexadas por fecha. Solo trae los
     * días que sí tienen un registro: el resto son «sin marcar» por omisión,
     * así que no hace falta mandar una entrada vacía por cada uno.
     *
     * @param  Collection<int, Asistencia>  $asistenciasDelConductor
     * @return array<string, array{asistencia_id: int, estado: string, estado_label: string}>
     */
    private function comoMarcas(Collection $asistenciasDelConductor): array
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
}
