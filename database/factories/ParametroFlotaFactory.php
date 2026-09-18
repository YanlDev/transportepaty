<?php

namespace Database\Factories;

use App\Models\ParametroFlota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParametroFlota>
 */
class ParametroFlotaFactory extends Factory
{
    protected $model = ParametroFlota::class;

    /**
     * Los valores reales de la casa (Excel de costos, agosto 2026), no valores
     * al azar: un test que deriva una tasa conocida tiene que dar el número que
     * da en la calle.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tamano_flota' => 100,
            'dias_ano' => 365,
            'dias_mantenimiento' => 4.87,
            'dias_certificaciones' => 4,
            'dias_sincronizacion' => 53,
            'igv_pct' => 0.18,
            'margen_pct_default' => 0.12,
        ];
    }
}
