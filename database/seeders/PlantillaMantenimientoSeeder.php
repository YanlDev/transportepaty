<?php

namespace Database\Seeders;

use App\Models\PlantillaMantenimiento;
use Illuminate\Database\Seeder;

class PlantillaMantenimientoSeeder extends Seeder
{
    private const PLANTILLAS = [
        // Toyota Hilux / Fortuner
        ['nombre' => 'Cambio de aceite + filtro', 'tipo_mantenimiento' => 'aceite', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 5000, 'intervalo_meses' => 6, 'orden' => 1],
        ['nombre' => 'Cambio de aceite + filtro', 'tipo_mantenimiento' => 'aceite', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 5000, 'intervalo_meses' => 6, 'orden' => 1],
        ['nombre' => 'Filtro de aire', 'tipo_mantenimiento' => 'filtro_aire', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 10000, 'intervalo_meses' => 12, 'orden' => 2],
        ['nombre' => 'Filtro de aire', 'tipo_mantenimiento' => 'filtro_aire', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 10000, 'intervalo_meses' => 12, 'orden' => 2],
        ['nombre' => 'Filtro de combustible', 'tipo_mantenimiento' => 'filtro_combustible', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 10000, 'intervalo_meses' => 12, 'orden' => 3],
        ['nombre' => 'Filtro de combustible', 'tipo_mantenimiento' => 'filtro_combustible', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 10000, 'intervalo_meses' => 12, 'orden' => 3],
        ['nombre' => 'Rotación de neumáticos', 'tipo_mantenimiento' => 'neumaticos', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 5000, 'intervalo_meses' => 6, 'orden' => 4],
        ['nombre' => 'Rotación de neumáticos', 'tipo_mantenimiento' => 'neumaticos', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 5000, 'intervalo_meses' => 6, 'orden' => 4],
        ['nombre' => 'Líquido de frenos', 'tipo_mantenimiento' => 'frenos', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 40000, 'intervalo_meses' => 24, 'orden' => 5],
        ['nombre' => 'Líquido de frenos', 'tipo_mantenimiento' => 'frenos', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 40000, 'intervalo_meses' => 24, 'orden' => 5],
        ['nombre' => 'Refrigerante', 'tipo_mantenimiento' => 'refrigerante', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 50000, 'intervalo_meses' => 24, 'orden' => 6],
        ['nombre' => 'Refrigerante', 'tipo_mantenimiento' => 'refrigerante', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 50000, 'intervalo_meses' => 24, 'orden' => 6],
        ['nombre' => 'Aceite de transmisión', 'tipo_mantenimiento' => 'transmision', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 80000, 'intervalo_meses' => 48, 'orden' => 7],
        ['nombre' => 'Aceite de transmisión', 'tipo_mantenimiento' => 'transmision', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 80000, 'intervalo_meses' => 48, 'orden' => 7],
        ['nombre' => 'Bujías', 'tipo_mantenimiento' => 'bujias', 'marca' => 'Toyota', 'modelo' => 'Hilux', 'intervalo_km' => 100000, 'orden' => 8],
        ['nombre' => 'Bujías', 'tipo_mantenimiento' => 'bujias', 'marca' => 'Toyota', 'modelo' => 'Fortuner', 'intervalo_km' => 100000, 'orden' => 8],

        // Toyota RAV4
        ['nombre' => 'Cambio de aceite + filtro', 'tipo_mantenimiento' => 'aceite', 'marca' => 'Toyota', 'modelo' => 'RAV4', 'intervalo_km' => 5000, 'intervalo_meses' => 6, 'orden' => 1],
        ['nombre' => 'Filtro de aire', 'tipo_mantenimiento' => 'filtro_aire', 'marca' => 'Toyota', 'modelo' => 'RAV4', 'intervalo_km' => 10000, 'intervalo_meses' => 12, 'orden' => 2],
        ['nombre' => 'Líquido de frenos', 'tipo_mantenimiento' => 'frenos', 'marca' => 'Toyota', 'modelo' => 'RAV4', 'intervalo_km' => 40000, 'intervalo_meses' => 24, 'orden' => 3],
        ['nombre' => 'Refrigerante', 'tipo_mantenimiento' => 'refrigerante', 'marca' => 'Toyota', 'modelo' => 'RAV4', 'intervalo_km' => 50000, 'intervalo_meses' => 24, 'orden' => 4],
        ['nombre' => 'Bujías', 'tipo_mantenimiento' => 'bujias', 'marca' => 'Toyota', 'modelo' => 'RAV4', 'intervalo_km' => 100000, 'orden' => 5],

        // Toyota Land Cruiser / Land Cruiser Prado
        ['nombre' => 'Cambio de aceite + filtro', 'tipo_mantenimiento' => 'aceite', 'marca' => 'Toyota', 'modelo' => 'Land Cruiser', 'intervalo_km' => 5000, 'intervalo_meses' => 6, 'orden' => 1],
        ['nombre' => 'Filtro de aire', 'tipo_mantenimiento' => 'filtro_aire', 'marca' => 'Toyota', 'modelo' => 'Land Cruiser', 'intervalo_km' => 10000, 'intervalo_meses' => 12, 'orden' => 2],
        ['nombre' => 'Líquido de frenos', 'tipo_mantenimiento' => 'frenos', 'marca' => 'Toyota', 'modelo' => 'Land Cruiser', 'intervalo_km' => 40000, 'intervalo_meses' => 24, 'orden' => 3],
        ['nombre' => 'Refrigerante', 'tipo_mantenimiento' => 'refrigerante', 'marca' => 'Toyota', 'modelo' => 'Land Cruiser', 'intervalo_km' => 50000, 'intervalo_meses' => 24, 'orden' => 4],
        ['nombre' => 'Aceite de transmisión', 'tipo_mantenimiento' => 'transmision', 'marca' => 'Toyota', 'modelo' => 'Land Cruiser', 'intervalo_km' => 60000, 'intervalo_meses' => 48, 'orden' => 5],
        ['nombre' => 'Bujías', 'tipo_mantenimiento' => 'bujias', 'marca' => 'Toyota', 'modelo' => 'Land Cruiser', 'intervalo_km' => 100000, 'orden' => 6],

        // Moto (genérica por tipo_vehiculo)
        ['nombre' => 'Cambio de aceite', 'tipo_mantenimiento' => 'aceite', 'tipo_vehiculo' => 'moto', 'intervalo_km' => 3000, 'intervalo_meses' => 6, 'orden' => 1],
        ['nombre' => 'Filtro de aire', 'tipo_mantenimiento' => 'filtro_aire', 'tipo_vehiculo' => 'moto', 'intervalo_km' => 6000, 'orden' => 2],
        ['nombre' => 'Lubricar y ajustar cadena', 'tipo_mantenimiento' => 'cadena', 'tipo_vehiculo' => 'moto', 'intervalo_km' => 1000, 'orden' => 3],
        ['nombre' => 'Frenos', 'tipo_mantenimiento' => 'frenos', 'tipo_vehiculo' => 'moto', 'intervalo_km' => 12000, 'orden' => 4],
        ['nombre' => 'Bujía', 'tipo_mantenimiento' => 'bujias', 'tipo_vehiculo' => 'moto', 'intervalo_km' => 12000, 'orden' => 5],

        // Genéricas (todo null)
        ['nombre' => 'Cambio de aceite + filtro', 'tipo_mantenimiento' => 'aceite', 'intervalo_km' => 5000, 'intervalo_meses' => 6, 'orden' => 1],
        ['nombre' => 'Filtro de aire', 'tipo_mantenimiento' => 'filtro_aire', 'intervalo_km' => 10000, 'intervalo_meses' => 12, 'orden' => 2],
        ['nombre' => 'Líquido de frenos', 'tipo_mantenimiento' => 'frenos', 'intervalo_km' => 40000, 'intervalo_meses' => 24, 'orden' => 3],
        ['nombre' => 'Refrigerante', 'tipo_mantenimiento' => 'refrigerante', 'intervalo_km' => 50000, 'intervalo_meses' => 24, 'orden' => 4],
    ];

    public function run(): void
    {
        foreach (self::PLANTILLAS as $data) {
            PlantillaMantenimiento::updateOrCreate(
                ['nombre' => $data['nombre'], 'marca' => $data['marca'] ?? null, 'modelo' => $data['modelo'] ?? null, 'tipo_vehiculo' => $data['tipo_vehiculo'] ?? null],
                $data,
            );
        }
    }
}
