<?php

namespace App\Services;

use App\Enums\NivelAlerta;
use App\Enums\TipoAlerta;

/**
 * Una inconsistencia detectada sobre un estado diario. No bloquea nada: se
 * muestra junto a la fila para que decidas si el dato está mal o si la realidad
 * fue rara ese día.
 */
final readonly class Alerta
{
    public function __construct(
        public TipoAlerta $tipo,
        public ?string $detalle = null,
    ) {}

    public function nivel(): NivelAlerta
    {
        return $this->tipo->nivel();
    }

    /**
     * Representación para el frontend.
     *
     * @return array{tipo: string, label: string, nivel: string, nivel_label: string, detalle: string|null}
     */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo->value,
            'label' => $this->tipo->label(),
            'nivel' => $this->nivel()->value,
            'nivel_label' => $this->nivel()->label(),
            'detalle' => $this->detalle,
        ];
    }
}
