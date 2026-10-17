<?php

namespace App\Models;

use App\Enums\EstadoLlanta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que el taller ajusta del módulo de mantenimiento. Es una fila sola,
 * sembrada por la migración: hay un juego vigente y se edita.
 *
 * @property int $id
 * @property string $llantas_revisar_mm
 * @property string $llantas_cambiar_mm
 */
#[Fillable(['llantas_revisar_mm', 'llantas_cambiar_mm'])]
class ParametroMantenimiento extends Model
{
    protected $table = 'parametros_mantenimiento';

    public static function vigentes(): self
    {
        return self::query()->firstOrFail();
    }

    /**
     * El semáforo de una llanta con estos cortes: hasta `llantas_cambiar_mm`
     * rojo, hasta `llantas_revisar_mm` ámbar, y verde con más dibujo.
     */
    public function estadoLlanta(?float $milimetros): EstadoLlanta
    {
        return match (true) {
            $milimetros === null => EstadoLlanta::SinMedir,
            $milimetros <= (float) $this->llantas_cambiar_mm => EstadoLlanta::Cambiar,
            $milimetros <= (float) $this->llantas_revisar_mm => EstadoLlanta::Revisar,
            default => EstadoLlanta::Bien,
        };
    }

    /**
     * @return array{revisar_mm: float, cambiar_mm: float}
     */
    public function umbralesLlanta(): array
    {
        return [
            'revisar_mm' => (float) $this->llantas_revisar_mm,
            'cambiar_mm' => (float) $this->llantas_cambiar_mm,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'llantas_revisar_mm' => 'decimal:1',
            'llantas_cambiar_mm' => 'decimal:1',
        ];
    }
}
