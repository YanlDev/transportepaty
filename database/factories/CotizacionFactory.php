<?php

namespace Database\Factories;

use App\Enums\EstadoCotizacion;
use App\Models\Cotizacion;
use App\Services\CalculadoraCotizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cotizacion>
 */
class CotizacionFactory extends Factory
{
    protected $model = Cotizacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ruta = [
            'km' => $this->faker->numberBetween(200, 1500),
            'dias' => $this->faker->numberBetween(1, 7),
            'margen_pct' => 0.12,
        ];

        // Líneas explícitas y no los componentes de la base: la cotización
        // guarda el desglose congelado, así que una fábrica que no toca la
        // estructura de costos vigente es más fiel a lo que representa.
        $calculo = (new CalculadoraCotizacion)->calcular($ruta, [
            ['nombre' => 'Activos', 'tipo' => 'fijo_dia', 'naturaleza' => 'directo', 'tasa' => 268.13],
            ['nombre' => 'Choferes', 'tipo' => 'fijo_dia', 'naturaleza' => 'directo', 'tasa' => 258.94],
            ['nombre' => 'Estructura', 'tipo' => 'fijo_dia', 'naturaleza' => 'indirecto', 'tasa' => 121.70],
            ['nombre' => 'Combustible', 'tipo' => 'variable_km', 'naturaleza' => 'directo', 'tasa' => 2.52],
            ['nombre' => 'Otros variables', 'tipo' => 'variable_km', 'naturaleza' => 'directo', 'tasa' => 1.04],
        ], 0.18);

        return [
            ...$ruta,
            ...$calculo,
            'numero' => Cotizacion::SERIE.'-'.$this->faker->unique()->numerify('####'),
            'fecha' => now()->toDateString(),
            'valido_hasta' => now()->addDays(15)->toDateString(),
            'cliente_id' => null,
            'cliente_nombre' => mb_strtoupper($this->faker->company()),
            'cliente_ruc' => '20'.$this->faker->numerify('#########'),
            'origen' => mb_strtoupper($this->faker->city()),
            'destino' => mb_strtoupper($this->faker->city()),
            'material' => 'Materiales varios',
            'estado' => EstadoCotizacion::Borrador,
            'notas' => null,
        ];
    }
}
