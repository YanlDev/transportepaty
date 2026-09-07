<?php

namespace App\Models;

use Database\Factories\ParametroFlotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * El contexto que comparten todos los costos fijos: cuántas unidades hay y
 * cuántos días al año cada una está realmente disponible para vender.
 *
 * Los días disponibles son el divisor de toda la estructura, y por eso están
 * acá y no escondidos dentro de cada tasa: un camión no factura 365 días
 * —pierde días en mantenimiento, en revisiones técnicas y esperando la
 * sincronización de retornos—, y repartir los costos fijos sobre 365 en vez de
 * sobre los días reales subestima el costo de cada viaje.
 *
 * Es una fila sola: hay un juego vigente y se edita.
 *
 * @property int $id
 * @property int $tamano_flota
 * @property float $dias_ano
 * @property float $dias_mantenimiento
 * @property float $dias_certificaciones
 * @property float $dias_sincronizacion
 * @property float $igv_pct
 * @property float $margen_pct_default
 * @property float $viatico_dia
 */
#[Fillable([
    'tamano_flota',
    'dias_ano',
    'dias_mantenimiento',
    'dias_certificaciones',
    'dias_sincronizacion',
    'igv_pct',
    'margen_pct_default',
    'viatico_dia',
])]
class ParametroFlota extends Model
{
    /** @use HasFactory<ParametroFlotaFactory> */
    use HasFactory;

    protected $table = 'parametros_flota';

    /**
     * El juego vigente. La migración siembra la fila, así que en una base con
     * las migraciones corridas siempre existe.
     */
    public static function vigentes(): self
    {
        return self::query()->firstOrFail();
    }

    /**
     * Los días al año que una unidad puede estar en ruta.
     */
    public function diasDisponibles(): float
    {
        return max(0.0, $this->dias_ano
            - $this->dias_mantenimiento
            - $this->dias_certificaciones
            - $this->dias_sincronizacion);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamano_flota' => 'integer',
            'dias_ano' => 'float',
            'dias_mantenimiento' => 'float',
            'dias_certificaciones' => 'float',
            'dias_sincronizacion' => 'float',
            'igv_pct' => 'float',
            'margen_pct_default' => 'float',
            'viatico_dia' => 'float',
        ];
    }
}
