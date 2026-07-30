<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Cuándo se espera que una unidad llegue a su destino. La confianza importa
 * tanto como el número: mientras no haya recorridos suficientes en el histórico,
 * la estimación se apoya en una velocidad de referencia y hay que leerla como
 * un orden de magnitud, no como una hora de llegada.
 */
final readonly class Estimacion
{
    public function __construct(
        public int $diasRestantes,
        public string $fechaEstimada,
        public float $kilometrosRestantes,
        public bool $calibradaConHistorico,
    ) {}

    /**
     * Texto para las pantallas, en los términos en que se habla de esto: nadie
     * necesita una hora exacta, necesita saber si le llega hoy o pasado.
     */
    public function label(): string
    {
        return match (true) {
            $this->diasRestantes <= 0 => 'Llegando',
            $this->diasRestantes === 1 => 'Mañana',
            default => "En {$this->diasRestantes} días",
        };
    }

    public function esHoy(): bool
    {
        return $this->fechaEstimada === Carbon::now()->toDateString();
    }

    /**
     * @return array{dias: int, fecha: string, label: string, kilometros: int, calibrada: bool}
     */
    public function toArray(): array
    {
        return [
            'dias' => $this->diasRestantes,
            'fecha' => $this->fechaEstimada,
            'label' => $this->label(),
            'kilometros' => (int) round($this->kilometrosRestantes),
            'calibrada' => $this->calibradaConHistorico,
        ];
    }
}
