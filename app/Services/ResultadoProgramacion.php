<?php

namespace App\Services;

/**
 * La propuesta de programación de un día, ya separada en los tres grupos que
 * hacen falta para decidir: lo que sube, lo que queda de reserva, y lo que no
 * puede subir con el motivo a la vista.
 *
 * Las que van cargadas a mina se llevan aparte porque no compiten por cupo: se
 * agregan a la tabla sin número, con su observación de inicio de tránsito.
 */
final readonly class ResultadoProgramacion
{
    /**
     * @param  list<FilaProgramacion>  $enTransito
     * @param  list<FilaProgramacion>  $titulares
     * @param  list<FilaProgramacion>  $reservas
     * @param  list<FilaProgramacion>  $noProgramables
     */
    public function __construct(
        public string $fecha,
        public int $cupos,
        public array $enTransito,
        public array $titulares,
        public array $reservas,
        public array $noProgramables,
    ) {}

    /**
     * Las filas de la tabla que se envía, en su orden definitivo: primero las
     * cargadas que ya van subiendo, después las vacías numeradas.
     *
     * @return list<FilaProgramacion>
     */
    public function filasDeLaTabla(): array
    {
        return [...$this->enTransito, ...$this->titulares];
    }

    /**
     * Cupos que quedaron sin cubrir por falta de unidades elegibles.
     */
    public function cuposLibres(): int
    {
        return max(0, $this->cupos - count($this->titulares));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $comoArray = fn (array $filas): array => array_map(
            fn (FilaProgramacion $fila): array => $fila->toArray(),
            $filas,
        );

        return [
            'fecha' => $this->fecha,
            'cupos' => $this->cupos,
            'cupos_libres' => $this->cuposLibres(),
            'en_transito' => $comoArray($this->enTransito),
            'titulares' => $comoArray($this->titulares),
            'reservas' => $comoArray($this->reservas),
            'no_programables' => $comoArray($this->noProgramables),
            'tabla' => $comoArray($this->filasDeLaTabla()),
        ];
    }
}
