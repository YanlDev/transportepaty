<?php

namespace App\Services;

/**
 * Una línea de la tabla que se envía a mina, ya en los términos del formato de
 * Cargo Transport. Las cargadas que suben van sin número y sin hora; las vacías
 * llevan su turno.
 */
final readonly class FilaProgramacion
{
    public function __construct(
        public int $tractoId,
        public string $fecha,
        public string $empresa,
        public string $vehiculo,
        public ?string $conductor,
        public ?string $tipoCarga,
        public ?string $estadoUnidad,
        public ?int $numero = null,
        public ?string $hora = null,
        public ?string $observaciones = null,
        /** Solo en las no programables: por qué se quedó. */
        public ?string $motivo = null,
    ) {}

    /**
     * Copia la fila con su turno asignado.
     */
    public function conTurno(int $numero, string $hora): self
    {
        return new self(
            tractoId: $this->tractoId,
            fecha: $this->fecha,
            empresa: $this->empresa,
            vehiculo: $this->vehiculo,
            conductor: $this->conductor,
            tipoCarga: $this->tipoCarga,
            estadoUnidad: $this->estadoUnidad,
            numero: $numero,
            hora: $hora,
            observaciones: $this->observaciones,
            motivo: $this->motivo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tracto_id' => $this->tractoId,
            // Las cargadas hacia mina no compiten por cupo, así que van sin
            // numerar: en la tabla se lee como una raya.
            'numero' => $this->numero,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'empresa' => $this->empresa,
            'vehiculo' => $this->vehiculo,
            'conductor' => $this->conductor,
            'tipo_carga' => $this->tipoCarga,
            'estado_unidad' => $this->estadoUnidad,
            'observaciones' => $this->observaciones,
            'motivo' => $this->motivo,
        ];
    }
}
