<?php

namespace Database\Factories;

use App\Enums\MetodoCosto;
use App\Enums\NaturalezaCosto;
use App\Enums\TipoComponente;
use App\Models\ComponenteCosto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponenteCosto>
 */
class ComponenteCostoFactory extends Factory
{
    protected $model = ComponenteCosto::class;

    /**
     * Un componente de tasa fija: es el más simple de razonar en un test que
     * no está probando la derivación en sí, sino qué hace la calculadora con
     * la tasa una vez que la tiene.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->words(2, true),
            'tipo' => TipoComponente::FijoDia,
            'naturaleza' => NaturalezaCosto::Directo,
            'metodo' => MetodoCosto::Manual,
            'entradas' => ['tasa' => 100],
            'orden' => 0,
            'activo' => true,
        ];
    }

    public function fijoDia(float $tasa): self
    {
        return $this->state([
            'tipo' => TipoComponente::FijoDia,
            'metodo' => MetodoCosto::Manual,
            'entradas' => ['tasa' => $tasa],
        ]);
    }

    public function variableKm(float $tasa): self
    {
        return $this->state([
            'tipo' => TipoComponente::VariableKm,
            'metodo' => MetodoCosto::Manual,
            'entradas' => ['tasa' => $tasa],
        ]);
    }

    public function indirecto(): self
    {
        return $this->state(['naturaleza' => NaturalezaCosto::Indirecto]);
    }

    /**
     * @param  array<string, mixed>  $entradas
     */
    public function conMetodo(MetodoCosto $metodo, array $entradas): self
    {
        return $this->state(['metodo' => $metodo, 'entradas' => $entradas]);
    }
}
