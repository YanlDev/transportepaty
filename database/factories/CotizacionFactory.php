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
            'peajes' => $this->faker->numberBetween(40, 600),
            'viaticos' => $this->faker->numberBetween(50, 350),
            'alojamiento' => 0,
            'cochera' => $this->faker->numberBetween(0, 80),
            'carga_descarga' => 0,
            'otros_ruta' => 0,
            'margen_pct' => 0.12,
        ];

        // Líneas explícitas y no los componentes de la base: la cotización
        // guarda el desglose congelado, así que una fábrica que no toca la
        // estructura de costos vigente es más fiel a lo que representa.
        $calculo = (new CalculadoraCotizacion)->calcular($ruta, [
            ['nombre' => 'Activos', 'tipo' => 'fijo_dia', 'naturaleza' => 'directo', 'tasa' => 268.43],
            ['nombre' => 'Choferes', 'tipo' => 'fijo_dia', 'naturaleza' => 'directo', 'tasa' => 258.94],
            ['nombre' => 'Estructura', 'tipo' => 'fijo_dia', 'naturaleza' => 'indirecto', 'tasa' => 94.21],
            ['nombre' => 'Combustible', 'tipo' => 'variable_km', 'naturaleza' => 'directo', 'tasa' => 2.6321],
            ['nombre' => 'Neumáticos', 'tipo' => 'variable_km', 'naturaleza' => 'directo', 'tasa' => 0.2466],
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
