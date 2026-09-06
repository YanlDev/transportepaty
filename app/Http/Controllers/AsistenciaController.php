<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAsistencia;
use App\Http\Requests\ActualizarDiasDebidosRequest;
use App\Http\Requests\ActualizarNotasMesRequest;
use App\Http\Requests\MarcarAsistenciaRequest;
use App\Models\Asistencia;
use App\Models\Conductor;
use App\Models\DescansoDebido;
use App\Services\CalendarioAsistenciaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * El calendario individual por conductor (mes a mes, año completo) vive en
 * la pestaña de Asistencia de su ficha —ver `ConductorController::show()`—,
 * no acá: este controlador solo se ocupa del rooster y de las acciones que
 * modifican una marca, sin importar desde qué vista se dispararon.
 */
class AsistenciaController extends Controller
{
    /**
     * El ciclo de planilla no sigue el mes calendario: siempre empieza el
     * día 28 y termina el día antes del próximo 28, sin importar cuántos
     * días tenga el mes de por medio (28-31, según el mes de inicio).
     */
    private const DIA_INICIO_CICLO = 28;

    public function __construct(private readonly CalendarioAsistenciaService $calendarioService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Asistencia::class);

        $inicioCiclo = $this->inicioCicloPedido($request);
        $finCiclo = $inicioCiclo->addMonth()->subDay();

        // Activos siempre, más los inactivos que todavía tienen marcas en
        // este ciclo puntual —un conductor que renunció sigue apareciendo en
        // el ciclo en que trabajó, pero no en los ciclos posteriores donde ya
        // no hay nada suyo que mostrar.
        $conductores = Conductor::query()
            ->where(function (Builder $query) use ($inicioCiclo, $finCiclo): void {
                $query->where('activo', true)
                    ->orWhereHas('asistencias', function (Builder $query) use ($inicioCiclo, $finCiclo): void {
                        $query->whereBetween('fecha', [$inicioCiclo->toDateString(), $finCiclo->toDateString()]);
                    });
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'activo']);

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
            'activo' => $conductor->activo,
            'marcas' => $this->calendarioService->comoMarcas($asistencias->get($conductor->id, new Collection)),
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
     * Cuántos días de descanso se le deben a un conductor ese mes —un
     * número que el admin escribe a mano, independiente de mes a mes (no
     * arrastra saldo), porque la deuda real incluye meses de antes de este
     * sistema y no hay forma de reconstruirla desde las marcas.
     */
    public function actualizarDiasDebidos(ActualizarDiasDebidosRequest $request, Conductor $conductor): RedirectResponse
    {
        $this->authorize('update', DescansoDebido::class);

        DescansoDebido::query()->updateOrCreate(
            [
                'conductor_id' => $conductor->id,
                'mes' => CarbonImmutable::parse($request->string('mes')->value())->startOfMonth()->toDateString(),
            ],
            ['dias_debidos' => $request->integer('dias_debidos')],
        );

        return back();
    }

    /**
     * Notas libres del mes —incidencias, acuerdos verbales, lo que no encaja
     * en una marca diaria ni es un número de días— sobre el mismo registro
     * que ya guarda los días debidos, porque ambos son datos del par
     * conductor+mes, no del día.
     */
    public function actualizarNotas(ActualizarNotasMesRequest $request, Conductor $conductor): RedirectResponse
    {
        $this->authorize('update', DescansoDebido::class);

        DescansoDebido::query()->updateOrCreate(
            [
                'conductor_id' => $conductor->id,
                'mes' => CarbonImmutable::parse($request->string('mes')->value())->startOfMonth()->toDateString(),
            ],
            ['notas' => $request->string('notas')->value() ?: null],
        );

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
                'dia_semana' => CalendarioAsistenciaService::DIAS_SEMANA[$dia->dayOfWeekIso - 1],
                'es_domingo' => $dia->dayOfWeekIso === 7,
            ];
        }

        return $dias;
    }
}
