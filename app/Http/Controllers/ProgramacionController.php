<?php

namespace App\Http\Controllers;

use App\Enums\TipoNovedad;
use App\Models\EstadoUnidad;
use App\Models\Novedad;
use App\Services\MotorProgramacion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La programación de subida a mina del día: dado el número de cupos, propone
 * qué unidades suben, cuáles quedan de reserva y cuáles no pueden con su motivo.
 *
 * La propuesta se recalcula en cada visita a partir del estado canónico y las
 * novedades vigentes, así que corregir un dato en Disponibilidad o registrar un
 * no habido se refleja de inmediato sin tener que rehacer nada.
 */
class ProgramacionController extends Controller
{
    /** Cupos que ofrece mina en un día normal, mientras no digas otra cosa. */
    private const CUPOS_POR_DEFECTO = 10;

    /** Tope defensivo: más que esto es un dedazo en la caja de cupos. */
    private const CUPOS_MAXIMOS = 200;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EstadoUnidad::class);

        $fecha = $this->fechaPedida($request);
        $cupos = $this->cuposPedidos($request);

        $resultado = (new MotorProgramacion)->proponer($fecha, $cupos);

        return Inertia::render('programacion/index', [
            'fecha' => $fecha,
            'cupos' => $cupos,
            'resultado' => $resultado->toArray(),
            'novedades' => $this->novedadesDelDia($fecha),
            'tiposNovedad' => TipoNovedad::options(),
            'unidades' => $this->unidadesConEstado($fecha),
        ]);
    }

    /**
     * Novedades que pesaban ese día, para poder verlas y levantarlas desde la
     * misma pantalla en la que estorban.
     *
     * @return list<array<string, mixed>>
     */
    private function novedadesDelDia(string $fecha): array
    {
        $vigentes = Novedad::query()
            ->with('tracto:id,placa')
            ->vigentesEn($fecha)
            ->orderByDesc('desde')
            ->get();

        $novedades = [];

        foreach ($vigentes as $novedad) {
            $novedades[] = [
                'id' => $novedad->id,
                'tracto_id' => $novedad->tracto_id,
                'placa' => $novedad->tracto->placa,
                'tipo' => $novedad->tipo->value,
                'tipo_label' => $novedad->tipo->label(),
                'motivo' => $novedad->motivoLegible(),
                'desde' => $novedad->desde->toDateString(),
                'vigente' => $novedad->estaVigente(),
            ];
        }

        return $novedades;
    }

    /**
     * Tractos que aparecen en el estado del día, para elegirlos al registrar una
     * novedad sin tener que buscar entre toda la flota.
     *
     * @return list<array{value: string, label: string}>
     */
    private function unidadesConEstado(string $fecha): array
    {
        $estados = EstadoUnidad::ultimosAntesDe(
            Carbon::parse($fecha)->addDay()->toDateString(),
            relaciones: ['tracto:id,placa'],
        );

        $unidades = [];

        foreach ($estados as $estado) {
            $unidades[] = [
                'value' => (string) $estado->tracto_id,
                'label' => $estado->tracto->placa,
            ];
        }

        usort($unidades, fn (array $uno, array $otro): int => strcmp($uno['label'], $otro['label']));

        return $unidades;
    }

    private function fechaPedida(Request $request): string
    {
        $fecha = $request->string('fecha')->value();

        try {
            return $fecha === '' ? now()->toDateString() : Carbon::parse($fecha)->toDateString();
        } catch (\Exception) {
            return now()->toDateString();
        }
    }

    private function cuposPedidos(Request $request): int
    {
        if (! $request->has('cupos')) {
            return self::CUPOS_POR_DEFECTO;
        }

        return max(0, min($request->integer('cupos'), self::CUPOS_MAXIMOS));
    }
}
