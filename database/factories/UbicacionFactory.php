<?php

namespace Database\Factories;

use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ubicacion>
 */
class UbicacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->city();

        return [
            'codigo' => str($nombre)->slug('_')->value(),
            'nombre' => $nombre,
            // Dentro del rango continental del Perú, para que cualquier punto
            // de prueba caiga en un lugar plausible del mapa.
            'latitud' => fake()->randomFloat(7, -18.3, -11.0),
            'longitud' => fake()->randomFloat(7, -77.5, -69.0),
            'es_zona_base' => false,
            'tiene_taller' => false,
            'dias_permanencia_habitual' => null,
            'orden_corredor' => null,
            'observaciones' => null,
        ];
    }

    /**
     * Un punto sobre el corredor troncal, en la posición indicada.
     */
    public function enCorredor(int $orden): static
    {
        return $this->state(fn (): array => ['orden_corredor' => $orden]);
    }

    /**
     * Una zona desde la que se puede subir a mina.
     */
    public function zonaBase(): static
    {
        return $this->state(fn (): array => ['es_zona_base' => true]);
    }

    /**
     * La base con taller, donde la unidad puede quedarse los días indicados sin
     * que eso cuente como estar detenida.
     */
    public function conTaller(int $diasPermanencia = 2): static
    {
        return $this->state(fn (): array => [
            'es_zona_base' => true,
            'tiene_taller' => true,
            'dias_permanencia_habitual' => $diasPermanencia,
        ]);
    }

    /**
     * Un punto sin posición confirmada: existe en el catálogo pero no se dibuja
     * en el mapa hasta que alguien le ponga la chincheta.
     */
    public function sinCoordenadas(): static
    {
        return $this->state(fn (): array => [
            'latitud' => null,
            'longitud' => null,
        ]);
    }
}
