<?php

namespace Database\Seeders;

use App\Models\Conductor;
use App\Models\Sucursal;
use App\Models\Vehiculo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FlotaDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed realistic demo data for the fleet module.
     */
    public function run(): void
    {
        $sucursales = collect([
            ['nombre' => 'Sucursal Lima', 'codigo' => 'LIM', 'ciudad' => 'Lima'],
            ['nombre' => 'Sucursal Arequipa', 'codigo' => 'AQP', 'ciudad' => 'Arequipa'],
            ['nombre' => 'Sucursal Trujillo', 'codigo' => 'TRU', 'ciudad' => 'Trujillo'],
        ])->map(fn (array $datos): Sucursal => Sucursal::factory()->create($datos));

        $sucursales->each(function (Sucursal $sucursal): void {
            $conductores = Conductor::factory()
                ->count(4)
                ->for($sucursal)
                ->create();

            // Vehículos asignados a conductores de la sucursal.
            $conductores->each(function (Conductor $conductor): void {
                Vehiculo::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->for($conductor->sucursal)
                    ->paraConductor($conductor)
                    ->create();
            });

            // Algunos vehículos sin conductor y en mantenimiento.
            Vehiculo::factory()
                ->count(2)
                ->for($sucursal)
                ->sinConductor()
                ->create();

            Vehiculo::factory()
                ->for($sucursal)
                ->enMantenimiento()
                ->create();
        });
    }
}
