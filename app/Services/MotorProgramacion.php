<?php

namespace App\Services;

use App\Models\EstadoUnidad;
use App\Models\Novedad;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Propone la programación de subida a mina de un día: dado el número de cupos,
 * separa las unidades en las que suben, las que quedan de reserva y las que no
 * pueden subir con su motivo.
 *
 * Trabaja sobre el estado canónico y las novedades de campo, no sobre ningún
 * archivo, así que la propuesta sale igual se hayan cargado los datos a mano o
 * importados.
 */
final class MotorProgramacion
{
    /**
     * Turnos de salida hacia mina. Los titulares se reparten entre ellos en
     * bloques parejos.
     *
     * @var list<string>
     */
    public const HORAS = ['06:00', '06:30', '07:00'];

    /**
     * Razón social tal como debe figurar en la tabla que recibe el operador
     * logístico.
     */
    public const EMPRESA = 'TRANSPORTES PATY S.C.R. LTDA.';

    /**
     * Punto al que se sube. Las unidades cargadas con este destino ya están en
     * camino y no compiten por cupo.
     */
    public const MINA = 'san_rafael';

    /**
     * Días hacia atrás que se miran para calcular desde cuándo lleva la unidad
     * esperando en base. Alcanza de sobra: la vuelta completa del circuito son
     * diez a catorce días.
     */
    private const DIAS_DE_HISTORIAL = 60;

    /**
     * @var list<string>
     */
    private const RELACIONES = [
        'tracto:id,placa',
        'carreta:id,placa',
        'conductor:id,nombres,apellidos',
        'ubicacion',
        'destino',
    ];

    public function proponer(string $fecha, int $cupos): ResultadoProgramacion
    {
        $estados = $this->estadosVigentes($fecha);
        $novedades = $this->novedadesPorUnidad($fecha);
        $llegadas = $this->llegadasABase($estados, $fecha);

        $enTransito = [];
        $candidatos = [];
        $noProgramables = [];

        foreach ($estados as $estado) {
            $novedad = $novedades->get($estado->tracto_id);

            if ($novedad !== null) {
                $noProgramables[] = $this->fila($estado, $fecha, motivo: $novedad->motivoLegible());

                continue;
            }

            if ($this->vaCargadaAMina($estado)) {
                $enTransito[] = $this->filaEnTransito($estado, $fecha);

                continue;
            }

            $impedimento = $this->impedimento($estado);

            if ($impedimento !== null) {
                $noProgramables[] = $this->fila($estado, $fecha, motivo: $impedimento);

                continue;
            }

            $candidatos[] = [
                'fila' => $this->fila($estado, $fecha),
                // Orden de llegada a base: la que espera desde hace más tiempo
                // sube primero.
                'llegada' => $llegadas[$estado->tracto_id] ?? $fecha,
                'placa' => $estado->tracto->placa,
            ];
        }

        usort($candidatos, fn (array $uno, array $otro): int => [$uno['llegada'], $uno['placa']]
            <=> [$otro['llegada'], $otro['placa']]);

        $elegibles = array_map(fn (array $candidato): FilaProgramacion => $candidato['fila'], $candidatos);

        return new ResultadoProgramacion(
            fecha: $fecha,
            cupos: $cupos,
            enTransito: $enTransito,
            titulares: $this->conTurnos(array_slice($elegibles, 0, max(0, $cupos))),
            reservas: array_slice($elegibles, max(0, $cupos)),
            noProgramables: $noProgramables,
        );
    }

    /**
     * Reparte los turnos entre las horas de salida, en bloques parejos.
     *
     * @param  list<FilaProgramacion>  $titulares
     * @return list<FilaProgramacion>
     */
    private function conTurnos(array $titulares): array
    {
        if ($titulares === []) {
            return [];
        }

        $porTurno = (int) ceil(count($titulares) / count(self::HORAS));
        $conTurno = [];

        foreach ($titulares as $indice => $titular) {
            $conTurno[] = $titular->conTurno(
                $indice + 1,
                self::HORAS[min(intdiv($indice, $porTurno), count(self::HORAS) - 1)],
            );
        }

        return $conTurno;
    }

    /**
     * Motivo por el que la unidad no puede entrar a la programación, o null si
     * está en condiciones de subir. El orden de las comprobaciones es el orden
     * en que conviene leerlas.
     */
    private function impedimento(EstadoUnidad $estado): ?string
    {
        if ($estado->estado_carga === null || ! $estado->estado_carga->estaDescargada()) {
            $carga = $estado->tipo_carga?->label() ?? 'carga sin registrar';

            return "En ruta con {$carga}";
        }

        if ($estado->ubicacion === null) {
            return 'Sin ubicación registrada';
        }

        if (! $estado->ubicacion->es_zona_base) {
            return "Está en {$estado->ubicacion->nombre}, fuera de zona base";
        }

        if ($estado->conductor_id === null) {
            return 'Sin conductor asignado';
        }

        return null;
    }

    /**
     * Indica si la unidad ya va cargada hacia mina. Estas se suman a la tabla
     * sin número: no compiten por los cupos de vacías porque no están pidiendo
     * turno, están avisando que ya salieron.
     */
    private function vaCargadaAMina(EstadoUnidad $estado): bool
    {
        return $estado->estado_carga !== null
            && ! $estado->estado_carga->estaDescargada()
            && $estado->destino?->codigo === self::MINA;
    }

    private function filaEnTransito(EstadoUnidad $estado, string $fecha): FilaProgramacion
    {
        $desde = $estado->ubicacion === null
            ? ($estado->ubicacion_texto ?? 'ubicación sin registrar')
            : $estado->ubicacion->nombre;

        return $this->fila($estado, $fecha, observaciones: "Inicio de tránsito desde {$desde}");
    }

    private function fila(
        EstadoUnidad $estado,
        string $fecha,
        ?string $observaciones = null,
        ?string $motivo = null,
    ): FilaProgramacion {
        $vehiculo = $estado->carreta === null
            ? $estado->tracto->placa
            : "{$estado->tracto->placa}/{$estado->carreta->placa}";

        return new FilaProgramacion(
            tractoId: $estado->tracto_id,
            fecha: $fecha,
            empresa: self::EMPRESA,
            vehiculo: $vehiculo,
            conductor: $estado->conductor?->nombre_completo,
            tipoCarga: $estado->tipo_carga?->label(),
            estadoUnidad: $estado->estado_carga?->label(),
            observaciones: $observaciones ?: $estado->observaciones,
            motivo: $motivo,
        );
    }

    /**
     * Último estado conocido de cada unidad hasta la fecha indicada, inclusive.
     *
     * @return Collection<int, EstadoUnidad>
     */
    private function estadosVigentes(string $fecha): Collection
    {
        return EstadoUnidad::ultimosAntesDe(
            Carbon::parse($fecha)->addDay()->toDateString(),
            relaciones: self::RELACIONES,
        );
    }

    /**
     * Novedades que pesaban ese día, una por unidad. Si hay varias manda la más
     * reciente, que es la que describe la situación actual.
     *
     * @return Collection<int, Novedad>
     */
    private function novedadesPorUnidad(string $fecha): Collection
    {
        return Novedad::query()
            ->vigentesEn($fecha)
            ->orderBy('desde')
            ->get()
            ->keyBy('tracto_id');
    }

    /**
     * Desde qué día lleva cada unidad esperando en base, para poder darle
     * prioridad a la que llegó primero.
     *
     * Se cuenta hacia atrás mientras la unidad siga apareciendo descargada y en
     * zona base: el día en que dejó de cumplirlo es el día en que llegó.
     *
     * @param  Collection<int, EstadoUnidad>  $estados
     * @return array<int, string>
     */
    private function llegadasABase(Collection $estados, string $fecha): array
    {
        $desde = Carbon::parse($fecha)->subDays(self::DIAS_DE_HISTORIAL)->toDateString();

        $historial = EstadoUnidad::query()
            ->with('ubicacion')
            ->whereIn('tracto_id', $estados->pluck('tracto_id')->all())
            ->whereBetween('fecha', [$desde, $fecha])
            ->orderBy('tracto_id')
            ->orderByDesc('fecha')
            ->get()
            ->groupBy('tracto_id');

        $llegadas = [];

        foreach ($historial as $tractoId => $porUnidad) {
            foreach ($porUnidad as $estado) {
                if (! $this->esperandoEnBase($estado)) {
                    break;
                }

                $llegadas[(int) $tractoId] = $estado->fecha->toDateString();
            }
        }

        return $llegadas;
    }

    private function esperandoEnBase(EstadoUnidad $estado): bool
    {
        return $estado->estado_carga !== null
            && $estado->estado_carga->estaDescargada()
            && $estado->ubicacion !== null
            && $estado->ubicacion->es_zona_base;
    }
}
