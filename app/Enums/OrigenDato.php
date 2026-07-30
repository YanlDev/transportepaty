<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De dónde salió el valor de un campo del estado diario. Se guarda por campo,
 * no por registro, porque una misma fila mezcla las tres procedencias: la carga
 * llegó del reporte, el cliente se dedujo de ella y la ubicación la corregiste
 * tú a mano.
 *
 * Sirve para dos cosas: mostrar más tenue lo que el sistema supuso, y —sobre
 * todo— impedir que una reimportación pise lo que ya confirmaste.
 */
enum OrigenDato: string
{
    use HasLabel;

    case Importado = 'importado';
    case Deducido = 'deducido';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Importado => 'Del reporte',
            self::Deducido => 'Deducido',
            self::Manual => 'Confirmado a mano',
        };
    }

    /**
     * Indica si el valor puede volver a calcularse o sobrescribirse al importar.
     * Lo que confirmaste a mano manda sobre cualquier reporte posterior: si
     * corregiste una ubicación, no queremos que el Excel del día siguiente la
     * vuelva a romper.
     */
    public function admiteSobrescritura(): bool
    {
        return $this !== self::Manual;
    }
}
